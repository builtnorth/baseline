<?php
/**
 * Tests for Security Headers
 *
 * @package Siratik\Baseline\Tests\Unit\Security
 */

namespace Siratik\Baseline\Tests\Unit\Security;

use Siratik\Baseline\Security\Headers;
use BuiltNorth\WPMockStandIns\WPMockTestCase;
use WP_Mock;

/**
 * Headers test case
 */
class HeadersTest extends WPMockTestCase {

	/**
	 * Test constructor registers hooks
	 * SKIPPED: WP_Mock expectActionAdded not working with namespace functions
	 */
	public function skip_test_constructor_registers_hooks() {
		$this->markTestSkipped('WP_Mock expectActionAdded not working properly with namespace functions');
	}

	/**
	 * Test default security headers
	 */
	public function test_default_security_headers() {
		WP_Mock::userFunction( 'apply_filters' )
			->with( 'siratik_baseline_security_headers', \Mockery::type( 'array' ) )
			->andReturnUsing( function( $filter, $headers ) {
				// Check that default headers are present
				$this->assertArrayHasKey( 'X-Content-Type-Options', $headers );
				$this->assertArrayHasKey( 'X-Frame-Options', $headers );
				$this->assertArrayHasKey( 'Referrer-Policy', $headers );
				$this->assertArrayHasKey( 'Permissions-Policy', $headers );
				return $headers;
			});

		WP_Mock::userFunction( 'apply_filters' )
			->with( 'siratik_baseline_security_headers_csp', \Mockery::type( 'array' ) )
			->andReturnUsing( function( $filter, $csp ) {
				return $csp;
			});

		new Headers();
		
		$this->assertConditionsMet();
	}

	/**
	 * Test CSP directives structure
	 */
	public function test_csp_directives_structure() {
		WP_Mock::userFunction( 'apply_filters' )
			->with( 'siratik_baseline_security_headers', \Mockery::type( 'array' ) )
			->andReturnUsing( function( $filter, $headers ) {
				return $headers;
			});

		WP_Mock::userFunction( 'apply_filters' )
			->with( 'siratik_baseline_security_headers_csp', \Mockery::type( 'array' ) )
			->andReturnUsing( function( $filter, $csp ) {
				// Check CSP structure
				$this->assertIsArray( $csp );
				$this->assertArrayHasKey( 'default-src', $csp );
				$this->assertArrayHasKey( 'script-src', $csp );
				$this->assertArrayHasKey( 'style-src', $csp );
				return $csp;
			});

		new Headers();
		
		$this->assertConditionsMet();
	}

	/**
	 * Test headers can be filtered
	 */
	public function test_headers_can_be_filtered() {
		$custom_headers = [
			'X-Custom-Header' => 'custom-value',
		];

		WP_Mock::userFunction( 'apply_filters' )
			->with( 'siratik_baseline_security_headers', \Mockery::type( 'array' ) )
			->andReturn( $custom_headers );

		WP_Mock::userFunction( 'apply_filters' )
			->with( 'siratik_baseline_security_headers_csp', \Mockery::type( 'array' ) )
			->andReturn( [] );

		new Headers();
		
		$this->assertConditionsMet();
	}

	/**
	 * Test CSP can be filtered
	 */
	public function test_csp_can_be_filtered() {
		$custom_csp = [
			'default-src' => "'self' custom.com",
		];

		WP_Mock::userFunction( 'apply_filters' )
			->with( 'siratik_baseline_security_headers', \Mockery::type( 'array' ) )
			->andReturnUsing( function( $filter, $headers ) {
				return $headers;
			});

		WP_Mock::userFunction( 'apply_filters' )
			->with( 'siratik_baseline_security_headers_csp', \Mockery::type( 'array' ) )
			->andReturn( $custom_csp );

		new Headers();
		
		$this->assertConditionsMet();
	}

	/**
	 * HSTS covers subdomains only when the site opts in.
	 */
	public function test_hsts_include_subdomains_is_opt_in() {
		$method = new \ReflectionMethod( Headers::class, 'hsts_value' );
		$method->setAccessible( true );
		$headers = new Headers();

		$this->assertSame( 'max-age=31536000', $method->invoke( $headers, 'max-age=31536000' ) );

		$GLOBALS['siratik_baseline_test_filters'] = [ 'siratik_baseline_hsts_include_subdomains' => true ];
		$this->assertSame( 'max-age=31536000; includeSubDomains', $method->invoke( $headers, 'max-age=31536000' ) );
		$this->assertSame( 'max-age=1; includeSubDomains', $method->invoke( $headers, 'max-age=1; includeSubDomains' ) );
		unset( $GLOBALS['siratik_baseline_test_filters'] );
	}

	/**
	 * The legacy XSS auditor is turned off, not put in block mode.
	 */
	public function test_default_headers_turn_off_the_xss_auditor() {
		$headers = ( new \ReflectionClassConstant( Headers::class, 'SECURITY_HEADERS' ) )->getValue();

		$this->assertSame( '0', $headers['X-XSS-Protection'] );
		$this->assertSame( 'max-age=31536000', $headers['Strict-Transport-Security'] );
	}
}
