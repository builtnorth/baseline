<?php

declare(strict_types=1);

/**
 * ------------------------------------------------------------------
 * Disabled XML-RPC server
 * ------------------------------------------------------------------
 *
 * Stands in for wp_xmlrpc_server while XML-RPC is off. Core's xmlrpc.php
 * instantiates whatever class `wp_xmlrpc_server_class` returns and calls
 * serve_request(), so this answers every request with a 403.
 *
 * @package WPBaseline
 */

namespace Siratik\Baseline\Security;

// Don't load directly.
defined('ABSPATH') || defined('WP_CLI') || exit;

class DisabledXmlrpcServer
{
	/**
	 * Refuse the request.
	 */
	public function serve_request(): void
	{
		status_header(403);
		header('Content-Type: text/plain; charset=utf-8');
		echo 'XML-RPC services are disabled on this site.';
		exit;
	}
}
