<?php

declare(strict_types=1);

/**
 * ------------------------------------------------------------------
 * Mail
 * ------------------------------------------------------------------
 *
 * Customize the email sent from WordPress
 *
 * @package WPBaseline
 * @since 2.0.0
 */

namespace Siratik\Baseline\Cleanup;

// Don't load directly.
defined('ABSPATH') || defined('WP_CLI') || exit;

class Mail
{
	/**
	 * Initialize the class.
	 */
	public function init()
	{

		// Send mail as the site's name.
		if (apply_filters('siratik_baseline_mail_from_site_name', true)) {
			add_filter('wp_mail_from_name', [$this, 'mail_from_name']);
		}

		// Check if the removal of emails should be enabled
		$enable_removal = apply_filters('siratik_baseline_disable_update_emails', true);

		if (!$enable_removal) {
			return;
		}

		add_filter('auto_core_update_send_email', [$this, 'disable_auto_update_emails']);
		add_filter('auto_plugin_update_send_email', [$this, 'disable_auto_update_emails']);
		add_filter('auto_theme_update_send_email', [$this, 'disable_auto_update_emails']);
	}

	/**
	 * Change WP Mail from name
	 */
	/**
	 * Replace WordPress's default From name ("WordPress") with the site name.
	 * A name set on purpose (a From header, a mail plugin) is left alone.
	 *
	 * @param mixed $from_name The From name so far.
	 * @return mixed
	 */
	public function mail_from_name($from_name)
	{
		if ($from_name !== 'WordPress') {
			return $from_name;
		}

		$site_name = wp_specialchars_decode((string) get_option('blogname'), ENT_QUOTES);

		return $site_name !== '' ? $site_name : $from_name;
	}

	/**
	 * Change WP Mail from email address
	 */
	// public function mail_from_email()
	// {
	// 	return get_option("admin_email");
	// }

	/**
	 * Disable auto update emails
	 */
	public function disable_auto_update_emails()
	{
		return false;
	}
}
