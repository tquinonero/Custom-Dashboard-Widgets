<?php

namespace CDW\Tests;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

abstract class CDWTestCase extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        // Default stubs so every test survives rate-limit and nonce checks
        // without having to repeat this boilerplate.  Individual tests that
        // exercise the nonce/rate-limit failure paths clear $_SERVER or
        // re-stub these functions inside the test itself, which overrides
        // the defaults set here.
        $_SERVER['HTTP_X_WP_NONCE'] = 'valid-nonce';
        Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        Functions\when( 'get_transient' )->justReturn( 0 );
        Functions\when( 'set_transient' )->justReturn( true );
    }

    protected function tearDown(): void {
        unset( $_SERVER['HTTP_X_WP_NONCE'] );
        Monkey\tearDown();
        parent::tearDown();
    }
}
