<?php

declare(strict_types=1);

/**
 * The site name replaces only WordPress's default From name; a From name set
 * on purpose (a form, a mail plugin) is left alone.
 *
 * @package Siratik\Baseline\Tests\Unit\Cleanup
 */

namespace Siratik\Baseline\Tests\Unit\Cleanup;

use BuiltNorth\WPMockStandIns\WPMockTestCase;
use Siratik\Baseline\Cleanup\Mail;
use WP_Mock;

class MailTest extends WPMockTestCase
{
	public function test_only_the_core_default_from_name_becomes_the_site_name(): void
	{
		WP_Mock::userFunction('Siratik\Baseline\Cleanup\get_option')->with('blogname')->andReturn('Joe&#039;s Bakery');
		WP_Mock::userFunction('wp_specialchars_decode')->andReturnUsing(
			static fn (string $text, $quotes): string => html_entity_decode($text, ENT_QUOTES)
		);

		$mail = new Mail();

		$this->assertSame("Joe's Bakery", $mail->mail_from_name('WordPress'));
		$this->assertSame('Contact Form', $mail->mail_from_name('Contact Form'));
	}
}
