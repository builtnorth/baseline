<?php

/**
 * ------------------------------------------------------------------
 * Embeds
 * ------------------------------------------------------------------
 *
 * Remove oEmbed discovery links and the wp-embed host script from the
 * front end. Embedding other sites' content (YouTube, etc.) still works, as
 * does the oEmbed REST route the block editor uses to preview embeds.
 *
 * @package WPBaseline
 */

namespace Siratik\Baseline\Cleanup;

// Don't load directly.
defined('ABSPATH') || defined('WP_CLI') || exit;

class Embeds
{
	/**
	 * Initialize the class.
	 */
	public function init()
	{
		if (!apply_filters('siratik_baseline_disable_embeds', true)) {
			return;
		}

		// Discovery links (registered at priorities 4 and 10 for back-compat).
		remove_action('wp_head', 'wp_oembed_add_discovery_links', 4);
		remove_action('wp_head', 'wp_oembed_add_discovery_links');

		// The wp-embed script that resizes embedded WordPress posts.
		remove_action('wp_head', 'wp_oembed_add_host_js');
		remove_filter('embed_oembed_html', 'wp_maybe_enqueue_oembed_host_js');
	}
}
