<?php
/**
 * Tests for the XML-RPC switch-off.
 *
 * @package Siratik\Baseline\Tests\Unit\Security
 */

namespace Siratik\Baseline\Tests\Unit\Security;

use BuiltNorth\WPMockStandIns\WPMockTestCase;
use Siratik\Baseline\Security\DisabledXmlrpcServer;
use Siratik\Baseline\Security\XMLRPC;
use WP_Mock;

/**
 * XMLRPC test case
 */
class XMLRPCTest extends WPMockTestCase {

	public function tearDown(): void {
		unset( $GLOBALS['siratik_baseline_test_hooks'], $GLOBALS['siratik_baseline_test_filters'] );
		parent::tearDown();
	}

	/**
	 * Core's xmlrpc.php does `new $class()` on the filtered server class, so a
	 * non-class value (the old __return_false) is a fatal error on every probe.
	 */
	public function test_server_class_is_instantiable_and_serves(): void {
		$class = ( new XMLRPC() )->disabled_server_class();

		$this->assertSame( DisabledXmlrpcServer::class, $class );
		$this->assertTrue( method_exists( new $class(), 'serve_request' ) );
	}

	/**
	 * The off switch runs last, so a later filter can't turn XML-RPC back on,
	 * and xmlrpc.php is refused on init, before core answers ?rsd itself.
	 */
	public function test_init_hooks_run_last_and_refuse_rsd(): void {
		$xmlrpc = new XMLRPC();
		$GLOBALS['siratik_baseline_test_hooks'] = [];

		$xmlrpc->init();

		$hooks = $GLOBALS['siratik_baseline_test_hooks'];
		$this->assertContains( [ 'filter', 'wp_xmlrpc_server_class', [ $xmlrpc, 'disabled_server_class' ], PHP_INT_MAX ], $hooks );
		$this->assertContains( [ 'filter', 'xmlrpc_enabled', '__return_false', PHP_INT_MAX ], $hooks );
		$this->assertContains( [ 'action', 'init', [ $xmlrpc, 'refuse_xmlrpc_request' ], 0 ], $hooks );
	}

	/**
	 * Apache 2.4 denies with Require; Order/Deny only where mod_authz_core is missing.
	 */
	public function test_htaccess_block_works_on_apache_2_4(): void {
		$rules = ( new XMLRPC() )->block_xmlrpc_requests( '' );

		$this->assertStringContainsString( '<IfModule mod_authz_core.c>', $rules );
		$this->assertStringContainsString( 'Require all denied', $rules );
		$this->assertStringContainsString( '<IfModule !mod_authz_core.c>', $rules );
	}

	/**
	 * While a plugin needs XML-RPC (Jetpack), nothing is switched off and the
	 * server rules carry no deny.
	 */
	public function test_nothing_is_blocked_while_a_plugin_needs_xmlrpc(): void {
		$GLOBALS['siratik_baseline_test_filters']['siratik_baseline_xmlrpc_required'] = true;
		$GLOBALS['siratik_baseline_test_hooks']                                       = [];
		$xmlrpc = new XMLRPC();

		$xmlrpc->init();

		$this->assertFalse( XMLRPC::is_blocked() );
		$hooks = array_column( $GLOBALS['siratik_baseline_test_hooks'], 1 );
		$this->assertNotContains( 'xmlrpc_enabled', $hooks );
		$this->assertContains( 'mod_rewrite_rules', $hooks, 'Rules still rebuild, so a stale deny is removed.' );
		$this->assertSame( '# core', $xmlrpc->block_xmlrpc_requests( '# core' ) );
	}

	/**
	 * Switching the block off removes the deny from the rules.
	 */
	public function test_switched_off_rules_carry_no_deny(): void {
		$GLOBALS['siratik_baseline_test_filters']['siratik_baseline_disable_xmlrpc'] = false;

		$this->assertSame( '# core', ( new XMLRPC() )->block_xmlrpc_requests( '# core' ) );
	}

	/**
	 * The rules are rewritten (hard flush) once when the state changes.
	 */
	public function test_rules_are_rewritten_once_when_the_state_changes(): void {
		WP_Mock::userFunction( 'get_option' )->with( XMLRPC::RULES_STATE_OPTION )->andReturn( 'open' );
		WP_Mock::userFunction( 'update_option' )->once()->with( XMLRPC::RULES_STATE_OPTION, 'blocked', true );
		WP_Mock::userFunction( 'flush_rewrite_rules' )->once()->with( true );

		( new XMLRPC() )->refresh_server_rules();

		$this->assertConditionsMet();
	}

	/**
	 * No rewrite when the rules already match the state.
	 */
	public function test_rules_are_left_alone_when_the_state_is_unchanged(): void {
		WP_Mock::userFunction( 'get_option' )->with( XMLRPC::RULES_STATE_OPTION )->andReturn( 'blocked' );
		WP_Mock::userFunction( 'flush_rewrite_rules' )->never();

		( new XMLRPC() )->refresh_server_rules();

		$this->assertConditionsMet();
	}
}
