<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;
use Brain\Monkey\Functions;

require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';
require_once CDW_PLUGIN_DIR . 'includes/services/ai/class-cdw-ai-rate-limiter.php';

class AiRateLimiterTest extends CDWTestCase {

    public function test_check_returns_true_when_under_limit(): void {
        Functions\when( 'get_transient' )->justReturn( 0 );
        Functions\when( 'set_transient' )->justReturn( true );

        $result = \CDW_AI_Rate_Limiter::check( 1 );

        $this->assertTrue( $result );
    }

    public function test_check_increments_counter_via_set_transient(): void {
        Functions\when( 'get_transient' )->justReturn( 5 );

        $capturedCount = null;
        Functions\when( 'set_transient' )->alias(
            function( $key, $count ) use ( &$capturedCount ) {
                $capturedCount = $count;
            }
        );

        \CDW_AI_Rate_Limiter::check( 1 );

        $this->assertSame( 6, $capturedCount );
    }

    public function test_check_returns_wp_error_when_at_limit(): void {
        Functions\when( 'get_transient' )->justReturn( \CDW_AI_Rate_Limiter::RATE_LIMIT_COUNT );

        $result = \CDW_AI_Rate_Limiter::check( 1 );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'ai_rate_limited', $result->get_error_code() );
        $this->assertSame( 429, $result->get_error_data()['status'] );
    }

    public function test_check_uses_user_specific_transient_key(): void {
        Functions\when( 'get_transient' )->justReturn( 0 );

        $capturedKey = null;
        Functions\when( 'set_transient' )->alias(
            function( $key ) use ( &$capturedKey ) {
                $capturedKey = $key;
            }
        );

        \CDW_AI_Rate_Limiter::check( 42 );

        $this->assertStringContainsString( '42', $capturedKey );
    }

    public function test_constants_match_expected_values(): void {
        $this->assertSame( 30, \CDW_AI_Rate_Limiter::RATE_LIMIT_COUNT );
        $this->assertSame( 60, \CDW_AI_Rate_Limiter::RATE_LIMIT_WINDOW );
    }
}
