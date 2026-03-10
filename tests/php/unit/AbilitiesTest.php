<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;
use Brain\Monkey\Functions;

require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';
require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-cli-service.php';
require_once CDW_PLUGIN_DIR . 'includes/class-cdw-abilities.php';

/**
 * Tests for CDW_Abilities.
 *
 * @package CDW\Tests\Unit
 */
class AbilitiesTest extends CDWTestCase {

	// -----------------------------------------------------------------------
	// 1. Guard — no-op when wp_register_ability does not exist
	// -----------------------------------------------------------------------

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_register_is_noop_when_wp_register_ability_does_not_exist(): void {
		// In this isolated process, wp_register_ability has never been defined.
		$this->assertFalse( function_exists( 'wp_register_ability' ) );

		// register() has no reason to call add_action — if it did,
		// Brain Monkey would throw for the unexpected call.
		\CDW_Abilities::register();

		// Reaching here without error confirms the guard fired correctly.
		$this->assertTrue( true );
	}

	// -----------------------------------------------------------------------
	// 2. register_category() registers the expected category name
	// -----------------------------------------------------------------------

	public function test_register_category_uses_cdw_admin_tools_name(): void {
		$categoryName = null;

		Functions\when( 'wp_register_ability_category' )->alias(
			function ( $name ) use ( &$categoryName ) {
				$categoryName = $name;
			}
		);
		Functions\when( '__' )->returnArg();

		\CDW_Abilities::register_category();

		$this->assertSame( 'cdw-admin-tools', $categoryName );
	}

	// -----------------------------------------------------------------------
	// 3. register_abilities() calls wp_register_ability exactly 70 times
	// (59 in $abilities array + 11 inline: block-patterns-get, post-set-content,
	// post-get-content, post-append-content, build-page, custom-patterns-list,
	// custom-patterns-get, role-list, role-create, role-update, role-delete)
	// -----------------------------------------------------------------------

	public function test_register_abilities_registers_exactly_70_abilities(): void {
		$count = 0;

		Functions\when( 'wp_register_ability' )->alias( function () use ( &$count ) {
			$count++;
		} );
		Functions\when( '__' )->returnArg();

		\CDW_Abilities::register_abilities();

		$this->assertSame( 70, $count );
	}

	// -----------------------------------------------------------------------
	// 4. permission_callback returns false for a user without manage_options
	// -----------------------------------------------------------------------

	public function test_permission_callback_returns_false_for_non_admin(): void {
		$callbacks = array();

		Functions\when( 'wp_register_ability' )->alias(
			function ( $name, $args ) use ( &$callbacks ) {
				$callbacks[] = $args['permission_callback'];
			}
		);
		Functions\when( '__' )->returnArg();
		Functions\when( 'current_user_can' )->justReturn( false );

		\CDW_Abilities::register_abilities();

		$this->assertNotEmpty( $callbacks, 'Expected at least one ability to be registered' );
		foreach ( $callbacks as $cb ) {
			$this->assertFalse( $cb(), 'permission_callback must return false for a user lacking manage_options' );
		}
	}

	// -----------------------------------------------------------------------
	// 5. cdw_mcp_public = false → wp_register_ability_args filter NOT added
	// -----------------------------------------------------------------------

	public function test_mcp_public_false_does_not_add_ability_args_filter(): void {
		Functions\when( 'wp_register_ability' )->justReturn( true );
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( '__' )->returnArg();

		$filterHooks = array();
		Functions\when( 'add_filter' )->alias( function ( $hook ) use ( &$filterHooks ) {
			$filterHooks[] = $hook;
		} );
		Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
			if ( 'cdw_mcp_public' === $key ) {
				return false;
			}
			return $default;
		} );

		\CDW_Abilities::register();

		$this->assertNotContains(
			'wp_register_ability_args',
			$filterHooks,
			'wp_register_ability_args filter must not be registered when mcp_public is false'
		);
	}

	// -----------------------------------------------------------------------
	// 6. cdw_mcp_public = true → filter IS added; cdw/* gets meta.mcp.public;
	//    non-cdw/* abilities are unchanged
	// -----------------------------------------------------------------------

	public function test_mcp_public_true_registers_filter_and_marks_cdw_abilities(): void {
		Functions\when( 'wp_register_ability' )->justReturn( true );
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( '__' )->returnArg();

		$capturedCb = null;
		Functions\when( 'add_filter' )->alias(
			function ( $hook, $cb ) use ( &$capturedCb ) {
				if ( 'wp_register_ability_args' === $hook ) {
					$capturedCb = $cb;
				}
			}
		);
		Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
			if ( 'cdw_mcp_public' === $key ) {
				return true;
			}
			return $default;
		} );

		\CDW_Abilities::register();

		$this->assertNotNull( $capturedCb, 'wp_register_ability_args filter callback was not captured' );

		// A cdw/* ability should receive meta.mcp.public = true.
		$cdwArgs = $capturedCb( array(), 'cdw/plugin-list' );
		$this->assertTrue(
			$cdwArgs['meta']['mcp']['public'],
			'cdw/* ability must have meta.mcp.public = true when mcp_public is enabled'
		);

		// A non-cdw/* ability must not be modified.
		$otherArgs = $capturedCb( array( 'meta' => array() ), 'core/my-ability' );
		$this->assertArrayNotHasKey(
			'mcp',
			$otherArgs['meta'],
			'non-cdw/* ability must not receive meta.mcp flags'
		);
	}
}
