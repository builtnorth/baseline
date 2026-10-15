<?php

declare(strict_types=1);

/**
 * ------------------------------------------------------------------
 * Login
 * ------------------------------------------------------------------
 *
 * Enhance login security
 *
 * @package WPBaseline
 * @since 2.1.0
 */

namespace Siratik\Baseline\Security;

// Don't load directly.
defined('ABSPATH') || defined('WP_CLI') || exit;

class Login
{
	/**
	 * Whether the current login attempt used a username instead of an email.
	 */
	private bool $username_login_attempt = false;

	/**
	 * Error codes that cause WordPress to repopulate the login field after failure.
	 *
	 * @see wp-login.php
	 */
	private const REPOPULATING_ERROR_CODES = [
		'incorrect_password',
		'empty_password',
	];

	/**
	 * Whether sign-in requires an email address (usernames are rejected).
	 */
	private bool $email_only = false;

	/**
	 * Initialize the class
	 *
	 * Two decisions:
	 * - `siratik_baseline_login_security`: email-only sign-in, with the
	 *   "Email address" label and login form tweaks.
	 * - `siratik_baseline_generic_login_errors`: one generic error for failed
	 *   sign-ins and a cleared login field. Follows the email-only decision
	 *   unless filtered.
	 */
	public function init()
	{
		$this->email_only = (bool) apply_filters('siratik_baseline_login_security', true);

		if ($this->email_only) {
			add_filter('authenticate', [$this, 'prevent_username_login'], 30, 3);
			add_action('login_init', [$this, 'register_login_label_filter']);
			add_action('login_enqueue_scripts', [$this, 'customize_login_form']);
		}

		if ((bool) apply_filters('siratik_baseline_generic_login_errors', $this->email_only)) {
			add_filter('wp_login_errors', [$this, 'prevent_login_field_repopulation'], 10, 2);
			add_filter('login_errors', [$this, 'generic_login_error']);
		}
	}

	/**
	 * Prevents username login
	 */
	public function prevent_username_login($user, $username, $password)
	{
		// Let core handle empty credentials on initial GET (wp_signon runs before the form).
		if ($username === '' || $username === null) {
			return $user;
		}

		if (is_email($username)) {
			return $user;
		}

		$this->username_login_attempt = true;

		return new \WP_Error('invalid_email', $this->username_login_error_message());
	}

	/**
	 * Prevents WordPress from repopulating the login field after failed sign-in.
	 *
	 * Core only keeps the submitted value for {@see self::REPOPULATING_ERROR_CODES},
	 * which leaks whether an email is registered when combined with a generic error message.
	 *
	 * @param \WP_Error $errors      Login errors.
	 * @param string    $redirect_to Redirect destination URL.
	 */
	public function prevent_login_field_repopulation($errors, $redirect_to)
	{
		unset($redirect_to);

		if (!$errors instanceof \WP_Error || !$errors->has_errors()) {
			return $errors;
		}

		if (!in_array($errors->get_error_code(), self::REPOPULATING_ERROR_CODES, true)) {
			return $errors;
		}

		return new \WP_Error('authentication_failed', $errors->get_error_message());
	}

	/**
	 * Returns a generic login error message, naming usernames only when they are allowed.
	 *
	 * @param string $errors HTML error messages from the login screen.
	 */
	public function generic_login_error($errors)
	{
		if ($errors === '') {
			return $errors;
		}

		if ($this->username_login_attempt) {
			$this->username_login_attempt = false;

			return $this->username_login_error_message();
		}

		if ($this->email_only) {
			return __('The email address or password you entered is incorrect.', 'siratik-baseline');
		}

		return __('The username, email address or password you entered is incorrect.', 'siratik-baseline');
	}

	/**
	 * Replaces the default "Username or Email Address" label on the login screen.
	 */
	public function register_login_label_filter(): void
	{
		add_filter('gettext', [$this, 'filter_login_username_label'], 10, 3);
	}

	/**
	 * @param string $translation Translated text.
	 * @param string $text        Original text.
	 * @param string $domain      Text domain.
	 */
	public function filter_login_username_label($translation, $text, $domain)
	{
		if ($domain === 'default' && $text === 'Username or Email Address') {
			return __('Email Address', 'siratik-baseline');
		}

		return $translation;
	}

	/**
	 * Customizes login form UX for email-only sign-in.
	 */
	public function customize_login_form(): void
	{
		$placeholder = wp_json_encode(__('Email address', 'siratik-baseline'));

		wp_add_inline_script(
			'login',
			'var userLogin = document.getElementById("user_login");
			var userPass = document.getElementById("user_pass");
			if (userLogin) {
				userLogin.autocomplete = "off";
				userLogin.placeholder = ' . $placeholder . ';
			}
			if (userPass) {
				userPass.autocomplete = "off";
			}'
		);
	}

	/**
	 * Error shown when a username is used instead of an email address.
	 */
	private function username_login_error_message(): string
	{
		return __('Please sign in with your email address, not a username.', 'siratik-baseline');
	}
}
