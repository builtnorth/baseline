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
	 * Whether the authenticate result was already a user before core's
	 * password callbacks (priority 20) ran, i.e. another plugin signed in.
	 */
	private bool $user_before_core = false;

	/**
	 * Whether the authenticate result was a user right after core's
	 * password callbacks ran.
	 */
	private bool $user_after_core = false;

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
			// 19 and 21 bracket core's password callbacks (20), so the check at
			// 30 can tell a core username+password sign-in from one another
			// plugin (LDAP, single sign-on) produced.
			add_filter('authenticate', [$this, 'note_user_before_core'], 19, 1);
			add_filter('authenticate', [$this, 'note_user_after_core'], 21, 1);
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
	 * Filter: authenticate (19). Notes whether a user was signed in before
	 * core's password callbacks.
	 *
	 * @param mixed $user Authentication result so far.
	 * @return mixed Unchanged.
	 */
	public function note_user_before_core($user)
	{
		$this->user_before_core = $user instanceof \WP_User;
		$this->user_after_core  = false;

		return $user;
	}

	/**
	 * Filter: authenticate (21). Notes whether a user was signed in once
	 * core's password callbacks ran.
	 *
	 * @param mixed $user Authentication result so far.
	 * @return mixed Unchanged.
	 */
	public function note_user_after_core($user)
	{
		$this->user_after_core = $user instanceof \WP_User;

		return $user;
	}

	/**
	 * Prevents username login.
	 *
	 * Only the WordPress password sign-in is held to email-only: a user that
	 * another authenticate callback produced (before core's password check,
	 * or between it and this one) is left alone, so LDAP and single sign-on
	 * plugins that sign in by username keep working.
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

		if ($user instanceof \WP_User && ($this->user_before_core || ! $this->user_after_core)) {
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
