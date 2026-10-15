<?php
/**
 * Tests for SVG Sanitization
 *
 * @package Siratik\Baseline\Tests\Unit\MimeTypes\SVG
 */

namespace Siratik\Baseline\Tests\Unit\MimeTypes\SVG;

use Siratik\Baseline\MimeTypes\SVG\Sanitize;
use BuiltNorth\WPMockStandIns\WPMockTestCase;
use WP_Mock;

/**
 * Sanitize test case
 */
class SanitizeTest extends WPMockTestCase {

	/**
	 * Test constructor registers hooks
	 * REMOVED: Testing non-existent methods
	 */
	public function skip_test_constructor_registers_hooks() {
		$this->markTestSkipped('Test refers to non-existent fix_mime_type method');
	}

	/**
	 * Test SVG mime type is fixed
	 * REMOVED: Method doesn't exist
	 */
	public function skip_test_fix_mime_type_for_svg() {
		$this->markTestSkipped('fix_mime_type method does not exist in Sanitize class');
		$sanitize = new Sanitize();
		
		$file = '/path/to/file.svg';
		$filename = 'file.svg';
		
		// Mock wp_check_filetype_and_ext behavior
		$data = [
			'ext' => false,
			'type' => false,
			'proper_filename' => false,
		];
		
		$mimes = [ 'svg' => 'image/svg+xml' ];
		
		WP_Mock::userFunction( 'wp_check_filetype' )
			->with( $filename, $mimes )
			->andReturn( [
				'ext' => 'svg',
				'type' => 'image/svg+xml',
			] );
		
		$result = $sanitize->fix_mime_type( $data, $file, $filename, $mimes );
		
		$this->assertEquals( 'svg', $result['ext'] );
		$this->assertEquals( 'image/svg+xml', $result['type'] );
	}

	/**
	 * Test non-SVG files are not modified
	 * REMOVED: Method doesn't exist
	 */
	public function skip_test_non_svg_files_not_modified() {
		$this->markTestSkipped('fix_mime_type method does not exist in Sanitize class');
		$sanitize = new Sanitize();
		
		$file = '/path/to/file.jpg';
		$filename = 'file.jpg';
		
		$data = [
			'ext' => 'jpg',
			'type' => 'image/jpeg',
			'proper_filename' => false,
		];
		
		$mimes = [ 'jpg' => 'image/jpeg' ];
		
		$result = $sanitize->fix_mime_type( $data, $file, $filename, $mimes );
		
		$this->assertEquals( $data, $result );
	}

	/**
	 * Test SVG sanitization for non-SVG files
	 */
	public function test_sanitize_svg_ignores_non_svg() {
		// Create a mock Sanitizer instance
		if (!class_exists('\enshrined\svgSanitize\Sanitizer')) {
			$this->markTestSkipped('SVG Sanitizer library not available');
		}
		$sanitize = new Sanitize();
		
		$file = [
			'name' => 'test.jpg',
			'type' => 'image/jpeg',
			'tmp_name' => '/tmp/test.jpg',
			'error' => 0,
			'size' => 1024,
		];
		
		$result = $sanitize->sanitize_svg( $file );
		
		$this->assertEquals( $file, $result );
	}

	/**
	 * Test SVG upload is denied for a user lacking the required capability.
	 */
	public function test_sanitize_svg_denies_without_capability() {
		if (!class_exists('\enshrined\svgSanitize\Sanitizer')) {
			$this->markTestSkipped('SVG Sanitizer library not available');
		}
		$sanitize = new Sanitize();

		$file = [
			'name'     => 'test.svg',
			'type'     => 'image/svg+xml',
			'tmp_name' => '/tmp/test.svg',
			'error'    => 0,
			'size'     => 1024,
		];

		WP_Mock::onFilter( 'siratik_baseline_mime_upload_capability' )
			->with( 'manage_options', 'svg' )
			->reply( 'manage_options' );

		WP_Mock::userFunction( 'current_user_can' )
			->with( 'manage_options' )
			->andReturn( false );

		WP_Mock::userFunction( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		$result = $sanitize->sanitize_svg( $file );

		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test SVG sanitization error handling
	 * SKIPPED: Cannot mock PHP internal functions
	 */
	public function skip_test_sanitize_svg_with_invalid_file() {
		$this->markTestSkipped('Cannot mock PHP internal file_get_contents function');
		if (!class_exists('\enshrined\svgSanitize\Sanitizer')) {
			$this->markTestSkipped('SVG Sanitizer library not available');
		}
		$sanitize = new Sanitize();
		
		$file = [
			'name' => 'test.svg',
			'type' => 'image/svg+xml',
			'tmp_name' => '/tmp/nonexistent.svg',
			'error' => 0,
			'size' => 1024,
		];
		
		WP_Mock::userFunction( 'file_get_contents' )
			->with( $file['tmp_name'] )
			->andReturn( false );
		
		WP_Mock::userFunction( '__' )
			->with( 'SVG file could not be read for sanitization.', 'siratik-baseline' )
			->andReturn( 'SVG file could not be read for sanitization.' );
		
		$result = $sanitize->sanitize_svg( $file );
		
		$this->assertArrayHasKey( 'error', $result );
		$this->assertEquals( 'SVG file could not be read for sanitization.', $result['error'] );
	}

	/**
	 * Test valid SVG is sanitized
	 * SKIPPED: Cannot mock PHP internal functions
	 */
	public function skip_test_valid_svg_is_sanitized() {
		$this->markTestSkipped('Cannot mock PHP internal file_get_contents function');
		if (!class_exists('\enshrined\svgSanitize\Sanitizer')) {
			$this->markTestSkipped('SVG Sanitizer library not available');
		}
		$sanitize = new Sanitize();
		
		$svg_content = '<svg xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="40"/></svg>';
		
		$file = [
			'name' => 'test.svg',
			'type' => 'image/svg+xml',
			'tmp_name' => '/tmp/test.svg',
			'error' => 0,
			'size' => strlen( $svg_content ),
		];
		
		WP_Mock::userFunction( 'file_get_contents' )
			->with( $file['tmp_name'] )
			->andReturn( $svg_content );
		
		WP_Mock::userFunction( 'file_put_contents' )
			->with( $file['tmp_name'], \Mockery::type( 'string' ) )
			->andReturn( strlen( $svg_content ) );
		
		$result = $sanitize->sanitize_svg( $file );
		
		$this->assertEquals( $file, $result );
	}

	/**
	 * Both upload paths are sanitized: wp_handle_sideload() (REST raw-body
	 * uploads) fires its own prefilter, not wp_handle_upload_prefilter.
	 */
	public function test_init_hooks_upload_and_sideload_prefilters() {
		$GLOBALS['siratik_baseline_test_hooks'] = [];
		$sanitize = new Sanitize();

		$sanitize->init();

		$hooks = $GLOBALS['siratik_baseline_test_hooks'];
		$this->assertContains( [ 'filter', 'wp_handle_upload_prefilter', [ $sanitize, 'sanitize_svg' ] ], $hooks );
		$this->assertContains( [ 'filter', 'wp_handle_sideload_prefilter', [ $sanitize, 'sanitize_svg' ] ], $hooks );
	}

	/**
	 * A sideloaded file array (name + tmp_name, no size) has its script stripped.
	 */
	public function test_sanitize_svg_cleans_a_sideloaded_file() {
		if (!class_exists('\enshrined\svgSanitize\Sanitizer')) {
			$this->markTestSkipped('SVG Sanitizer library not available');
		}

		$tmp_name = tempnam( sys_get_temp_dir(), 'svg' );
		file_put_contents( $tmp_name, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><circle r="4"/></svg>' );

		WP_Mock::userFunction( 'current_user_can' )->andReturn( true );

		$result = ( new Sanitize() )->sanitize_svg( [
			'name'     => 'payload.svg',
			'tmp_name' => $tmp_name,
		] );

		$clean = (string) file_get_contents( $tmp_name );
		unlink( $tmp_name );

		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertStringNotContainsString( '<script', $clean );
		$this->assertStringContainsString( '<circle', $clean );
	}
}
