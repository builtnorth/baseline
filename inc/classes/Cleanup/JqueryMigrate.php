<?php

/**
 * ------------------------------------------------------------------
 * jQuery Migrate
 * ------------------------------------------------------------------
 *
 * Drop jquery-migrate from the jquery bundle on the front end. Admin screens
 * keep it, since older admin code may still depend on it.
 *
 * @package WPBaseline
 */

namespace Siratik\Baseline\Cleanup;

// Don't load directly.
defined('ABSPATH') || defined('WP_CLI') || exit;

class JqueryMigrate
{
	/**
	 * Initialize the class.
	 */
	public function init()
	{
		if (!apply_filters('siratik_baseline_remove_jquery_migrate', true)) {
			return;
		}

		// Baseline boots at after_setup_theme; a plugin that registered a
		// script earlier has already built the registry.
		if (did_action('wp_default_scripts')) {
			$this->remove_from_jquery(wp_scripts());
			return;
		}

		add_action('wp_default_scripts', [$this, 'remove_from_jquery']);
	}

	/**
	 * Action: wp_default_scripts.
	 *
	 * @param \WP_Scripts $scripts Script registry.
	 */
	public function remove_from_jquery($scripts): void
	{
		if (is_admin() || !is_object($scripts) || empty($scripts->registered['jquery'])) {
			return;
		}

		$jquery       = $scripts->registered['jquery'];
		$jquery->deps = array_values(array_diff((array) $jquery->deps, ['jquery-migrate']));
	}
}
