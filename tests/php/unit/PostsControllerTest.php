<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;
use Brain\Monkey\Functions;

require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';
require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-posts-controller.php';

class PostsControllerTest extends CDWTestCase {

    private \CDW_Posts_Controller $controller;

    protected function setUp(): void {
        parent::setUp();
        global $wpdb;
        $GLOBALS['wpdb'] = new \wpdb();

        $this->controller = new \CDW_Posts_Controller();

        Functions\when( 'rest_ensure_response' )->alias( function( $data ) {
            return new \WP_REST_Response( $data, 200 );
        } );
    }

    /** Build a minimal WP_REST_Request stub with given params. */
    private function makeRequest( array $params = array() ): \WP_REST_Request {
        $request = new \WP_REST_Request();
        $defaults = array(
            'per_page'  => 10,
            'status'    => 'publish',
            'post_type' => 'post',
        );
        foreach ( array_merge( $defaults, $params ) as $k => $v ) {
            $request->set_param( $k, $v );
        }
        return $request;
    }

    // -----------------------------------------------------------------------
    // post_type validation
    // -----------------------------------------------------------------------

    public function test_public_post_type_proceeds(): void {
        $obj         = new \stdClass();
        $obj->public = true;
        Functions\when( 'get_post_type_object' )->justReturn( $obj );
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'get_transient' )->justReturn( false );
        Functions\when( 'get_posts' )->justReturn( array() );
        Functions\when( 'set_transient' )->justReturn( true );

        $result = $this->controller->get_posts( $this->makeRequest() );

        $this->assertInstanceOf( \WP_REST_Response::class, $result );
    }

    public function test_non_public_post_type_returns_error(): void {
        $obj         = new \stdClass();
        $obj->public = false;
        Functions\when( 'get_post_type_object' )->justReturn( $obj );
        Functions\when( 'current_user_can' )->justReturn( true );

        $result = $this->controller->get_posts( $this->makeRequest( array( 'post_type' => 'revision' ) ) );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 400, $result->get_error_data()['status'] );
    }

    public function test_nonexistent_post_type_returns_error(): void {
        Functions\when( 'get_post_type_object' )->justReturn( null );
        Functions\when( 'current_user_can' )->justReturn( true );

        $result = $this->controller->get_posts( $this->makeRequest( array( 'post_type' => 'nonexistent' ) ) );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 400, $result->get_error_data()['status'] );
    }

    // -----------------------------------------------------------------------
    // status coercion
    // -----------------------------------------------------------------------

    public function test_draft_status_coerced_to_publish_for_non_admin(): void {
        $obj         = new \stdClass();
        $obj->public = true;
        Functions\when( 'get_post_type_object' )->justReturn( $obj );
        Functions\when( 'current_user_can' )->justReturn( false ); // not admin
        Functions\when( 'get_transient' )->justReturn( false );
        Functions\when( 'set_transient' )->justReturn( true );

        $queriedStatus = null;
        Functions\when( 'get_posts' )->alias( function( $args ) use ( &$queriedStatus ) {
            $queriedStatus = $args['post_status'];
            return array();
        } );

        $this->controller->get_posts( $this->makeRequest( array( 'status' => 'draft' ) ) );

        $this->assertSame( 'publish', $queriedStatus );
    }

    public function test_draft_status_preserved_for_admin(): void {
        $obj         = new \stdClass();
        $obj->public = true;
        Functions\when( 'get_post_type_object' )->justReturn( $obj );
        Functions\when( 'current_user_can' )->justReturn( true ); // admin
        Functions\when( 'get_transient' )->justReturn( false );
        Functions\when( 'set_transient' )->justReturn( true );

        $queriedStatus = null;
        Functions\when( 'get_posts' )->alias( function( $args ) use ( &$queriedStatus ) {
            $queriedStatus = $args['post_status'];
            return array();
        } );

        $this->controller->get_posts( $this->makeRequest( array( 'status' => 'draft' ) ) );

        $this->assertSame( 'draft', $queriedStatus );
    }

    // -----------------------------------------------------------------------
    // caching
    // -----------------------------------------------------------------------

    public function test_cache_hit_does_not_call_get_posts(): void {
        $obj         = new \stdClass();
        $obj->public = true;
        Functions\when( 'get_post_type_object' )->justReturn( $obj );
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'get_transient' )->justReturn( array( array( 'id' => 1, 'title' => 'Cached' ) ) );

        $getPostsCalled = false;
        Functions\when( 'get_posts' )->alias( function() use ( &$getPostsCalled ) {
            $getPostsCalled = true;
            return array();
        } );

        $result = $this->controller->get_posts( $this->makeRequest() );

        $this->assertFalse( $getPostsCalled, 'get_posts should NOT be called on cache hit' );
        $data = $result->get_data();
        $this->assertSame( 'Cached', $data[0]['title'] );
    }

    public function test_cache_miss_calls_get_posts_and_stores_result(): void {
        $obj         = new \stdClass();
        $obj->public = true;
        Functions\when( 'get_post_type_object' )->justReturn( $obj );
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'get_transient' )->justReturn( false );

        $setTransientCalled = false;
        Functions\when( 'set_transient' )->alias( function() use ( &$setTransientCalled ) {
            $setTransientCalled = true;
        } );

        $fakePost              = new \stdClass();
        $fakePost->ID          = 1;
        $fakePost->post_title  = 'Hello World';
        $fakePost->post_status = 'publish';
        $fakePost->post_date   = '2024-01-01 00:00:00';
        $fakePost->post_author = 1;
        Functions\when( 'get_posts' )->justReturn( array( $fakePost ) );
        Functions\when( 'get_permalink' )->justReturn( 'https://example.com/hello' );

        $result = $this->controller->get_posts( $this->makeRequest() );

        $this->assertTrue( $setTransientCalled );
        $data = $result->get_data();
        $this->assertCount( 1, $data );
        $this->assertSame( 'Hello World', $data[0]['title'] );
    }

    public function test_empty_get_posts_result_returns_empty_array(): void {
        $obj         = new \stdClass();
        $obj->public = true;
        Functions\when( 'get_post_type_object' )->justReturn( $obj );
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'get_transient' )->justReturn( false );
        Functions\when( 'set_transient' )->justReturn( true );
        Functions\when( 'get_posts' )->justReturn( array() );

        $result = $this->controller->get_posts( $this->makeRequest() );

        $this->assertSame( array(), $result->get_data() );
    }
}
