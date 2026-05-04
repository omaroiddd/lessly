<?php
/**
 * Example test demonstrating the patterns to use in this project.
 *
 * Delete this file once you have real tests — it exists only to show
 * the conventions: namespace, class naming, Brain Monkey usage, and
 * how to mock WordPress functions and hooks.
 *
 * @package Lessly\Tests
 */

declare( strict_types=1 );

namespace Lessly\Tests;

use Brain\Monkey\Functions;
use Brain\Monkey\Filters;
use Brain\Monkey\Actions;

final class ExampleTest extends BaseTestCase {

	/**
	 * Sanity check — PHPUnit itself works.
	 */
	public function test_phpunit_runs(): void {
		$this->assertTrue( true );
	}

	/**
	 * Mock a WordPress function and verify the result.
	 */
	public function test_mocking_a_wordpress_function(): void {
		Functions\when( 'get_bloginfo' )->justReturn( 'Lessly Test Site' );

		$this->assertSame( 'Lessly Test Site', get_bloginfo( 'name' ) );
	}

	/**
	 * Verify a function passes a value through a filter.
	 */
	public function test_filter_is_applied(): void {
		Filters\expectApplied( 'lessly_greeting' )
			->once()
			->with( 'Hello' )
			->andReturn( 'Hello, world!' );

		$result = apply_filters( 'lessly_greeting', 'Hello' );

		$this->assertSame( 'Hello, world!', $result );
	}

	/**
	 * Verify an action fires.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_action_is_fired(): void {
		Actions\expectDone( 'lessly_after_setup' )->once();

		do_action( 'lessly_after_setup' );
	}

	/**
	 * Verify escaping/sanitization helpers are called.
	 *
	 * Common pattern: when testing theme code that escapes output,
	 * mock the escape function to return its input unchanged so you
	 * can assert on the underlying logic.
	 */
	public function test_escaped_output(): void {
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( '__' )->returnArg();

		$this->assertSame( 'Hello', esc_html( __( 'Hello', 'lessly' ) ) );
	}
}