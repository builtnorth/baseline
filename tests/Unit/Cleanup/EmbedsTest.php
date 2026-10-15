<?php

declare(strict_types=1);

/**
 * Embeds cleanup removes discovery links and the host script, behind its gate.
 *
 * @package Siratik\Baseline\Tests\Unit\Cleanup
 */

namespace Siratik\Baseline\Tests\Unit\Cleanup;

use BuiltNorth\WPMockStandIns\WPMockTestCase;
use Siratik\Baseline\Cleanup\Embeds;

class EmbedsTest extends WPMockTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		$GLOBALS['siratik_baseline_test_removed'] = [];
		$GLOBALS['siratik_baseline_test_filters'] = [];
	}

	public function test_removes_discovery_links_and_host_script(): void
	{
		(new Embeds())->init();

		$removed = $GLOBALS['siratik_baseline_test_removed'];
		$this->assertContains(['action', 'wp_head', 'wp_oembed_add_discovery_links', 4], $removed);
		$this->assertContains(['action', 'wp_head', 'wp_oembed_add_discovery_links'], $removed);
		$this->assertContains(['action', 'wp_head', 'wp_oembed_add_host_js'], $removed);
		$this->assertContains(['filter', 'embed_oembed_html', 'wp_maybe_enqueue_oembed_host_js'], $removed);
	}

	public function test_keeps_the_oembed_rest_route(): void
	{
		(new Embeds())->init();

		foreach ($GLOBALS['siratik_baseline_test_removed'] as $removal) {
			$this->assertNotSame('rest_api_init', $removal[1]);
		}
	}

	public function test_gate_off_removes_nothing(): void
	{
		$GLOBALS['siratik_baseline_test_filters']['siratik_baseline_disable_embeds'] = false;

		(new Embeds())->init();

		$this->assertSame([], $GLOBALS['siratik_baseline_test_removed']);
	}
}
