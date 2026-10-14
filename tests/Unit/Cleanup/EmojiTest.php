<?php

declare(strict_types=1);

/**
 * Emoji cleanup turns smileys off for the request without changing the site's setting.
 *
 * @package Siratik\Baseline\Tests\Unit\Cleanup
 */

namespace Siratik\Baseline\Tests\Unit\Cleanup;

use BuiltNorth\WPMockStandIns\WPMockTestCase;
use Siratik\Baseline\Cleanup\Emoji;

class EmojiTest extends WPMockTestCase
{
	public function test_init_never_writes_the_use_smilies_option(): void
	{
		$GLOBALS['siratik_baseline_test_hooks'] = [];
		$GLOBALS['siratik_baseline_test_option_writes'] = [];

		$emoji = new Emoji();
		$emoji->init();

		$this->assertSame([], $GLOBALS['siratik_baseline_test_option_writes']);
		$this->assertContains(
			['filter', 'pre_option_use_smilies', [$emoji, 'disable_smilies']],
			$GLOBALS['siratik_baseline_test_hooks']
		);
		$this->assertSame('0', $emoji->disable_smilies());
	}
}
