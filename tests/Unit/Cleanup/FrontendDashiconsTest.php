<?php

declare(strict_types=1);

/**
 * Dashicons are dropped for logged-out visitors only.
 *
 * @package Siratik\Baseline\Tests\Unit\Cleanup
 */

namespace Siratik\Baseline\Tests\Unit\Cleanup;

use BuiltNorth\WPMockStandIns\WPMockTestCase;
use Siratik\Baseline\Cleanup\FrontendDashicons;

class FrontendDashiconsTest extends WPMockTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		$GLOBALS['siratik_baseline_test_hooks']    = [];
		$GLOBALS['siratik_baseline_test_filters']  = [];
		$GLOBALS['siratik_baseline_test_state']    = [];
		$GLOBALS['siratik_baseline_test_dequeued'] = [];
	}

	public function test_visitors_do_not_get_dashicons(): void
	{
		(new FrontendDashicons())->dequeue();

		$this->assertSame([['style', 'dashicons']], $GLOBALS['siratik_baseline_test_dequeued']);
	}

	public function test_logged_in_users_keep_dashicons(): void
	{
		$GLOBALS['siratik_baseline_test_state']['logged_in'] = true;

		(new FrontendDashicons())->dequeue();

		$this->assertSame([], $GLOBALS['siratik_baseline_test_dequeued']);
	}

	public function test_gate_off_registers_nothing(): void
	{
		$GLOBALS['siratik_baseline_test_filters']['siratik_baseline_remove_frontend_dashicons'] = false;

		(new FrontendDashicons())->init();

		$this->assertSame([], $GLOBALS['siratik_baseline_test_hooks']);
	}
}
