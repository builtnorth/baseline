<?php

declare(strict_types=1);

/**
 * Asset URLs hide the WordPress version but still change when it changes.
 *
 * @package Siratik\Baseline\Tests\Unit\Security
 */

namespace Siratik\Baseline\Tests\Unit\Security;

use BuiltNorth\WPMockStandIns\WPMockTestCase;
use Siratik\Baseline\Security\Version;
use WP_Mock;

class VersionTest extends WPMockTestCase
{
	private function src_for(string $wp_version, string $src): string
	{
		WP_Mock::userFunction('get_bloginfo')->with('version')->andReturn($wp_version);
		WP_Mock::userFunction('wp_salt')->with('nonce')->andReturn('test-salt');
		WP_Mock::userFunction('remove_query_arg')->andReturnUsing(
			static fn (string $key, string $url): string => preg_replace('/([?&])' . $key . '=[^&]*&?/', '$1', $url)
		);
		WP_Mock::userFunction('add_query_arg')->andReturnUsing(
			static function (string $key, string $value, string $url): string {
				$url = rtrim($url, '?&');
				return $url . (str_contains($url, '?') ? '&' : '?') . $key . '=' . $value;
			}
		);
		WP_Mock::onFilter('siratik_baseline_asset_version_constant')->with('')->reply('');

		return (new Version())->replace_wp_version_in_files($src);
	}

	public function test_core_asset_url_hides_the_version(): void
	{
		$src = $this->src_for('6.8.1', 'https://example.org/wp-includes/css/dist/block-library/style.css?ver=6.8.1');

		$this->assertStringNotContainsString('6.8.1', $src);
		$this->assertMatchesRegularExpression('/\?ver=[0-9a-f]{10}$/', $src);
	}

	public function test_a_core_update_changes_the_asset_url(): void
	{
		$url = 'https://example.org/wp-includes/js/dist/blocks.min.js?ver=';

		$before = $this->src_for('6.8.1', $url . '6.8.1');
		$after = $this->src_for('6.8.2', $url . '6.8.2');

		$this->assertNotSame($before, $after);
	}

	public function test_the_same_version_gives_the_same_url(): void
	{
		$url = 'https://example.org/wp-includes/js/dist/blocks.min.js?ver=6.8.1';

		$this->assertSame($this->src_for('6.8.1', $url), $this->src_for('6.8.1', $url));
	}

	public function test_other_versions_are_left_alone(): void
	{
		$src = 'https://example.org/wp-content/plugins/acme/app.js?ver=2.3.0';

		$this->assertSame($src, $this->src_for('6.8.1', $src));
	}
}
