<?php

/**
 * ------------------------------------------------------------------
 *  Rest API
 * ------------------------------------------------------------------
 *
 * Restricts access to the REST API
 *
 * @package WPBaseline
 * @since 1.0.0
 */

namespace Siratik\Baseline\Security;

defined('ABSPATH') || defined('WP_CLI') || exit;

class RestAPI
{

	/**
	 * Initialize the class
	 */
	public function init()
	{

		if ((bool) apply_filters('siratik_baseline_disable_user_rest_endpoints', true)) {
			// Remove users endpoint for non-authenticated users
			add_filter('rest_endpoints', [$this, 'restrict_user_endpoints']);

			// Optionally disable the users endpoint completely
			if ((bool) apply_filters('siratik_baseline_disable_user_rest_endpoints_completely', false)) {
				add_filter('rest_endpoints', [$this, 'disable_user_endpoints_completely']);
			}
		}
	}

	/**
	 * Restricts access to the users endpoint
	 */
	public function restrict_user_endpoints($endpoints)
	{
		// Hidden from visitors only: the block editor reads these routes
		// (current user, author picker) for anyone who can edit posts.
		if (!current_user_can('edit_posts')) {
			if (isset($endpoints['/wp/v2/users'])) {
				unset($endpoints['/wp/v2/users']);
			}
			if (isset($endpoints['/wp/v2/users/(?P<id>[\d]+)'])) {
				unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
			}
		}
		return $endpoints;
	}

	/**	
	 * Completely remove user endpoints regardless of authentication
	 */
	public function disable_user_endpoints_completely($endpoints)
	{
		unset($endpoints['/wp/v2/users']);
		unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
		return $endpoints;
	}
}
