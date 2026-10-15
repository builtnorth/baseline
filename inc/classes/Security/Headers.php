<?php

/**
 * ------------------------------------------------------------------
 * Headers
 * ------------------------------------------------------------------
 *
 * Implements security headers for WordPress.
 *
 * @package WPBaseline
 * @since 2.1.0
 */

namespace Siratik\Baseline\Security;

// Don't load directly.
defined('ABSPATH') || defined('WP_CLI') || exit;

class Headers
{
	/**
	 * Default security headers
	 *
	 * @var array
	 */
	private const SECURITY_HEADERS = [
		'X-Content-Type-Options' => 'nosniff',
		'X-Frame-Options' => 'SAMEORIGIN',
		'X-XSS-Protection' => '1; mode=block',
		'Referrer-Policy' => 'strict-origin-when-cross-origin',
		'Permissions-Policy' => 'geolocation=(), microphone=()',
		'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
	];

	/**
	 * Default Content Security Policy directives
	 *
	 * @var array
	 */
	private const CSP_DIRECTIVES = [
		'default-src'  => "'self'",
		'script-src'   => "'self' 'unsafe-inline' 'wasm-unsafe-eval' https: *.googleapis.com *.gstatic.com *.google.com *.google-analytics.com *.doubleclick.net *.wordpress.org *.wp.com",
		'style-src'    => "'self' 'unsafe-inline' https:",
		'worker-src'   => "'self' blob:",
		'img-src'      => "'self' data: https:",
		'font-src'     => "'self' data: https:",
		'connect-src'  => "'self' https: tiles.openfreemap.org nominatim.openstreetmap.org",
		'media-src'    => "'self' https:",
		'object-src'   => "'none'",
		'frame-src'    => "'self' https:",
		'base-uri'     => "'self'",
		'form-action'  => "'self'",
	];

	/**
	 * Initialize security headers
	 */
	public function init(): void
	{
		// Check if security headers are enabled
		if (!(bool) apply_filters('siratik_baseline_enable_security_headers', true)) {
			return;
		}

		// Apply security headers
		add_action('send_headers', [$this, 'apply_headers']);
	}

	/**
	 * Apply all security headers
	 */
	public function apply_headers(): void
	{
		// Set security headers	
		$this->set_security_headers();

		// Set CSP header
		$this->set_csp_header();
	}

	/**
	 * Set basic security headers
	 */
	private function set_security_headers(): void
	{
		if (headers_sent()) {
			return;
		}

		// Apply security headers
		$headers = apply_filters('siratik_baseline_security_headers', self::SECURITY_HEADERS);
		// HSTS has its own gate: browsers keep it for a year, for every subdomain.
		$send_hsts = is_ssl() && (bool) apply_filters('siratik_baseline_enable_hsts', true);

		// Loop through headers and set them
		foreach ($headers as $name => $value) {
			if ('Strict-Transport-Security' === $name && !$send_hsts) {
				continue;
			}

			header("$name: $value");
		}
	}

	/**
	 * Set Content Security Policy header
	 */
	private function set_csp_header(): void
	{
		// Check if headers have already been sent
		if (headers_sent()) {
			return;
		}

		// 'enforce' (default), 'report_only' (browsers report violations but
		// block nothing, for checking a site before enforcing) or 'off'.
		$mode = apply_filters('siratik_baseline_csp_mode', 'enforce');
		if (! in_array($mode, ['enforce', 'report_only'], true)) {
			return;
		}

		// Get CSP directives
		$directives = apply_filters('siratik_baseline_security_headers_csp', self::CSP_DIRECTIVES);

		// Build CSP header value
		$header_value = $this->build_csp_value($directives);

		// Set CSP header if value is not empty
		if (!empty($header_value)) {
			$name = $mode === 'report_only' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
			header("$name: $header_value");
		}
	}

	/**
	 * Build CSP header value from directives
	 *
	 * @param array $directives CSP directives
	 * @return string Formatted CSP header value
	 */
	private function build_csp_value(array $directives): string
	{
		// Initialize parts array
		$parts = [];

		// Loop through directives and build parts
		foreach ($directives as $directive => $value) {
			$parts[] = "$directive $value";
		}

		// Return parts as a string separated by semicolons
		return implode('; ', $parts);
	}
}
