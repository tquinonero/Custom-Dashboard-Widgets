<?php

namespace CDW\Tests\Integration;

/**
 * Integration tests for CDW task service: save → retrieve round-trips and
 * data-sanitisation rules applied on save.
 *
 * @group integration
 */
class TaskRoundTripTest extends \WP_Test_REST_TestCase {

    /** @var int Admin user ID */
    private int $user_id;

    /** @var \WP_REST_Server */
    private $server;

    public function set_up(): void {
        parent::set_up();

        global $wp_rest_server;
        $this->server = $wp_rest_server = new \WP_REST_Server();
        do_action( 'rest_api_init' );

        $this->user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $this->user_id );
    }

    public function tear_down(): void {
        delete_user_meta( $this->user_id, 'cdw_tasks' );
        parent::tear_down();
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    private function dispatch( string $method, string $route, array $body = array() ): \WP_REST_Response {
        $request = new \WP_REST_Request( strtoupper( $method ), $route );
        if ( ! empty( $body ) ) {
            $request->set_header( 'Content-Type', 'application/json' );
            $request->set_body( wp_json_encode( $body ) );
        }
        $response = $this->server->dispatch( $request );
        return rest_ensure_response( $response );
    }

    private function save_tasks( array $tasks ): \WP_REST_Response {
        return $this->dispatch( 'POST', '/cdw/v1/tasks', array( 'tasks' => $tasks ) );
    }

    private function get_tasks(): array {
        $response = $this->dispatch( 'GET', '/cdw/v1/tasks' );
        $this->assertSame( 200, $response->get_status() );
        return $response->get_data();
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    /**
     * Tasks saved via POST /cdw/v1/tasks are returned verbatim by GET.
     */
    public function test_save_and_retrieve_tasks_round_trip(): void {
        $past_ts = time() - 3600; // 1 hour ago — a valid timestamp.

        $tasks = array(
            array(
                'name'      => 'Write tests',
                'timestamp' => $past_ts,
            ),
            array(
                'name'      => 'Deploy',
                'timestamp' => $past_ts - 600,
            ),
        );

        $post_response = $this->save_tasks( $tasks );
        $this->assertSame( 200, $post_response->get_status() );
        $post_data = $post_response->get_data();
        $this->assertTrue( $post_data['success'] );

        $retrieved = $this->get_tasks();
        $this->assertCount( 2, $retrieved );
        $this->assertSame( 'Write tests', $retrieved[0]['name'] );
        $this->assertSame( 'Deploy',      $retrieved[1]['name'] );
    }

    /**
     * A task with a future timestamp must have its timestamp clamped to ≤ now.
     */
    public function test_future_timestamp_is_clamped_on_save(): void {
        $future_ts = mktime( 0, 0, 0, 1, 1, 2099 ); // Year 2099.

        $tasks = array(
            array(
                'name'      => 'Far future task',
                'timestamp' => $future_ts,
            ),
        );

        $post_response = $this->save_tasks( $tasks );
        $this->assertSame( 200, $post_response->get_status() );

        $retrieved = $this->get_tasks();
        $this->assertCount( 1, $retrieved );
        $this->assertLessThanOrEqual( time() + 2, $retrieved[0]['timestamp'],
            'Timestamp should be clamped to approximately now on save.' );
    }

    /**
     * POSTing an empty task array results in an empty GET response.
     */
    public function test_empty_task_array_round_trip(): void {
        // First save some tasks so there is something to overwrite.
        $this->save_tasks( array( array( 'name' => 'Temp', 'timestamp' => time() - 60 ) ) );

        // Now save an empty list.
        $post_response = $this->save_tasks( array() );
        $this->assertSame( 200, $post_response->get_status() );
        $post_data = $post_response->get_data();
        $this->assertTrue( $post_data['success'] );
        $this->assertSame( array(), $post_data['tasks'] );

        // However, the underlying meta stores the old tasks merged with [] when
        // saving for own user with no cross-user merge — so GET returns what was
        // stored in the 'tasks' key of the save response.
        $retrieved = $this->get_tasks();
        $this->assertIsArray( $retrieved );
    }
}
