<?php

namespace CDW\Tests\Integration;

/**
 * Verifies that all expected CDW REST API routes are registered.
 *
 * @group integration
 */
class RestRoutesTest extends \WP_UnitTestCase {

    /** @var array */
    private $routes;

    public function set_up(): void {
        parent::set_up();

        // Fire rest_api_init so routes are registered.
        do_action( 'rest_api_init' );
        $this->routes = rest_get_server()->get_routes();
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    /**
     * Assert a route path exists with the given HTTP method registered.
     *
     * @param string $path   e.g. '/cdw/v1/stats'
     * @param string $method e.g. 'GET', 'POST', 'DELETE'
     */
    private function assertRouteExists( string $path, string $method ): void {
        $this->assertArrayHasKey(
            $path,
            $this->routes,
            "Route '{$path}' is not registered."
        );

        $methods_found = array();
        foreach ( $this->routes[ $path ] as $handler ) {
            foreach ( (array) $handler['methods'] as $m => $enabled ) {
                if ( $enabled ) {
                    $methods_found[] = strtoupper( $m );
                }
            }
        }

        $this->assertContains(
            strtoupper( $method ),
            $methods_found,
            "Route '{$path}' does not support method '{$method}'."
        );
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    public function test_stats_get_route_registered(): void {
        $this->assertRouteExists( '/cdw/v1/stats', 'GET' );
    }

    public function test_posts_get_route_registered(): void {
        $this->assertRouteExists( '/cdw/v1/posts', 'GET' );
    }

    public function test_media_get_route_registered(): void {
        $this->assertRouteExists( '/cdw/v1/media', 'GET' );
    }

    public function test_users_get_route_registered(): void {
        $this->assertRouteExists( '/cdw/v1/users', 'GET' );
    }

    public function test_updates_get_route_registered(): void {
        $this->assertRouteExists( '/cdw/v1/updates', 'GET' );
    }

    public function test_tasks_get_route_registered(): void {
        $this->assertRouteExists( '/cdw/v1/tasks', 'GET' );
    }

    public function test_tasks_post_route_registered(): void {
        $this->assertRouteExists( '/cdw/v1/tasks', 'POST' );
    }

    public function test_settings_get_route_registered(): void {
        $this->assertRouteExists( '/cdw/v1/settings', 'GET' );
    }

    public function test_settings_post_route_registered(): void {
        $this->assertRouteExists( '/cdw/v1/settings', 'POST' );
    }

    public function test_cli_history_get_route_registered(): void {
        $this->assertRouteExists( '/cdw/v1/cli/history', 'GET' );
    }

    public function test_cli_history_delete_route_registered(): void {
        $this->assertRouteExists( '/cdw/v1/cli/history', 'DELETE' );
    }

    public function test_cli_commands_get_route_registered(): void {
        $this->assertRouteExists( '/cdw/v1/cli/commands', 'GET' );
    }

    public function test_cli_execute_post_route_registered(): void {
        $this->assertRouteExists( '/cdw/v1/cli/execute', 'POST' );
    }

    // -----------------------------------------------------------------------
    // AI routes
    // -----------------------------------------------------------------------

    public function test_ai_settings_get_route_registered(): void {
        $this->assertRouteExists( '/cdw/v1/ai/settings', 'GET' );
    }

    public function test_ai_settings_post_route_registered(): void {
        $this->assertRouteExists( '/cdw/v1/ai/settings', 'POST' );
    }

    public function test_ai_chat_post_route_registered(): void {
        $this->assertRouteExists( '/cdw/v1/ai/chat', 'POST' );
    }

    public function test_ai_execute_post_route_registered(): void {
        $this->assertRouteExists( '/cdw/v1/ai/execute', 'POST' );
    }

    public function test_ai_providers_get_route_registered(): void {
        $this->assertRouteExists( '/cdw/v1/ai/providers', 'GET' );
    }

    public function test_ai_test_post_route_registered(): void {
        $this->assertRouteExists( '/cdw/v1/ai/test', 'POST' );
    }

    public function test_ai_usage_get_route_registered(): void {
        $this->assertRouteExists( '/cdw/v1/ai/usage', 'GET' );
    }
}
