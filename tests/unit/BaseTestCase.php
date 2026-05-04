<?php
/**
 * Base test case.
 *
 * All Lessly unit tests should extend this class. It wires up Brain Monkey
 * (which mocks WordPress functions) before each test and tears it down after,
 * so individual tests stay focused on assertions instead of setup boilerplate.
 *
 * @package Lessly\Tests
 */

declare( strict_types=1 );

namespace Lessly\Tests;

use Brain\Monkey;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Shared setup/teardown for every Lessly unit test.
 */
abstract class BaseTestCase extends TestCase {

	/**
	 * Set up Brain Monkey before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tear down Brain Monkey and Mockery after each test.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		Mockery::close();
		parent::tearDown();
	}
}
