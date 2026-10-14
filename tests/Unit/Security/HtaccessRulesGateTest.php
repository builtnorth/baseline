<?php
/**
 * Tests for the siratik_baseline_write_htaccess_rules gate.
 *
 * @package Siratik\Baseline\Tests\Unit\Security
 */

declare(strict_types=1);

namespace Siratik\Baseline\Tests\Unit\Security;

use BuiltNorth\WPMockStandIns\WPMockTestCase;
use Siratik\Baseline\Security\UploadsExecution;
use Siratik\Baseline\Security\XMLRPC;
use WP_Mock;

/**
 * Both `.htaccess` rule builders honour the gate each time they run.
 */
class HtaccessRulesGateTest extends WPMockTestCase {

	public function tearDown(): void {
		unset( $GLOBALS['siratik_baseline_test_filters'] );
		parent::tearDown();
	}

	public function test_xmlrpc_rule_is_left_out_when_gate_is_off(): void {
		$GLOBALS['siratik_baseline_test_filters'] = [ 'siratik_baseline_write_htaccess_rules' => false ];

		$this->assertSame( "# BEGIN WordPress\n", ( new XMLRPC() )->block_xmlrpc_requests( "# BEGIN WordPress\n" ) );
	}

	public function test_xmlrpc_rule_is_added_when_gate_is_on(): void {
		$rules = ( new XMLRPC() )->block_xmlrpc_requests( 'core-rules' );

		$this->assertStringContainsString( '# Block XML-RPC requests', $rules );
		$this->assertStringEndsWith( 'core-rules', $rules );
	}

	public function test_uploads_rule_is_left_out_when_gate_is_off(): void {
		$GLOBALS['siratik_baseline_test_filters'] = [ 'siratik_baseline_write_htaccess_rules' => false ];

		$this->assertSame( 'core-rules', ( new UploadsExecution() )->block_uploads_php_execution( 'core-rules' ) );
	}

	public function test_uploads_rule_is_added_when_gate_is_on(): void {
		WP_Mock::userFunction( 'wp_get_upload_dir' )->andReturn( [ 'basedir' => ABSPATH . 'wp-content/uploads' ] );
		WP_Mock::userFunction( 'wp_normalize_path' )->andReturnArg( 0 );

		$rules = ( new UploadsExecution() )->block_uploads_php_execution( 'core-rules' );

		$this->assertStringContainsString( '# Block PHP execution in uploads', $rules );
		$this->assertStringContainsString( 'RewriteRule ^wp\-content/uploads/', $rules );
	}
}
