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

/**
 * XMLRPC test case
 */
class XMLRPCTest extends WPMockTestCase {

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
}
