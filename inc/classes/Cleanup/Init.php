<?php

/**
 * ------------------------------------------------------------------
 * Init
 * ------------------------------------------------------------------
 *
 * Initialize the cleanup classes
 *
 * @package WPBaseline
 * @since 1.0.0
 */

namespace Siratik\Baseline\Cleanup;

use Siratik\Baseline\Abstracts\AbstractInit;

// Don't load directly.
defined('ABSPATH') || defined('WP_CLI') || exit;

class Init extends AbstractInit
{
	/**
	 * Get the namespace for the init class
	 */
	protected function getNamespace(): string
	{
		return __NAMESPACE__;
	}

	/**
	 * Get the classes to initialize
	 */
	protected function getClasses(): array
	{
		return [
			'AdminBar',
			'CartFragments',
			'Dashboard',
			'Embeds',
			'Emoji',
			'FrontendDashicons',
			'General',
			'JqueryMigrate',
			'Login',
			'Mail',
			'Widgets',
		];
	}
}
