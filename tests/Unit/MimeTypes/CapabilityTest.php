<?php
/**
 * Tests for the shared mime upload capability gate
 *
 * @package Navas\Baseline\Tests\Unit\MimeTypes
 */

namespace Navas\Baseline\Tests\Unit\MimeTypes;

use Navas\Baseline\MimeTypes\Capability;
use Navas\Baseline\Tests\WPMockTestCase;
use WP_Mock;

/**
 * Capability test case
 */
class CapabilityTest extends WPMockTestCase {

	/**
	 * Test the default required capability is manage_options.
	 */
	public function test_defaults_to_manage_options() {
		WP_Mock::onFilter( 'navas_baseline_mime_upload_capability' )
			->with( 'manage_options', 'svg' )
			->reply( 'manage_options' );

		WP_Mock::userFunction( 'current_user_can' )
			->with( 'manage_options' )
			->andReturn( true );

		$this->assertTrue( Capability::current_user_can_upload( 'svg' ) );
	}

	/**
	 * Test the filter can loosen the required capability per mime key.
	 */
	public function test_filter_can_override_capability() {
		WP_Mock::onFilter( 'navas_baseline_mime_upload_capability' )
			->with( 'manage_options', 'svg' )
			->reply( 'upload_files' );

		WP_Mock::userFunction( 'current_user_can', [ 'return' => true ] );

		$this->assertTrue( Capability::current_user_can_upload( 'svg' ) );
	}

	/**
	 * Test a user lacking the required capability is denied.
	 */
	public function test_denies_user_without_capability() {
		WP_Mock::onFilter( 'navas_baseline_mime_upload_capability' )
			->with( 'manage_options', 'json' )
			->reply( 'manage_options' );

		WP_Mock::userFunction( 'current_user_can' )
			->with( 'manage_options' )
			->andReturn( false );

		$this->assertFalse( Capability::current_user_can_upload( 'json' ) );
	}
}
