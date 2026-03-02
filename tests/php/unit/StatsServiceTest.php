<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;
use Brain\Monkey\Functions;

require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-stats-service.php';

class StatsServiceTest extends CDWTestCase {

    private \CDW_Stats_Service $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = new \CDW_Stats_Service();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Set up all WP function mocks needed by fetch_stats().
     */
    private function stubFetchStatsFunctions( array $overrides = array() ): void {
        $defaults = array(
            'posts'      => 5,
            'pages'      => array( 'publish' => 3, 'draft' => 1 ),
            'comments'   => 7,
            'users'      => 4,
            'media'      => 10,
            'categories' => 6,
            'tags'       => 8,
            'plugins'    => 2,
            'themes'     => 3,
        );
        $data = array_merge( $defaults, $overrides );

        Functions\when( 'get_plugins' )->justReturn( array_fill( 0, $data['plugins'], array() ) );

        Functions\when( 'wp_count_posts' )->alias( function( $type = 'post' ) use ( $data ) {
            $obj = new \stdClass();
            if ( 'page' === $type ) {
                foreach ( $data['pages'] as $status => $count ) {
                    $obj->$status = $count;
                }
            } elseif ( 'attachment' === $type ) {
                $obj->inherit = $data['media'];
            } elseif ( 'product' === $type ) {
                $obj->publish = $data['products'] ?? 0;
            } else {
                $obj->publish = $data['posts'];
            }
            return $obj;
        } );

        Functions\when( 'wp_count_comments' )->alias( function() use ( $data ) {
            $obj           = new \stdClass();
            $obj->approved = $data['comments'];
            return $obj;
        } );

        Functions\when( 'count_users' )->justReturn( array( 'total_users' => $data['users'] ) );

        Functions\when( 'wp_count_terms' )->alias( function( $args ) use ( $data ) {
            if ( isset( $args['taxonomy'] ) && 'category' === $args['taxonomy'] ) {
                return $data['categories'];
            }
            return $data['tags'];
        } );

        Functions\when( 'wp_get_themes' )->justReturn( array_fill( 0, $data['themes'], array() ) );
    }

    // -----------------------------------------------------------------------
    // get_stats()
    // -----------------------------------------------------------------------

    public function test_cache_hit_returns_cached_value_without_calling_wp_count_posts(): void {
        $cached = array( 'posts' => 99, 'pages' => 1 );
        Functions\when( 'get_transient' )->justReturn( $cached );
        Functions\expect( 'wp_count_posts' )->never();

        $result = $this->service->get_stats();

        $this->assertSame( $cached, $result );
    }

    public function test_cache_miss_calls_set_transient_with_correct_key_and_ttl(): void {
        Functions\when( 'get_transient' )->justReturn( false );
        $this->stubFetchStatsFunctions();

        $capturedArgs = null;
        Functions\when( 'set_transient' )->alias(
            function( $key, $value, $ttl ) use ( &$capturedArgs ) {
                $capturedArgs = array( $key, $ttl );
            }
        );

        $this->service->get_stats();

        $this->assertSame( 'cdw_stats_cache', $capturedArgs[0] );
        $this->assertSame( 60, $capturedArgs[1] );
    }

    public function test_cache_miss_returns_array_with_all_required_keys(): void {
        Functions\when( 'get_transient' )->justReturn( false );
        $this->stubFetchStatsFunctions();
        Functions\when( 'set_transient' )->justReturn( true );

        $result = $this->service->get_stats();

        $required = array( 'posts', 'pages', 'comments', 'users', 'media', 'categories', 'tags', 'plugins', 'themes' );
        foreach ( $required as $key ) {
            $this->assertArrayHasKey( $key, $result, "Missing key: $key" );
        }
    }

    // -----------------------------------------------------------------------
    // clear_cache()
    // -----------------------------------------------------------------------

    public function test_clear_cache_calls_delete_transient_with_correct_key(): void {
        $capturedKey = null;
        Functions\when( 'delete_transient' )->alias( function( $key ) use ( &$capturedKey ) {
            $capturedKey = $key;
        } );

        $this->service->clear_cache();

        $this->assertSame( 'cdw_stats_cache', $capturedKey );
    }

    // -----------------------------------------------------------------------
    // sum_post_statuses() — via reflection
    // -----------------------------------------------------------------------

    private function callSumPostStatuses( object $counts ): int {
        $ref    = new \ReflectionClass( $this->service );
        $method = $ref->getMethod( 'sum_post_statuses' );
        $method->setAccessible( true );
        return $method->invoke( $this->service, $counts );
    }

    public function test_sum_post_statuses_returns_correct_total_for_all_statuses(): void {
        $counts          = new \stdClass();
        $counts->publish = 5;
        $counts->draft   = 2;
        $counts->pending = 1;
        $counts->private = 3;

        $this->assertSame( 11, $this->callSumPostStatuses( $counts ) );
    }

    public function test_sum_post_statuses_with_only_publish_returns_publish_count(): void {
        $counts          = new \stdClass();
        $counts->publish = 5;

        $this->assertSame( 5, $this->callSumPostStatuses( $counts ) );
    }

    public function test_sum_post_statuses_with_all_zero_returns_zero(): void {
        $counts          = new \stdClass();
        $counts->publish = 0;
        $counts->draft   = 0;
        $counts->pending = 0;
        $counts->private = 0;

        $this->assertSame( 0, $this->callSumPostStatuses( $counts ) );
    }

    // -----------------------------------------------------------------------
    // WooCommerce integration
    // -----------------------------------------------------------------------

    public function test_get_stats_has_no_products_key_when_woocommerce_absent(): void {
        Functions\when( 'get_transient' )->justReturn( false );
        $this->stubFetchStatsFunctions();
        Functions\when( 'set_transient' )->justReturn( true );
        // class_exists('WooCommerce') is naturally false in test environment

        $result = $this->service->get_stats();

        $this->assertArrayNotHasKey( 'products', $result );
    }

    /**
     * Run in its own process so we can safely define the WooCommerce class
     * without affecting other tests.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_get_stats_has_products_key_when_woocommerce_present(): void {
        // Define WooCommerce so class_exists('WooCommerce') returns true.
        if ( ! class_exists( 'WooCommerce' ) ) {
            eval( 'class WooCommerce {}' ); // phpcs:ignore
        }

        Functions\when( 'get_transient' )->justReturn( false );
        $this->stubFetchStatsFunctions( array( 'products' => 12 ) );
        Functions\when( 'set_transient' )->justReturn( true );

        $result = $this->service->get_stats();

        $this->assertArrayHasKey( 'products', $result );
    }
}
