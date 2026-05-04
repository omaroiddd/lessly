<?php
/**
 * PHPUnit bootstrap file.
 *
 * Runs once before the test suite starts. Loads Composer's autoloader
 * so test classes and dependencies (PHPUnit, Brain Monkey, Mockery) are
 * available, then defines a couple of WordPress constants tests expect
 * to exist.
 *
 * @package Lessly
 */

declare( strict_types=1 );

// Load Composer autoloader (test dependencies + theme PSR-4 autoload).
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// WordPress constants that some theme code might reference.
// Tests don't run inside WordPress, so we define minimal stand-ins.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

// Theme path constants — match whatever your theme uses internally.
if ( ! defined( 'LESSLY_THEME_DIR' ) ) {
	define( 'LESSLY_THEME_DIR', dirname( __DIR__ ) . '/lessly' );
}

if ( ! defined( 'LESSLY_THEME_URI' ) ) {
	define( 'LESSLY_THEME_URI', 'http://example.test/wp-content/themes/lessly' );
}