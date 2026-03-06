<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;
use Brain\Monkey\Functions;

require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';
require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-stats-service.php';
require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-stats-controller.php';

class StatsControllerTest extends CDWTestCase {

    private \CDW_Stats_Controller $controller;
    private $mockService;

    protected function setUp(): void {
        parent::setUp();

        $this->controller = new \CDW_Stats_Controller();
        $this->mockService = \Mockery::mock( 'CDW_Stats_Service' );

        $ref  = new \ReflectionClass( $this->controller );
        $prop = $ref->getProperty( 'stats_service' );
        $prop->setAccessible( true );
        $prop->setValue( $this->controller, $this->mockService );

        Functions\when( 'rest_ensure_response' )->alias( function( $data ) {
            return new \WP_REST_Response( $data, 200 );
        } );
    }

    // -----------------------------------------------------------------------
    // get_stats()
    // -----------------------------------------------------------------------

    public function test_get_stats_delegates_to_stats_service(): void {
        $stats = array( 'posts' => 10, 'pages' => 2, 'comments' => 5, 'users' => 3 );
        $this->mockService->shouldReceive( 'get_stats' )->once()->andReturn( $stats );

        $result = $this->controller->get_stats();

        $this->assertInstanceOf( \WP_REST_Response::class, $result );
        $this->assertSame( $stats, $result->get_data() );
    }

    public function test_get_stats_returns_200_status(): void {
        $this->mockService->shouldReceive( 'get_stats' )->once()->andReturn( array() );

        $result = $this->controller->get_stats();

        $this->assertSame( 200, $result->get_status() );
    }

    public function test_get_stats_passes_through_all_service_keys(): void {
        $stats = array(
            'posts'      => 15,
            'pages'      => 4,
            'comments'   => 22,
            'users'      => 6,
            'media'      => 30,
            'categories' => 8,
            'tags'       => 12,
            'plugins'    => 5,
            'themes'     => 3,
        );
        $this->mockService->shouldReceive( 'get_stats' )->once()->andReturn( $stats );

        $data = $this->controller->get_stats()->get_data();

        foreach ( array_keys( $stats ) as $key ) {
            $this->assertArrayHasKey( $key, $data );
            $this->assertSame( $stats[ $key ], $data[ $key ] );
        }
    }

    public function test_get_stats_passes_through_products_key_when_present(): void {
        $stats = array( 'posts' => 1, 'products' => 50 );
        $this->mockService->shouldReceive( 'get_stats' )->once()->andReturn( $stats );

        $data = $this->controller->get_stats()->get_data();

        $this->assertArrayHasKey( 'products', $data );
        $this->assertSame( 50, $data['products'] );
    }
}
