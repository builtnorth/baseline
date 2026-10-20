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
		$this->mock_urls( 'https://example.com/', 'https://example.com/wp-content/uploads' );

		$rules = ( new UploadsExecution() )->block_uploads_php_execution( 'core-rules' );

		$this->assertStringContainsString( '# Block PHP execution in uploads', $rules );
		$this->assertStringContainsString( 'RewriteRule ^wp\-content/uploads/', $rules );
	}

	/**
	 * WordPress in its own directory (core in /app/, uploads beside it): the
	 * rule is still written, relative to the home URL.
	 */
	public function test_uploads_rule_is_relative_to_home_not_core(): void {
		$this->mock_urls( 'https://example.com/', 'https://example.com/wp-content/uploads' );

		$rules = ( new UploadsExecution() )->block_uploads_php_execution( '' );

		$this->assertStringContainsString( 'RewriteRule ^wp\-content/uploads/.*', $rules );
	}

	public function test_uploads_rule_drops_the_home_subdirectory(): void {
		$this->mock_urls( 'https://example.com/blog/', 'https://example.com/blog/wp-content/uploads' );

		$rules = ( new UploadsExecution() )->block_uploads_php_execution( '' );

		$this->assertStringContainsString( 'RewriteRule ^wp\-content/uploads/.*', $rules );
	}

	/**
	 * Uploads on a host alias (www vs apex) are still covered.
	 */
	public function test_uploads_rule_is_added_for_a_host_alias(): void {
		$this->mock_urls( 'https://www.example.com/', 'https://example.com/wp-content/uploads' );

		$rules = ( new UploadsExecution() )->block_uploads_php_execution( '' );

		$this->assertStringContainsString( 'RewriteRule ^wp\-content/uploads/.*', $rules );
	}

	/**
	 * A space in the uploads path can't split the RewriteRule line.
	 */
	public function test_uploads_rule_escapes_whitespace(): void {
		$this->mock_urls( 'https://example.com/', 'https://example.com/my%20files/uploads' );

		$rules = ( new UploadsExecution() )->block_uploads_php_execution( '' );

		$this->assertStringContainsString( 'RewriteRule ^my\sfiles/uploads/.*', $rules );
	}

	/**
	 * The rule also matches `x.php/anything` (Apache PATH_INFO), not only
	 * paths ending in the extension.
	 */
	public function test_uploads_rule_matches_php_with_path_info(): void {
		$this->mock_urls( 'https://example.com/', 'https://example.com/wp-content/uploads' );

		$rules = ( new UploadsExecution() )->block_uploads_php_execution( '' );
		$this->assertSame( 1, preg_match( '/RewriteRule \^(\S+) - \[F,L,NC\]/', $rules, $match ) );
		$regex = '#^' . $match[1] . '#i';

		$this->assertSame( 1, preg_match( $regex, 'wp-content/uploads/2026/10/x.php' ) );
		$this->assertSame( 1, preg_match( $regex, 'wp-content/uploads/x.php/foo' ) );
		$this->assertSame( 1, preg_match( $regex, 'wp-content/uploads/x.PHP7' ) );
		$this->assertSame( 1, preg_match( $regex, 'wp-content/uploads/x.pht' ) );
		$this->assertSame( 0, preg_match( $regex, 'wp-content/uploads/x.php.jpg' ) );
		$this->assertSame( 0, preg_match( $regex, 'wp-content/uploads/photo.jpg' ) );
	}

	/**
	 * Uploads URL moved to a CDN while the files stay on disk under the site
	 * root: the rule falls back to the directory path.
	 */
	public function test_uploads_rule_falls_back_to_the_directory_for_a_cdn(): void {
		$this->mock_urls( 'https://example.com/', 'https://cdn.example.net', '/var/www/html/wp-content/uploads' );

		$rules = ( new UploadsExecution() )->block_uploads_php_execution( '' );

		$this->assertStringContainsString( 'RewriteRule ^wp\-content/uploads/.*', $rules );
	}

	/**
	 * A CDN URL with its own path is not mistaken for the local path.
	 */
	public function test_uploads_rule_ignores_a_cdn_url_path(): void {
		$this->mock_urls( 'https://example.com/', 'https://cdn.example.net/uploads', '/var/www/html/wp-content/uploads' );

		$rules = ( new UploadsExecution() )->block_uploads_php_execution( '' );

		$this->assertStringContainsString( 'RewriteRule ^wp\-content/uploads/.*', $rules );
	}

	/**
	 * A path with characters Apache's config parser treats specially (here a
	 * backslash) gets no rule rather than a broken .htaccess.
	 */
	public function test_uploads_rule_is_left_out_for_unusual_characters(): void {
		$this->mock_urls( 'https://example.com/', 'https://example.com/a%5C(b/uploads' );

		$this->assertSame( 'core-rules', ( new UploadsExecution() )->block_uploads_php_execution( 'core-rules' ) );
	}

	/**
	 * The rewrite lines only apply where mod_rewrite is loaded.
	 */
	public function test_uploads_rule_is_wrapped_in_ifmodule(): void {
		$this->mock_urls( 'https://example.com/', 'https://example.com/wp-content/uploads' );

		$rules = ( new UploadsExecution() )->block_uploads_php_execution( '' );

		$this->assertMatchesRegularExpression( '#<IfModule mod_rewrite\.c>\s*RewriteEngine On\s*RewriteRule [^\n]+\s*</IfModule>#', $rules );
	}

	/**
	 * Mock the home and uploads locations the rule is built from.
	 */
	private function mock_urls( string $home, string $baseurl, string $basedir = '/var/www/html/wp-content/uploads' ): void {
		WP_Mock::userFunction( 'wp_get_upload_dir' )->andReturn( [ 'baseurl' => $baseurl, 'basedir' => $basedir ] );
		WP_Mock::userFunction( 'get_home_path' )->andReturn( '/var/www/html/' );
		WP_Mock::userFunction( 'wp_normalize_path' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'home_url' )->andReturn( $home );
		WP_Mock::userFunction( 'wp_parse_url' )->andReturnUsing( static fn ( $url ) => parse_url( $url ) );
		WP_Mock::userFunction( 'trailingslashit' )->andReturnUsing( static fn ( $value ) => rtrim( $value, '/\\' ) . '/' );
	}
}
