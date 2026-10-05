<?php
/**
 * Tests for the XML-RPC switch-off.
 *
 * @package Navas\Baseline\Tests\Unit\Security
 */

namespace Navas\Baseline\Tests\Unit\Security;

use BuiltNorth\WPMockStandIns\WPMockTestCase;
use Navas\Baseline\Security\DisabledXmlrpcServer;
use Navas\Baseline\Security\XMLRPC;

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
}
