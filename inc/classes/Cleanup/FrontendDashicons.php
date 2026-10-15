<?php

/**
 * ------------------------------------------------------------------
 * Front-end Dashicons
 * ------------------------------------------------------------------
 *
 * Don't load the Dashicons stylesheet for visitors who aren't logged in. It
 * is mostly there for the admin bar, which logged-out visitors don't see.
 *
 * @package WPBaseline
 */

namespace Siratik\Baseline\Cleanup;

// Don't load directly.
defined('ABSPATH') || defined('WP_CLI') || exit;

class FrontendDashicons
{
	/**
	 * Initialize the class.
	 */
	public function init()
	{
		if (!apply_filters('siratik_baseline_remove_frontend_dashicons', true)) {
			return;
		}

		add_action('wp_enqueue_scripts', [$this, 'dequeue'], 100);
	}

	/**
	 * Action: wp_enqueue_scripts.
	 */
	public function dequeue(): void
	{
		if (is_user_logged_in()) {
			return;
		}

		wp_dequeue_style('dashicons');
	}
}
