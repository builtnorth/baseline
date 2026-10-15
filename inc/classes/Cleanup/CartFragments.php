<?php

/**
 * ------------------------------------------------------------------
 * WooCommerce cart fragments
 * ------------------------------------------------------------------
 *
 * Skip WooCommerce's cart-fragments script (an AJAX request on every page
 * load) for visitors with an empty cart. Visitors with items keep it, so a
 * header mini-cart still updates for them; cart and checkout always keep it.
 *
 * @package WPBaseline
 */

namespace Siratik\Baseline\Cleanup;

// Don't load directly.
defined('ABSPATH') || defined('WP_CLI') || exit;

class CartFragments
{
	/**
	 * Initialize the class.
	 */
	public function init()
	{
		if (!apply_filters('siratik_baseline_disable_wc_cart_fragments', true)) {
			return;
		}

		add_action('wp_enqueue_scripts', [$this, 'dequeue'], 100);
	}

	/**
	 * Action: wp_enqueue_scripts.
	 */
	public function dequeue(): void
	{
		if (!function_exists('is_cart') || is_cart() || is_checkout()) {
			return;
		}

		if (!empty($_COOKIE['woocommerce_items_in_cart'])) {
			return;
		}

		wp_dequeue_script('wc-cart-fragments');
	}
}
