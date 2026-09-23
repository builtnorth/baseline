<?php
/**
 * Package-wide constants.
 *
 * @package Navas\Baseline
 */

declare(strict_types=1);

namespace Navas\Baseline\Core;

defined('ABSPATH') || defined('WP_CLI') || exit;

/**
 * Shared constants for hook names.
 *
 * Every hook this package fires derives its name from {@see self::HOOK_PREFIX}
 * rather than hardcoding the literal, so the prefix can be changed in one place.
 *
 * Deriving every hook from one constant means a consumer never has to check the
 * source to know what a hook is called, and the prefix cannot drift between
 * classes.
 */
final class Constants
{
	/**
	 * Prefix for every hook fired by this package.
	 *
	 * Includes the trailing underscore so callers can concatenate directly:
	 * `Constants::HOOK_PREFIX . 'disable_comments'`.
	 */
	public const HOOK_PREFIX = 'navas_baseline_';

	/**
	 * Build a fully-qualified hook name.
	 *
	 * Preferred over inline concatenation: it keeps the prefix out of the
	 * individual classes entirely, so no class spells any part of a hook name.
	 *
	 * @param string $name Unprefixed hook name (e.g. 'disable_comments').
	 * @return string Prefixed hook name.
	 */
	public static function hook(string $name): string
	{
		return self::HOOK_PREFIX . $name;
	}
}
