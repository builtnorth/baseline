<?php
/**
 * Tests for AuthorEnumeration.
 *
 * @package Siratik\Baseline\Tests\Unit\Security
 */

declare(strict_types=1);

namespace Siratik\Baseline\Tests\Unit\Security;

use BuiltNorth\WPMockStandIns\WPMockTestCase;
use Siratik\Baseline\Security\AuthorEnumeration;
use WP_Mock;

/**
 * Numeric author lookups are refused however the `author` var arrives.
 */
class AuthorEnumerationTest extends WPMockTestCase {

	/**
	 * Whether the current user has list_users.
	 */
	private bool $can_list_users = false;

	public function setUp(): void {
		parent::setUp();
		$this->can_list_users = false;
		WP_Mock::userFunction( 'current_user_can' )->andReturnUsing( fn () => $this->can_list_users );
		WP_Mock::userFunction( 'is_author' )->andReturn( true );
		WP_Mock::userFunction( 'esc_html__' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'wp_die' )->andReturnUsing(
			static function () {
				throw new \RuntimeException( 'wp_die' );
			}
		);
	}

	public function tearDown(): void {
		unset( $GLOBALS['wp'] );
		parent::tearDown();
	}

	/**
	 * WordPress also reads public query vars from a POST body, so the parsed
	 * request var is what counts, not $_GET.
	 */
	public function test_author_var_from_a_post_body_is_refused(): void {
		$GLOBALS['wp'] = (object) [ 'query_vars' => [ 'author' => '1' ] ];

		$this->expectException( \RuntimeException::class );
		( new AuthorEnumeration() )->block_numeric_author_lookup();
	}

	/**
	 * A pretty /author/slug/ archive sets author_name only and still renders.
	 */
	public function test_author_name_archive_is_left_alone(): void {
		$GLOBALS['wp'] = (object) [ 'query_vars' => [ 'author_name' => 'jane' ] ];

		( new AuthorEnumeration() )->block_numeric_author_lookup();

		$this->assertTrue( true );
	}

	public function test_author_var_as_an_array_is_refused(): void {
		$GLOBALS['wp'] = (object) [ 'query_vars' => [ 'author' => [ '1' ] ] ];

		$this->expectException( \RuntimeException::class );
		( new AuthorEnumeration() )->block_numeric_author_lookup();
	}

	public function test_user_who_can_list_users_is_let_through(): void {
		$this->can_list_users = true;
		$GLOBALS['wp'] = (object) [ 'query_vars' => [ 'author' => '1' ] ];

		( new AuthorEnumeration() )->block_numeric_author_lookup();

		$this->assertTrue( true );
	}

	public function test_users_sitemap_is_removed_and_others_kept(): void {
		$enumeration = new AuthorEnumeration();

		$this->assertFalse( $enumeration->remove_users_sitemap( 'provider', 'users' ) );
		$this->assertSame( 'provider', $enumeration->remove_users_sitemap( 'provider', 'posts' ) );
	}

	public function test_oembed_author_url_points_home(): void {
		WP_Mock::userFunction( 'get_home_url' )->andReturn( 'https://example.com' );

		$data = ( new AuthorEnumeration() )->hide_oembed_author_url(
			[
				'author_name' => 'Jane',
				'author_url'  => 'https://example.com/author/jane/',
			]
		);

		$this->assertSame( 'https://example.com', $data['author_url'] );
		$this->assertSame( 'Jane', $data['author_name'] );
	}

	public function test_comment_author_slug_class_is_removed(): void {
		WP_Mock::userFunction( 'get_userdata' )->with( 5 )->andReturn( (object) [ 'user_nicename' => 'jane' ] );
		WP_Mock::userFunction( 'sanitize_html_class' )->andReturnArg( 0 );

		$classes = ( new AuthorEnumeration() )->remove_comment_author_class(
			[ 'comment', 'byuser', 'comment-author-jane', 'comment-author-highlight', 'bypostauthor' ],
			'comment-author-highlight',
			9,
			(object) [ 'user_id' => '5' ]
		);

		$this->assertSame( [ 'comment', 'byuser', 'comment-author-highlight', 'bypostauthor' ], $classes );
	}

	public function test_users_sitemap_has_its_own_switch(): void {
		$GLOBALS['siratik_baseline_test_hooks']   = [];
		$GLOBALS['siratik_baseline_test_filters'] = [ 'siratik_baseline_remove_users_sitemap' => false ];
		$enumeration = new AuthorEnumeration();

		$enumeration->init();

		$hooks = $GLOBALS['siratik_baseline_test_hooks'];
		unset( $GLOBALS['siratik_baseline_test_filters'] );
		$this->assertNotContains( [ 'filter', 'wp_sitemaps_add_provider', [ $enumeration, 'remove_users_sitemap' ], 10, 2 ], $hooks );
		$this->assertContains( [ 'filter', 'comment_class', [ $enumeration, 'remove_comment_author_class' ], 10, 4 ], $hooks );
	}
}
