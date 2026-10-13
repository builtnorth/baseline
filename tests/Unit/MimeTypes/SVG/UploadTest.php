<?php
/**
 * Tests for SVG Upload handling
 *
 * @package Siratik\Baseline\Tests\Unit\MimeTypes\SVG
 */

namespace Siratik\Baseline\Tests\Unit\MimeTypes\SVG;

use Siratik\Baseline\MimeTypes\SVG\Upload;
use BuiltNorth\WPMockStandIns\WPMockTestCase;
use WP_Mock;

/**
 * Upload test case
 */
class UploadTest extends WPMockTestCase {

	/**
	 * Test non-SVG files are not changed.
	 */
	public function test_check_svg_filetype_ignores_non_svg() {
		$upload = new Upload();

		$data = [
			'ext'             => 'jpg',
			'type'            => 'image/jpeg',
			'proper_filename' => null,
		];

		$result = $upload->check_svg_filetype( $data, '/tmp/file.jpg', 'file.jpg', [] );

		$this->assertSame( $data, $result );
	}

	/**
	 * Test SVG filetype is corrected.
	 */
	public function test_check_svg_filetype_sets_svg_type() {
		$upload = new Upload();

		WP_Mock::userFunction( 'wp_check_filetype' )
			->with( 'icon.svg', [ 'svg' => 'image/svg+xml' ] )
			->andReturn(
				[
					'ext'  => 'svg',
					'type' => 'image/svg+xml',
				]
			);

		$result = $upload->check_svg_filetype(
			[
				'ext'             => false,
				'type'            => false,
				'proper_filename' => null,
			],
			'/tmp/icon.svg',
			'icon.svg',
			[ 'svg' => 'image/svg+xml' ]
		);

		$this->assertSame( 'svg', $result['ext'] );
		$this->assertSame( 'image/svg+xml', $result['type'] );
	}

	/**
	 * Test the mime type is withheld from a user lacking the required capability.
	 */
	public function test_add_svg_mime_type_denies_without_capability() {
		$upload = new Upload();
		$mimes  = [ 'jpg' => 'image/jpeg' ];

		WP_Mock::onFilter( 'siratik_baseline_mime_upload_capability' )
			->with( 'manage_options', 'svg' )
			->reply( 'manage_options' );

		WP_Mock::userFunction( 'current_user_can' )
			->with( 'manage_options' )
			->andReturn( false );

		$result = $upload->add_svg_mime_type( $mimes );

		$this->assertSame( $mimes, $result );
		$this->assertArrayNotHasKey( 'svg', $result );
	}

	/**
	 * Test the mime type is granted to a user with the required capability.
	 */
	public function test_add_svg_mime_type_allows_with_capability() {
		$upload = new Upload();
		$mimes  = [ 'jpg' => 'image/jpeg' ];

		WP_Mock::onFilter( 'siratik_baseline_mime_upload_capability' )
			->with( 'manage_options', 'svg' )
			->reply( 'manage_options' );

		WP_Mock::userFunction( 'current_user_can' )
			->with( 'manage_options' )
			->andReturn( true );

		$result = $upload->add_svg_mime_type( $mimes );

		$this->assertSame( 'image/svg+xml', $result['svg'] );
	}

	/**
	 * Test SVG dimensions come from numeric width and height attributes.
	 */
	public function test_get_dimensions_uses_width_and_height() {
		$svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="120px" height="40.4" viewBox="0 0 60 20" stroke-width="2"></svg>';

		$this->assertSame( [ 'width' => 120, 'height' => 40 ], Upload::get_dimensions( $svg ) );
	}

	/**
	 * Test the viewBox is used when width or height is missing or relative.
	 */
	public function test_get_dimensions_falls_back_to_view_box() {
		$this->assertSame( [ 'width' => 240, 'height' => 40 ], Upload::get_dimensions( '<svg viewBox="0 0 240 40"></svg>' ) );
		$this->assertSame( [ 'width' => 52, 'height' => 40 ], Upload::get_dimensions( '<svg width="100%" height="2em" viewBox="0,0,52,40"></svg>' ) );
	}

	/**
	 * Test no dimensions are returned when the SVG has no usable size.
	 */
	public function test_get_dimensions_returns_null_without_size() {
		$this->assertNull( Upload::get_dimensions( '<svg xmlns="http://www.w3.org/2000/svg"></svg>' ) );
		$this->assertNull( Upload::get_dimensions( '<svg viewBox="0 0 0 40"></svg>' ) );
		$this->assertNull( Upload::get_dimensions( 'not an svg' ) );
	}

	/**
	 * Test an uploaded SVG gets its dimensions added to the metadata.
	 */
	public function test_add_svg_dimensions_sets_width_and_height() {
		$file = tempnam( sys_get_temp_dir(), 'svg' );
		file_put_contents( $file, '<svg viewBox="0 0 88 40"></svg>' );

		WP_Mock::userFunction( 'get_post_mime_type' )->with( 12 )->andReturn( 'image/svg+xml' );
		WP_Mock::userFunction( 'get_attached_file' )->with( 12 )->andReturn( $file );

		$result = ( new Upload() )->add_svg_dimensions( [ 'filesize' => 31 ], 12 );
		unlink( $file );

		$this->assertSame( [ 'filesize' => 31, 'width' => 88, 'height' => 40 ], $result );
	}

	/**
	 * Test metadata of other file types is left alone.
	 */
	public function test_add_svg_dimensions_ignores_other_types() {
		WP_Mock::userFunction( 'get_post_mime_type' )->with( 13 )->andReturn( 'application/pdf' );

		$this->assertSame( [ 'filesize' => 10 ], ( new Upload() )->add_svg_dimensions( [ 'filesize' => 10 ], 13 ) );
	}
}
