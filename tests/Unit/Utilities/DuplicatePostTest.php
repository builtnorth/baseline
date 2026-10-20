<?php
/**
 * Tests for Duplicate Post utility
 *
 * @package Siratik\Baseline\Tests\Unit\Utilities
 */

namespace Siratik\Baseline\Tests\Unit\Utilities;

use BuiltNorth\WPMockStandIns\WPMockTestCase;
use Siratik\Baseline\Utilities\DuplicatePost;
use WP_Mock;

/**
 * DuplicatePost test case
 */
class DuplicatePostTest extends WPMockTestCase {

	/**
	 * Test non-WP_Post input is ignored.
	 */
	public function test_add_duplicate_link_ignores_non_post_input() {
		$duplicate_post = new DuplicatePost();
		$actions        = [ 'edit' => '<a href="#">Edit</a>' ];

		$result = $duplicate_post->add_duplicate_link( $actions, new \stdClass() );

		$this->assertSame( $actions, $result );
	}

	/**
	 * Test permission check safely handles invalid post type object.
	 */
	public function test_can_duplicate_post_returns_false_for_invalid_post_type_object() {
		$duplicate_post = new DuplicatePost();

		WP_Mock::userFunction( 'get_post_type_object' )
			->with( 'page' )
			->andReturn( false );

		$method = new \ReflectionMethod( DuplicatePost::class, 'can_duplicate_post' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$duplicate_post,
			(object) [
				'post_type' => 'page',
			]
		);

		$this->assertFalse( $result );
	}

	/**
	 * Test permission check uses the per-object edit_post capability, not the
	 * blanket edit_posts type capability, so ownership is respected.
	 */
	public function test_can_duplicate_post_checks_per_object_capability() {
		$duplicate_post = new DuplicatePost();
		$post            = (object) [
			'ID'        => 42,
			'post_type' => 'post',
		];

		WP_Mock::userFunction( 'get_post_type_object' )
			->with( 'post' )
			->andReturn( (object) [ 'cap' => (object) [ 'edit_posts' => 'edit_posts' ] ] );

		WP_Mock::userFunction( 'current_user_can' )
			->with( 'edit_post', 42 )
			->andReturn( false );

		WP_Mock::onFilter( 'siratik_baseline_can_duplicate_post' )
			->with( false, $post )
			->reply( false );

		$method = new \ReflectionMethod( DuplicatePost::class, 'can_duplicate_post' );
		$method->setAccessible( true );

		$result = $method->invoke( $duplicate_post, $post );

		$this->assertFalse( $result );
	}

	/**
	 * Test a user who can edit the specific post is allowed to duplicate it.
	 */
	public function test_can_duplicate_post_allows_when_user_can_edit_post() {
		$duplicate_post = new DuplicatePost();
		$post            = (object) [
			'ID'        => 7,
			'post_type' => 'post',
		];

		WP_Mock::userFunction( 'get_post_type_object' )
			->with( 'post' )
			->andReturn( (object) [ 'cap' => (object) [ 'edit_posts' => 'edit_posts' ] ] );

		WP_Mock::userFunction( 'current_user_can' )
			->with( 'edit_post', 7 )
			->andReturn( true );

		WP_Mock::onFilter( 'siratik_baseline_can_duplicate_post' )
			->with( true, $post )
			->reply( true );

		$method = new \ReflectionMethod( DuplicatePost::class, 'can_duplicate_post' );
		$method->setAccessible( true );

		$result = $method->invoke( $duplicate_post, $post );

		$this->assertTrue( $result );
	}

	/**
	 * The copy keeps the page template, and meta survives add_post_meta()'s
	 * unslashing (backslashes in JSON meta used to be stripped).
	 */
	public function test_meta_copy_keeps_the_template_and_backslashes() {
		$json = '{"path":"C:\\\\site","quote":"\\"hi\\""}';

		WP_Mock::userFunction( 'get_post_meta' )->with( 10 )->andReturn(
			[
				'_wp_page_template' => [ 'templates/landing.php' ],
				'_edit_lock'        => [ '123:1' ],
				'settings_json'     => [ $json ],
			]
		);
		WP_Mock::userFunction( 'maybe_unserialize' )->andReturnUsing( static fn ( $value ) => $value );
		WP_Mock::userFunction( 'wp_slash' )->andReturnUsing( static fn ( $value ) => addslashes( $value ) );

		$saved = [];
		WP_Mock::userFunction( 'add_post_meta' )->andReturnUsing(
			static function ( $post_id, $key, $value ) use ( &$saved ) {
				// What WordPress stores: add_post_meta() unslashes its input.
				$saved[ $key ] = stripslashes( $value );
				return true;
			}
		);

		$method = new \ReflectionMethod( DuplicatePost::class, 'duplicate_meta_fields' );
		$method->setAccessible( true );
		$method->invoke( new DuplicatePost(), 10, 20 );

		$this->assertSame( 'templates/landing.php', $saved['_wp_page_template'] ?? null );
		$this->assertArrayNotHasKey( '_edit_lock', $saved );
		$this->assertSame( $json, $saved['settings_json'] ?? null );
	}

	/**
	 * Title, content and excerpt are slashed for wp_insert_post(), which
	 * unslashes them, so backslashes in block attribute JSON survive.
	 */
	public function test_duplicate_keeps_backslashes_in_post_fields() {
		$content = '<!-- wp:kitaris/demo {"text":"say \\"hi\\""} /-->';
		$post    = new \WP_Post( (object) [
			'ID'           => 10,
			'post_title'   => 'C:\\path',
			'post_content' => $content,
			'post_excerpt' => 'a\\b',
			'post_type'    => 'post',
			'post_parent'  => 0,
			'menu_order'   => 0,
		] );

		WP_Mock::userFunction( 'get_current_user_id' )->andReturn( 1 );
		WP_Mock::userFunction( 'wp_slash' )->andReturnUsing( static fn ( $value ) => addslashes( $value ) );
		WP_Mock::userFunction( 'get_object_taxonomies' )->andReturn( [] );
		WP_Mock::userFunction( 'get_post_meta' )->andReturn( [] );

		$saved = [];
		WP_Mock::userFunction( 'wp_insert_post' )->andReturnUsing(
			static function ( $args ) use ( &$saved ) {
				// What WordPress stores: wp_insert_post() unslashes its input.
				$saved = array_map( static fn ( $value ) => is_string( $value ) ? stripslashes( $value ) : $value, $args );
				return 20;
			}
		);

		$method = new \ReflectionMethod( DuplicatePost::class, 'create_duplicate' );
		$method->setAccessible( true );

		$this->assertSame( 20, $method->invoke( new DuplicatePost(), $post ) );
		$this->assertSame( $content, $saved['post_content'] );
		$this->assertSame( 'C:\\path (Copy)', $saved['post_title'] );
		$this->assertSame( 'a\\b', $saved['post_excerpt'] );
	}
}
