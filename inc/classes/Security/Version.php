<?php

/**
 * ------------------------------------------------------------------
 * Version
 * ------------------------------------------------------------------
 *
 * Remove version information from the WordPress version header, feeds, and files.
 *
 * @package WPBaseline
 * @since 2.0.0
 */

namespace Siratik\Baseline\Security;

// Don't load directly.
defined('ABSPATH') || defined('WP_CLI') || exit;

class Version
{
	/**
	 * Initialize the class.
	 */
	public function init()
	{
		add_filter('the_generator', [$this, 'remove_wp_version_head_and_feeds']);
		add_action('admin_menu', [$this, 'remove_wp_version_footer'], 9999);
		add_filter('style_loader_src', [$this, 'replace_wp_version_in_files'], 9999);
		add_filter('script_loader_src', [$this, 'replace_wp_version_in_files'], 9999);
	}

	/**
	 * Remove the WordPress version from the head and feeds.
	 */
	public function remove_wp_version_head_and_feeds()
	{
		return '';
	}

	/**
	 * Remove the WordPress version from the footer.
	 */
	public function remove_wp_version_footer()
	{
		add_filter('admin_footer_text', '__return_empty_string', 11);
		add_filter('update_footer', '__return_empty_string', 11);
	}

	/**
	 * Replace the WordPress version in the files.
	 */
	public function replace_wp_version_in_files($src)
	{
		if (strpos($src, 'ver=')) {
			$version = $this->get_query_arg_value('ver', $src);
			if ($version === get_bloginfo('version')) {
				$src = remove_query_arg('ver', $src);
				$src = add_query_arg('ver', $this->get_asset_version(), $src);
			}
		}
		return $src;
	}

	/**
	 * Get the query argument value.
	 */
	private function get_query_arg_value($arg, $url)
	{
		$parts = parse_url($url);
		if (!isset($parts['query'])) {
			return null;
		}
		parse_str($parts['query'], $query);
		return isset($query[$arg]) ? $query[$arg] : null;
	}

	/**
	 * Get the asset version.
	 *
	 * A salted hash of the WordPress version: it hides the version, yet changes on
	 * every core update, so browsers fetch the new core CSS/JS. (The theme's
	 * style.css mtime used before stayed the same across core updates, so stale
	 * cached core assets were served.) A theme or plugin constant, if named via
	 * the filter, is mixed in so its own deploys bust the cache too.
	 */
	private function get_asset_version()
	{
		$seed = (string) get_bloginfo('version');

		$asset_version_constant = apply_filters('siratik_baseline_asset_version_constant', '');
		if ($asset_version_constant !== '' && defined($asset_version_constant)) {
			$seed .= '|' . constant($asset_version_constant);
		}

		$salt = function_exists('wp_salt') ? wp_salt('nonce') : (defined('NONCE_SALT') ? NONCE_SALT : '');

		return substr(hash_hmac('sha256', $seed, $salt), 0, 10);
	}
}
