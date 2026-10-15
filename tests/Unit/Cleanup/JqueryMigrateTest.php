<?php

declare(strict_types=1);

/**
 * jQuery Migrate is dropped from the jquery bundle on the front end only.
 *
 * @package Siratik\Baseline\Tests\Unit\Cleanup
 */

namespace Siratik\Baseline\Tests\Unit\Cleanup;

use BuiltNorth\WPMockStandIns\WPMockTestCase;
use Siratik\Baseline\Cleanup\JqueryMigrate;

class JqueryMigrateTest extends WPMockTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		$GLOBALS['siratik_baseline_test_hooks']   = [];
		$GLOBALS['siratik_baseline_test_filters'] = [];
		$GLOBALS['siratik_baseline_test_state']   = [];
	}

	private function registry(): object
	{
		$jquery       = new \stdClass();
		$jquery->deps = ['jquery-core', 'jquery-migrate'];
		$scripts      = new \stdClass();
		$scripts->registered = ['jquery' => $jquery];

		return $scripts;
	}

	public function test_front_end_drops_jquery_migrate(): void
	{
		$scripts = $this->registry();

		(new JqueryMigrate())->remove_from_jquery($scripts);

		$this->assertSame(['jquery-core'], $scripts->registered['jquery']->deps);
	}

	public function test_admin_keeps_jquery_migrate(): void
	{
		$GLOBALS['siratik_baseline_test_state']['admin'] = true;
		$scripts = $this->registry();

		(new JqueryMigrate())->remove_from_jquery($scripts);

		$this->assertSame(['jquery-core', 'jquery-migrate'], $scripts->registered['jquery']->deps);
	}

	public function test_hooks_the_registry_when_it_is_not_built_yet(): void
	{
		$migrate = new JqueryMigrate();
		$migrate->init();

		$this->assertContains(['action', 'wp_default_scripts', [$migrate, 'remove_from_jquery']], $GLOBALS['siratik_baseline_test_hooks']);
	}

	public function test_applies_at_once_when_the_registry_already_exists(): void
	{
		$scripts = $this->registry();
		$GLOBALS['siratik_baseline_test_state']['did']['wp_default_scripts'] = 1;
		$GLOBALS['siratik_baseline_test_state']['scripts'] = $scripts;

		(new JqueryMigrate())->init();

		$this->assertSame(['jquery-core'], $scripts->registered['jquery']->deps);
		$this->assertSame([], $GLOBALS['siratik_baseline_test_hooks']);
	}

	public function test_gate_off_does_nothing(): void
	{
		$GLOBALS['siratik_baseline_test_filters']['siratik_baseline_remove_jquery_migrate'] = false;

		(new JqueryMigrate())->init();

		$this->assertSame([], $GLOBALS['siratik_baseline_test_hooks']);
	}
}
