<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;
use Brain\Monkey\Functions;

require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';
require_once CDW_PLUGIN_DIR . 'includes/services/ai/class-cdw-ai-usage-tracker.php';

class AiUsageTrackerTest extends CDWTestCase {

    public function test_record_usage_updates_user_meta(): void {
        Functions\when( 'get_user_meta' )->justReturn( array(
            'prompt_tokens'     => 10,
            'completion_tokens' => 20,
            'total_tokens'      => 30,
            'request_count'     => 5,
        ) );
        Functions\when( 'update_user_meta' )->justReturn( true );

        \CDW_AI_Usage_Tracker::record_usage( 1, array(
            'prompt_tokens'     => 5,
            'completion_tokens' => 10,
            'total_tokens'      => 15,
        ) );
    }

    public function test_record_usage_initializes_defaults_when_no_meta(): void {
        Functions\when( 'get_user_meta' )->justReturn( '' );
        Functions\when( 'update_user_meta' )->justReturn( true );

        \CDW_AI_Usage_Tracker::record_usage( 1, array(
            'prompt_tokens'     => 5,
            'completion_tokens' => 10,
            'total_tokens'      => 15,
        ) );
    }

    public function test_reset_usage_deletes_user_meta(): void {
        Functions\when( 'delete_user_meta' )->justReturn( true );

        \CDW_AI_Usage_Tracker::reset_usage( 1 );
    }

    public function test_get_usage_returns_defaults_when_no_meta(): void {
        Functions\when( 'get_user_meta' )->justReturn( '' );

        $usage = \CDW_AI_Usage_Tracker::get_usage( 1 );

        $this->assertSame( 0, $usage['prompt_tokens'] );
        $this->assertSame( 0, $usage['completion_tokens'] );
        $this->assertSame( 0, $usage['total_tokens'] );
        $this->assertSame( 0, $usage['request_count'] );
    }

    public function test_get_usage_returns_saved_usage(): void {
        $saved_usage = array(
            'prompt_tokens'     => 100,
            'completion_tokens' => 200,
            'total_tokens'      => 300,
            'request_count'     => 10,
        );
        Functions\when( 'get_user_meta' )->justReturn( $saved_usage );

        $usage = \CDW_AI_Usage_Tracker::get_usage( 1 );

        $this->assertSame( $saved_usage, $usage );
    }

    public function test_default_usage_constant_matches_expected_values(): void {
        $this->assertSame( 0, \CDW_AI_Usage_Tracker::DEFAULT_USAGE['prompt_tokens'] );
        $this->assertSame( 0, \CDW_AI_Usage_Tracker::DEFAULT_USAGE['completion_tokens'] );
        $this->assertSame( 0, \CDW_AI_Usage_Tracker::DEFAULT_USAGE['total_tokens'] );
        $this->assertSame( 0, \CDW_AI_Usage_Tracker::DEFAULT_USAGE['request_count'] );
    }
}
