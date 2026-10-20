<?php

/**
 * ------------------------------------------------------------------
 * UploadsExecution
 * ------------------------------------------------------------------
 *
 * Blocks PHP execution inside wp-content/uploads
 *
 * @package WPBaseline
 * @since 2.2.0
 */

namespace Siratik\Baseline\Security;

// Don't load directly.
defined('ABSPATH') || defined('WP_CLI') || exit;

class UploadsExecution
{
	/**
	 * Initialize the class.
	 */
	public function init()
	{
		if ((bool) apply_filters('siratik_baseline_block_uploads_php_execution', true)) {
			add_filter('mod_rewrite_rules', [$this, 'block_uploads_php_execution']);
		}
	}

	/**
	 * Denies PHP execution under wp-content/uploads.
	 *
	 * WordPress's `mod_rewrite_rules` filter only ever writes to the site's
	 * ROOT .htaccess, where `<Directory>`/`<Location>` blocks aren't valid
	 * (Apache only allows `<Files>`/`<FilesMatch>`/mod_rewrite directives in
	 * .htaccess context — a `<Directory>` block here would 500 the whole
	 * site). A path-matching RewriteRule is the correct way to scope a deny
	 * to a subdirectory from the root file, and is the same approach other
	 * WordPress security plugins use for this exact rule.
	 *
	 * The pattern is the uploads URL path relative to the home URL, which is
	 * what a RewriteRule in the root .htaccess (core writes it next to the
	 * home page) is matched against. Measuring filesystem paths from ABSPATH
	 * skipped the rule whenever WordPress lives in its own directory, since
	 * uploads then sit beside core rather than inside it.
	 *
	 * The extension may be followed by `/` as well as end the path:
	 * `x.php/anything` still runs x.php on Apache (PATH_INFO).
	 */
	public function block_uploads_php_execution($rules)
	{
		// Read each time the rules are built; see XMLRPC::block_xmlrpc_requests().
		if (! (bool) apply_filters('siratik_baseline_write_htaccess_rules', true)) {
			return $rules;
		}

		$uploads  = wp_get_upload_dir();
		$relative = $this->relative_from_urls($uploads) ?? $this->relative_from_paths($uploads);

		// Plain path characters only: Apache's config parser reads
		// backslashes and other specials its own way, which could make the
		// rule miss or break the whole .htaccess.
		if (null === $relative || 1 !== preg_match('#^[A-Za-z0-9._~/ -]+$#', $relative)) {
			return $rules;
		}

		// A space would split the RewriteRule line.
		$pattern = str_replace(' ', '\\s', preg_quote($relative, '#'));

		return "
		# Block PHP execution in uploads
		<IfModule mod_rewrite.c>
			RewriteEngine On
			RewriteRule ^{$pattern}/.*\\.(?:php\\d*|phtml|pht|phar)(?:/|$) - [F,L,NC]
		</IfModule>\n\n" . $rules;
	}

	/**
	 * Uploads URL path relative to the home URL path.
	 *
	 * Only when uploads are served from the site's own host (www and apex
	 * count as the same): a CDN URL's path says nothing about where the
	 * files sit on this server.
	 *
	 * @param array $uploads wp_get_upload_dir() result.
	 * @return string|null Null when uploads aren't under the home path.
	 */
	private function relative_from_urls(array $uploads): ?string
	{
		$base_url = wp_parse_url((string) ($uploads['baseurl'] ?? ''));
		$home_url = wp_parse_url(home_url('/'));

		if (!is_array($base_url) || !is_array($home_url)) {
			return null;
		}

		$host = static fn (array $url): string => (string) preg_replace('/^www\./', '', strtolower((string) ($url['host'] ?? '')));
		if ($host($base_url) !== $host($home_url)) {
			return null;
		}

		// mod_rewrite matches the decoded path.
		$base_path = trailingslashit(rawurldecode((string) ($base_url['path'] ?? '')));
		$home_path = trailingslashit(rawurldecode((string) ($home_url['path'] ?? '')));

		return self::relative_path($base_path, $home_path);
	}

	/**
	 * Uploads directory relative to the home directory, for when the uploads
	 * URL points elsewhere (e.g. a CDN) but the files still sit under the
	 * site root.
	 *
	 * @param array $uploads wp_get_upload_dir() result.
	 * @return string|null Null when uploads aren't under the home directory.
	 */
	private function relative_from_paths(array $uploads): ?string
	{
		if (!function_exists('get_home_path')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$basedir   = trailingslashit(wp_normalize_path((string) ($uploads['basedir'] ?? '')));
		$home_path = trailingslashit(wp_normalize_path(get_home_path()));

		return self::relative_path($basedir, $home_path);
	}

	/**
	 * $path relative to $root (both with trailing slashes), or null when it
	 * isn't inside it.
	 */
	private static function relative_path(string $path, string $root): ?string
	{
		if (!str_starts_with($path, $root)) {
			return null;
		}

		$relative = trim(substr($path, strlen($root)), '/');

		return '' === $relative ? null : $relative;
	}
}
