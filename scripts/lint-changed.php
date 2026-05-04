<?php
/**
 * Lint only the PHP files that have changed in the working tree.
 *
 * Backs the `composer php:lint:changed` script. The vanilla
 * `phpcs-changed --git --git-unstaged` invocation fails on Windows because
 * its automatic mode shells out to `type` / `cat` for file content; in PHP's
 * exec context that pipe collapses and phpcs ends up reading empty stdin,
 * producing false-negative "0 errors" output.
 *
 * This wrapper avoids that entirely by using phpcs-changed in **manual mode**:
 * for each modified, tracked file it pre-generates the diff and the phpcs JSON
 * for both the HEAD version and the working-copy version, then hands those
 * files to phpcs-changed. New (untracked) files have no prior version to diff
 * against, so they go straight to plain phpcs.
 *
 * Exit code is the worst of the runs; 0 when there are no changed PHP files.
 *
 * @package Lessly
 */

declare( strict_types=1 );

chdir( dirname( __DIR__ ) );

/**
 * Run a command, capturing stdout lines. Returns [] on non-zero exit.
 *
 * @param string $command Shell command to execute.
 * @return string[]
 */
function lessly_lint_changed_git_lines( string $command ): array {
	$output = array();
	$status = 0;
	exec( $command, $output, $status );
	if ( 0 !== $status ) {
		return array();
	}
	return array_values(
		array_filter(
			array_map( 'trim', $output ),
			static fn( string $line ): bool => '' !== $line
		)
	);
}

/**
 * Resolve a vendor/bin executable, picking the .bat stub on Windows.
 *
 * @param string $name Binary base name.
 * @return string Path suitable for passing to escapeshellarg().
 */
function lessly_lint_changed_vendor_bin( string $name ): string {
	$suffix = ( 'Windows' === PHP_OS_FAMILY ) ? '.bat' : '';
	return 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . $name . $suffix;
}

/**
 * Capture stdout of a command as a string. Returns '' on failure.
 *
 * @param string $command Shell command to execute.
 * @return string
 */
function lessly_lint_changed_capture( string $command ): string {
	$out = shell_exec( $command );
	return is_string( $out ) ? $out : '';
}

/**
 * Lint one tracked-and-modified file via phpcs-changed manual mode.
 *
 * @param string $file              Path relative to repo root.
 * @param string $phpcs_bin         Resolved vendor/bin/phpcs path.
 * @param string $phpcs_changed_bin Resolved vendor/bin/phpcs-changed path.
 * @return int Exit code from phpcs-changed.
 */
function lessly_lint_changed_run_manual( string $file, string $phpcs_bin, string $phpcs_changed_bin ): int {
	$tmp_dir = sys_get_temp_dir();
	$id      = bin2hex( random_bytes( 6 ) );
	$paths   = array(
		'unmod_php'  => $tmp_dir . DIRECTORY_SEPARATOR . 'lessly-unmod-' . $id . '.php',
		'unmod_json' => $tmp_dir . DIRECTORY_SEPARATOR . 'lessly-unmod-' . $id . '.json',
		'mod_json'   => $tmp_dir . DIRECTORY_SEPARATOR . 'lessly-mod-' . $id . '.json',
		'diff'       => $tmp_dir . DIRECTORY_SEPARATOR . 'lessly-diff-' . $id . '.diff',
	);

	try {
		// HEAD version of the file.
		$head_content = lessly_lint_changed_capture( 'git show HEAD:' . escapeshellarg( $file ) );
		file_put_contents( $paths['unmod_php'], $head_content );

		// Diff. If empty, nothing to compare; treat as clean.
		$diff_content = lessly_lint_changed_capture( 'git diff --no-prefix -- ' . escapeshellarg( $file ) );
		if ( '' === trim( $diff_content ) ) {
			return 0;
		}
		file_put_contents( $paths['diff'], $diff_content );

		// phpcs JSON for both versions.
		$phpcs_arg = escapeshellarg( $phpcs_bin );
		file_put_contents(
			$paths['unmod_json'],
			lessly_lint_changed_capture( $phpcs_arg . ' --report=json -q ' . escapeshellarg( $paths['unmod_php'] ) )
		);
		file_put_contents(
			$paths['mod_json'],
			lessly_lint_changed_capture( $phpcs_arg . ' --report=json -q ' . escapeshellarg( $file ) )
		);

		// Hand it all to phpcs-changed in manual mode.
		$cmd = escapeshellarg( $phpcs_changed_bin )
			. ' -s'
			. ' --diff ' . escapeshellarg( $paths['diff'] )
			. ' --phpcs-unmodified ' . escapeshellarg( $paths['unmod_json'] )
			. ' --phpcs-modified ' . escapeshellarg( $paths['mod_json'] );

		$status = 0;
		passthru( $cmd, $status );
		return $status;
	} finally {
		foreach ( $paths as $p ) {
			if ( file_exists( $p ) ) {
				@unlink( $p ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
	}
}

$phpcs_bin         = lessly_lint_changed_vendor_bin( 'phpcs' );
$phpcs_changed_bin = lessly_lint_changed_vendor_bin( 'phpcs-changed' );

$modified_all = lessly_lint_changed_git_lines( 'git diff --name-only --diff-filter=AMR -- "*.php"' );
$untracked    = lessly_lint_changed_git_lines( 'git ls-files --others --exclude-standard -- "*.php"' );

// Split modified into "exists at HEAD" vs "added in index but not at HEAD".
$modified  = array();
$new_added = array();
foreach ( $modified_all as $path ) {
	if ( ! is_file( $path ) ) {
		continue;
	}
	$head_check = 0;
	$out        = array();
	exec( 'git cat-file -e HEAD:' . escapeshellarg( $path ) . ' 2>&1', $out, $head_check );
	if ( 0 === $head_check ) {
		$modified[] = $path;
	} else {
		$new_added[] = $path;
	}
}

$untracked = array_values( array_filter( $untracked, 'is_file' ) );
$new_files = array_merge( $new_added, $untracked );

if ( empty( $modified ) && empty( $new_files ) ) {
	fwrite( STDOUT, 'No changed PHP files to lint.' . PHP_EOL );
	exit( 0 );
}

$worst_exit = 0;

foreach ( $modified as $file ) {
	fwrite( STDOUT, 'phpcs-changed: ' . $file . PHP_EOL );
	$code = lessly_lint_changed_run_manual( $file, $phpcs_bin, $phpcs_changed_bin );
	if ( $code > $worst_exit ) {
		$worst_exit = $code;
	}
}

if ( ! empty( $new_files ) ) {
	fwrite( STDOUT, sprintf( 'phpcs (full) on %d new file(s):' . PHP_EOL, count( $new_files ) ) );
	$cmd = escapeshellarg( $phpcs_bin ) . ' -p -s ' . implode( ' ', array_map( 'escapeshellarg', $new_files ) );
	$status = 0;
	passthru( $cmd, $status );
	if ( $status > $worst_exit ) {
		$worst_exit = $status;
	}
}

exit( $worst_exit );
