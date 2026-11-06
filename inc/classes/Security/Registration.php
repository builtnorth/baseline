<?php

declare(strict_types=1);

/**
 * ------------------------------------------------------------------
 * Registration
 * ------------------------------------------------------------------
 *
 * Stops public registration being used to send spam. Bots sign up with a
 * message as the username, and WordPress copies it into the new-user email
 * sent to the admin. This keeps the username out of that email and refuses
 * usernames shaped like a message or a link.
 *
 * @package WPBaseline
 */

namespace Siratik\Baseline\Security;

// Don't load directly.
defined('ABSPATH') || defined('WP_CLI') || exit;

class Registration
{
	/**
	 * Most words a username may have. Real names rarely pass four; spam
	 * messages are sentences.
	 */
	private const MAX_WORDS = 4;

	/**
	 * Initialize the class.
	 */
	public function init()
	{
		if (! (bool) apply_filters('siratik_baseline_registration_security', true)) {
			return;
		}

		add_filter('wp_new_user_notification_email_admin', [$this, 'filter_admin_email'], 10, 3);
		add_filter('registration_errors', [$this, 'validate_username'], 10, 3);
	}

	/**
	 * Filter: wp_new_user_notification_email_admin. Rebuilds the body
	 * without the username (text the registrant typed), leaving the email
	 * address (checked by is_email()) and a link to the user's profile.
	 *
	 * @param mixed    $email    Email args (to, subject, message, headers).
	 * @param \WP_User $user     New user.
	 * @param string   $blogname Site title.
	 * @return mixed
	 */
	public function filter_admin_email($email, $user, $blogname)
	{
		if (! is_array($email) || ! $user instanceof \WP_User) {
			return $email;
		}

		/* translators: %s: Site title. */
		$message  = sprintf(__('New user registration on your site %s.', 'siratik-baseline'), $blogname) . "\r\n\r\n";
		/* translators: %s: Email address. */
		$message .= sprintf(__('Email: %s', 'siratik-baseline'), $user->user_email) . "\r\n\r\n";
		$message .= admin_url('user-edit.php?user_id=' . (int) $user->ID) . "\r\n";

		$email['message'] = $message;

		return $email;
	}

	/**
	 * Filter: registration_errors. Refuses usernames that read like a link
	 * or a message. Runs on the sanitized login, which only keeps letters,
	 * digits, spaces and `_ . - @`.
	 *
	 * @param mixed  $errors               Registration errors.
	 * @param string $sanitized_user_login Sanitized username.
	 * @param string $user_email           Email address.
	 * @return mixed
	 */
	public function validate_username($errors, $sanitized_user_login, $user_email)
	{
		unset($user_email);

		if (! $errors instanceof \WP_Error || ! is_string($sanitized_user_login)) {
			return $errors;
		}

		if (self::is_spam_shaped($sanitized_user_login)) {
			$errors->add(
				'invalid_username',
				__('<strong>Error:</strong> Please choose a username without links or long phrases.', 'siratik-baseline')
			);
		}

		return $errors;
	}

	/**
	 * Whether a username looks like a link or a message.
	 *
	 * @param string $login Sanitized username.
	 */
	public static function is_spam_shaped(string $login): bool
	{
		$login = strtolower(trim($login));

		if ($login === '') {
			return false;
		}

		if (str_contains($login, 'http') || str_contains($login, 'www.')) {
			return true;
		}

		$words = preg_split('/\s+/', $login, -1, PREG_SPLIT_NO_EMPTY);

		return is_array($words) && count($words) > self::MAX_WORDS;
	}
}
