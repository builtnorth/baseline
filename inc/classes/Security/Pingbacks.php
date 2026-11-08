<?php

declare(strict_types=1);

/**
 * ------------------------------------------------------------------
 * Pingbacks
 * ------------------------------------------------------------------
 *
 * Disable self-pingbacks
 *
 * @package WPBaseline
 * @since 2.0.0
 */

namespace Siratik\Baseline\Security;

// Don't load directly.
defined('ABSPATH') || defined('WP_CLI') || exit;

class Pingbacks
{
	/**
	 * Initialize the class.
	 */
	public function init()
	{
		// Links between the site's own posts don't ping it. The X-Pingback
		// header is the XML-RPC switch's (Security\XMLRPC).
		if (! apply_filters('siratik_baseline_disable_self_pingbacks', true)) {
			return;
		}

		add_action('pre_ping', [$this, 'no_self_ping']);
	}

	/**
	 * Remove self pingbacks.
	 */
	public function no_self_ping(&$links)
	{
		$home = (string) get_option('home');

		if ($home === '' || ! is_array($links)) {
			return;
		}

		foreach ($links as $l => $link) {
			if (is_string($link) && 0 === strpos($link, $home)) {
				unset($links[$l]);
			}
		}
	}
}
