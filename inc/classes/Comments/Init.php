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

namespace Navas\Baseline\Comments;

use Navas\Baseline\Abstracts\AbstractInit;

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
			'Actions',
			'Filters',
			'Blocks',
		];
	}
}
