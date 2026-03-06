<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;
use Brain\Monkey\Functions;

require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';
require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';
require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-media-controller.php';

class MediaControllerTest extends CDWTestCase {

    private \CDW_Media_Controller $controller;

    protected function setUp(): void {
        parent::setUp();
        $this->controller = new \CDW_Media_Controller();

        Functions\when( 'rest_ensure_response' )->alias( function( $data ) {
            return new \WP_REST_Response( $data, 200 );
        } );

        // Reset WP_Query mock posts between tests.
        \WP_Query::$mock_posts = array();
    }

    /** Builds a minimal WP_REST_Request with an optional per_page param. */
    private function makeRequest( int $perPage = 10 ): \WP_REST_Request {
        $request = new \WP_REST_Request();
        $request->set_param( 'per_page', $perPage );
        return $request;
    }

    // -----------------------------------------------------------------------
    // get_media() — cache hit
    // -----------------------------------------------------------------------

    public function test_cache_hit_returns_cached_items_without_querying_db(): void {
        $cached = array(
            array( 'id' => 1, 'title' => 'Photo 1', 'url' => 'https://example.com/photo1.jpg', 'date' => '2024-01-01T00:00:00+00:00' ),
        );
        Functions\when( 'get_transient' )->justReturn( $cached );

        $result = $this->controller->get_media( $this->makeRequest() );

        $this->assertInstanceOf( \WP_REST_Response::class, $result );
        $this->assertSame( $cached, $result->get_data() );
    }

    public function test_cache_hit_does_not_call_set_transient(): void {
        Functions\when( 'get_transient' )->justReturn( array( array( 'id' => 1, 'title' => 'x' ) ) );

        $setCalled = false;
        Functions\when( 'set_transient' )->alias( function() use ( &$setCalled ) {
            $setCalled = true;
        } );

        $this->controller->get_media( $this->makeRequest() );

        $this->assertFalse( $setCalled );
    }

    // -----------------------------------------------------------------------
    // get_media() — cache miss, empty query result
    // -----------------------------------------------------------------------

    public function test_cache_miss_with_empty_query_returns_empty_array(): void {
        Functions\when( 'get_transient' )->justReturn( false );
        Functions\when( 'set_transient' )->justReturn( true );
        // WP_Query::$mock_posts = [] so have_posts() → false

        $result = $this->controller->get_media( $this->makeRequest() );

        $this->assertSame( array(), $result->get_data() );
    }

    // -----------------------------------------------------------------------
    // get_media() — cache miss, query returns items
    // -----------------------------------------------------------------------

    public function test_cache_miss_with_items_returns_mapped_array(): void {
        $post        = new \stdClass();
        $post->ID    = 5;
        $post->post_title = 'My Photo';

        \WP_Query::$mock_posts = array( $post );

        Functions\when( 'get_transient' )->justReturn( false );
        Functions\when( 'set_transient' )->justReturn( true );
        Functions\when( 'get_the_ID' )->justReturn( 5 );
        Functions\when( 'get_the_title' )->justReturn( 'My Photo' );
        Functions\when( 'wp_get_attachment_url' )->justReturn( 'https://example.com/my-photo.jpg' );
        Functions\when( 'get_the_date' )->justReturn( '2024-05-01T00:00:00+00:00' );
        Functions\when( 'wp_reset_postdata' )->justReturn( null );

        $result = $this->controller->get_media( $this->makeRequest() );
        $data   = $result->get_data();

        $this->assertCount( 1, $data );
        $this->assertSame( 5,            $data[0]['id'] );
        $this->assertSame( 'My Photo',   $data[0]['title'] );
        $this->assertStringContainsString( 'my-photo', $data[0]['url'] );
    }

    // -----------------------------------------------------------------------
    // get_media() — cache stores result with 300 s TTL
    // -----------------------------------------------------------------------

    public function test_cache_miss_stores_result_with_300_second_ttl(): void {
        Functions\when( 'get_transient' )->justReturn( false );
        Functions\when( 'wp_reset_postdata' )->justReturn( null );

        $capturedTtl = null;
        Functions\when( 'set_transient' )->alias( function( $key, $value, $ttl ) use ( &$capturedTtl ) {
            $capturedTtl = $ttl;
        } );

        $this->controller->get_media( $this->makeRequest() );

        $this->assertSame( 300, $capturedTtl );
    }

    // -----------------------------------------------------------------------
    // get_media() — cache key includes per_page
    // -----------------------------------------------------------------------

    public function test_different_per_page_values_use_different_cache_keys(): void {
        $capturedKeys = array();
        Functions\when( 'get_transient' )->justReturn( false );
        Functions\when( 'set_transient' )->alias( function( $key ) use ( &$capturedKeys ) {
            $capturedKeys[] = $key;
        } );

        $this->controller->get_media( $this->makeRequest( 5 ) );
        $this->controller->get_media( $this->makeRequest( 20 ) );

        $this->assertCount( 2, array_unique( $capturedKeys ) );
    }
}
