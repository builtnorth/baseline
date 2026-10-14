<?php

/**
 * ------------------------------------------------------------------
 * Constants
 * ------------------------------------------------------------------
 *
 * Define settings for security
 *
 * @package WPBaseline
 * @since 2.0.0
 */

namespace Siratik\Baseline\Security;

// Don't load directly.
defined('ABSPATH') || defined('WP_CLI') || exit;


class Constants
{
	/**
	 * Initialize the class.
	 */
	public function init()
	{
		// Turn off the theme and plugin file editors.
		if (! apply_filters('siratik_baseline_disallow_file_edit', true)) {
			return;
		}

		add_action('init', [$this, 'define_constants']);
	}

	/**
	 * Define constants.
	 */
	public function define_constants()
	{
		if (!defined('DISALLOW_FILE_EDIT')) {
			define('DISALLOW_FILE_EDIT', true);
		}
	}
}
