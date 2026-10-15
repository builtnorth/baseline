<?php

declare(strict_types=1);

/**
 * The REST users routes are hidden from visitors, but stay for anyone who can
 * edit posts: the block editor reads them (current user, author picker).
 *
 * @package Siratik\Baseline\Tests\Unit\Security
 */

namespace Siratik\Baseline\Tests\Unit\Security;

use BuiltNorth\WPMockStandIns\WPMockTestCase;
use Siratik\Baseline\Security\RestAPI;
use WP_Mock;

class RestAPITest extends WPMockTestCase
{
	private const ENDPOINTS = [
		'/wp/v2/users'                => ['users'],
		'/wp/v2/users/(?P<id>[\d]+)'  => ['user'],
		'/wp/v2/posts'                => ['posts'],
	];

	public function test_users_routes_are_removed_for_anyone_who_cannot_edit_posts(): void
	{
		WP_Mock::userFunction('current_user_can')->with('edit_posts')->andReturn(false);

		$endpoints = (new RestAPI())->restrict_user_endpoints(self::ENDPOINTS);

		$this->assertSame(['/wp/v2/posts'], array_keys($endpoints));
	}

	public function test_users_routes_stay_for_anyone_who_can_edit_posts(): void
	{
		WP_Mock::userFunction('current_user_can')->with('edit_posts')->andReturn(true);

		$endpoints = (new RestAPI())->restrict_user_endpoints(self::ENDPOINTS);

		$this->assertSame(self::ENDPOINTS, $endpoints);
	}
}
