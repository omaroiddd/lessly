<?php
/**
 * Tests for lessly_get_copyright_text().
 *
 * @package Lessly\Tests
 */

declare( strict_types=1 );

namespace Lessly\Tests;

use Brain\Monkey\Functions;

final class CopyrightTextTest extends BaseTestCase {

	/**
	 * Load the file under test once. The theme isn't autoloaded, so we
	 * pull it in manually relative to the constant defined in bootstrap.php.
	 */
	protected function setUp(): void {
		parent::setUp();
		require_once LESSLY_THEME_DIR . '/inc/template-tags.php';
	}

	/**
	 * Output should contain the © symbol, the current UTC year, and the site name.
	 */
	public function test_returns_formatted_copyright_with_year_and_site_name(): void {
		Functions\when( 'get_bloginfo' )->justReturn( 'Lessly Test Site' );
		Functions\when( '__' )->returnArg();

		$result = lessly_get_copyright_text();

		$this->assertStringContainsString( '©', $result );
		$this->assertStringContainsString( gmdate( 'Y' ), $result );
		$this->assertStringContainsString( 'Lessly Test Site', $result );
	}

	/**
	 * Whatever get_bloginfo returns must end up in the output.
	 */
	public function test_uses_site_name_from_get_bloginfo(): void {
		Functions\when( 'get_bloginfo' )->justReturn( 'Custom Blog Name' );
		Functions\when( '__' )->returnArg();

		$result = lessly_get_copyright_text();

		$this->assertStringContainsString( 'Custom Blog Name', $result );
	}
}
