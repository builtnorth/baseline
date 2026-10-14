<?php

/**
 * ------------------------------------------------------------------
 * XMLRPC
 * ------------------------------------------------------------------
 *
 * Disable XMLRPC
 *
 * @package WPBaseline
 * @since 2.0.0
 */

namespace Siratik\Baseline\Security;

// Don't load directly.
defined('ABSPATH') || defined('WP_CLI') || exit;

class XMLRPC
{
	/**
	 * Initialize the class.
	 */
	public function init()
	{
		if ((bool) apply_filters('siratik_baseline_disable_xmlrpc', true)) {
			// Last, so a later filter can't turn XML-RPC back on.
			add_filter('wp_xmlrpc_server_class', [$this, 'disabled_server_class'], PHP_INT_MAX);
			add_filter('xmlrpc_enabled', '__return_false', PHP_INT_MAX);
			add_action('init', [$this, 'refuse_xmlrpc_request'], 0);
			add_filter('pre_update_option_enable_xmlrpc', '__return_false');
			add_filter('pre_option_enable_xmlrpc', '__return_zero');
			add_filter('mod_rewrite_rules', [$this, 'block_xmlrpc_requests']);
			add_filter('wp_headers', [$this, 'remove_xmlrpc_headers']);
		}
	}

	/**
	 * Server class for xmlrpc.php while XML-RPC is off.
	 *
	 * Must be a class name: core instantiates it, so returning false made
	 * every request a PHP fatal error (500) instead of a refusal.
	 */
	public function disabled_server_class(): string
	{
		return DisabledXmlrpcServer::class;
	}

	/**
	 * Refuse xmlrpc.php on init.
	 *
	 * Core answers `xmlrpc.php?rsd` itself after WordPress loads, before it
	 * asks for the server class, so the endpoint would still be advertised.
	 */
	public function refuse_xmlrpc_request(): void
	{
		if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
			(new DisabledXmlrpcServer())->serve_request();
		}
	}

	/**
	 * Blocks XML-RPC requests (Apache 2.4 `Require`; 2.2 syntax only where
	 * mod_authz_core is missing, since 2.4 without mod_access_compat 500s on it).
	 */
	public function block_xmlrpc_requests($rules)
	{
		// Read each time the rules are built, so a late filter wins (e.g. a
		// host removing the rules on deactivation before a final flush).
		if (! (bool) apply_filters('siratik_baseline_write_htaccess_rules', true)) {
			return $rules;
		}

		return "
		# Block XML-RPC requests
		<Files xmlrpc.php>
			<IfModule mod_authz_core.c>
				Require all denied
			</IfModule>
			<IfModule !mod_authz_core.c>
				Order Deny,Allow
				Deny from all
			</IfModule>
		</Files>\n\n" . $rules;
	}

	/**
	 * Removes the X-Pingback header
	 */
	public function remove_xmlrpc_headers($headers)
	{
		unset($headers['X-Pingback']);
		return $headers;
	}
}
