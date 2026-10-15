<?php

declare(strict_types=1);

/**
 * WooCommerce cart fragments are skipped only for empty carts outside cart
 * and checkout.
 *
 * @package Siratik\Baseline\Tests\Unit\Cleanup
 */

namespace {
	// WooCommerce conditionals, driven by test state.
	if (!function_exists('is_cart')) {
		function is_cart() { return !empty($GLOBALS['siratik_baseline_test_state']['is_cart']); }
		function is_checkout() { return !empty($GLOBALS['siratik_baseline_test_state']['is_checkout']); }
	}
}

namespace Siratik\Baseline\Tests\Unit\Cleanup {

	use BuiltNorth\WPMockStandIns\WPMockTestCase;
	use Siratik\Baseline\Cleanup\CartFragments;

	class CartFragmentsTest extends WPMockTestCase
	{
		public function setUp(): void
		{
			parent::setUp();
			$GLOBALS['siratik_baseline_test_hooks']    = [];
			$GLOBALS['siratik_baseline_test_filters']  = [];
			$GLOBALS['siratik_baseline_test_state']    = [];
			$GLOBALS['siratik_baseline_test_dequeued'] = [];
			unset($_COOKIE['woocommerce_items_in_cart']);
		}

		public function tearDown(): void
		{
			unset($_COOKIE['woocommerce_items_in_cart']);
			parent::tearDown();
		}

		public function test_empty_cart_skips_fragments(): void
		{
			(new CartFragments())->dequeue();

			$this->assertSame([['script', 'wc-cart-fragments']], $GLOBALS['siratik_baseline_test_dequeued']);
		}

		public function test_a_cart_with_items_keeps_fragments(): void
		{
			$_COOKIE['woocommerce_items_in_cart'] = '1';

			(new CartFragments())->dequeue();

			$this->assertSame([], $GLOBALS['siratik_baseline_test_dequeued']);
		}

		public function test_cart_and_checkout_keep_fragments(): void
		{
			$GLOBALS['siratik_baseline_test_state']['is_cart'] = true;
			(new CartFragments())->dequeue();

			$GLOBALS['siratik_baseline_test_state'] = ['is_checkout' => true];
			(new CartFragments())->dequeue();

			$this->assertSame([], $GLOBALS['siratik_baseline_test_dequeued']);
		}

		public function test_gate_off_registers_nothing(): void
		{
			$GLOBALS['siratik_baseline_test_filters']['siratik_baseline_disable_wc_cart_fragments'] = false;

			(new CartFragments())->init();

			$this->assertSame([], $GLOBALS['siratik_baseline_test_hooks']);
		}
	}
}
