<?php

/**
 * ------------------------------------------------------------------
 * AuthorEnumeration
 * ------------------------------------------------------------------
 *
 * Blocks username enumeration via ?author=N
 *
 * @package WPBaseline
 * @since 2.2.0
 */

namespace Siratik\Baseline\Security;

// Don't load directly.
defined('ABSPATH') || defined('WP_CLI') || exit;

class AuthorEnumeration
{
	/**
	 * Initialize the class.
	 */
	public function init()
	{
		if ((bool) apply_filters('siratik_baseline_block_author_enumeration', true)) {
			// Priority 0: WordPress core's own redirect_canonical() runs on
			// template_redirect at the default priority (10) and would
			// otherwise 301 straight to /author/username/ before this ever
			// runs, revealing the username the request was trying to block.
			add_action('template_redirect', [$this, 'block_numeric_author_lookup'], 0);
			// More places that print a user's slug.
			if ((bool) apply_filters('siratik_baseline_remove_users_sitemap', true)) {
				add_filter('wp_sitemaps_add_provider', [$this, 'remove_users_sitemap'], 10, 2);
			}
			add_filter('oembed_response_data', [$this, 'hide_oembed_author_url']);
			add_filter('comment_class', [$this, 'remove_comment_author_class'], 10, 4);
		}
	}

	/**
	 * WordPress resolves `?author=N` requests to the author's archive URL,
	 * revealing a valid username for anyone enumerating IDs. The pretty
	 * permalink form (`/author/username/`) never sets `author` as a request
	 * var — it resolves to `author_name` — so checking the parsed `author`
	 * var targets only the numeric-ID lookup, not legitimate author archives.
	 *
	 * Reads the parsed request rather than $_GET: WordPress also takes
	 * public query vars from a POST body, and core's canonical redirect skips
	 * POST requests, so `POST / author=1` would otherwise render the archive
	 * (its body class prints the author's slug).
	 */
	public function block_numeric_author_lookup()
	{
		// Matches the capability RestAPI.php already requires to list users
		// via the REST endpoint — a logged-in Subscriber has no more
		// legitimate need to enumerate user IDs than an anonymous visitor.
		if (current_user_can('list_users')) {
			return;
		}

		global $wp;
		$author = is_object($wp) ? ($wp->query_vars['author'] ?? '') : '';
		if ('' === $author || null === $author) {
			return;
		}

		if (!is_author()) {
			return;
		}

		wp_die(
			esc_html__('Not found.', 'siratik-baseline'),
			esc_html__('Not Found', 'siratik-baseline'),
			['response' => 404]
		);
	}

	/**
	 * Filter: wp_sitemaps_add_provider. Drops core's users sitemap, which
	 * lists every author's archive URL (and so their slug).
	 *
	 * @param mixed  $provider Sitemap provider.
	 * @param string $name     Provider name.
	 * @return mixed
	 */
	public function remove_users_sitemap($provider, $name)
	{
		return 'users' === $name ? false : $provider;
	}

	/**
	 * Filter: oembed_response_data. Points `author_url` at the home page (core's
	 * own fallback) instead of the author archive, which carries the slug.
	 *
	 * @param mixed $data oEmbed response data.
	 * @return mixed
	 */
	public function hide_oembed_author_url($data)
	{
		if (is_array($data) && isset($data['author_url'])) {
			$data['author_url'] = get_home_url();
		}

		return $data;
	}

	/**
	 * Filter: comment_class. Drops core's `comment-author-{slug}` class from
	 * comments by registered users; `byuser`, `bypostauthor` and any class a
	 * theme passed in stay.
	 *
	 * @param mixed $classes    Comment classes.
	 * @param mixed $css_class  Extra classes requested by the theme.
	 * @param mixed $comment_id Comment ID.
	 * @param mixed $comment    Comment object.
	 * @return mixed
	 */
	public function remove_comment_author_class($classes, $css_class = '', $comment_id = 0, $comment = null)
	{
		if (!is_array($classes) || !is_object($comment) || empty($comment->user_id)) {
			return $classes;
		}

		$user = get_userdata((int) $comment->user_id);
		if (!$user) {
			return $classes;
		}

		$slug_class = 'comment-author-' . sanitize_html_class($user->user_nicename, $comment->user_id);

		return array_values(array_diff($classes, [$slug_class]));
	}
}
