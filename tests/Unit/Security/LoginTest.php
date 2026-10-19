<?php
/**
 * Tests for the login hardening split: email-only sign-in and generic login errors.
 *
 * @package Siratik\Baseline\Tests\Unit\Security
 */

namespace Siratik\Baseline\Tests\Unit\Security;

use BuiltNorth\WPMockStandIns\WPMockTestCase;
use Siratik\Baseline\Security\Login;
use WP_Mock;

/**
 * Login test case
 */
class LoginTest extends WPMockTestCase {

	public function setUp(): void {
		parent::setUp();
		$GLOBALS['siratik_baseline_test_hooks']   = [];
		$GLOBALS['siratik_baseline_test_filters'] = [];
	}

	public function tearDown(): void {
		unset( $GLOBALS['siratik_baseline_test_hooks'], $GLOBALS['siratik_baseline_test_filters'] );
		parent::tearDown();
	}

	/**
	 * @return array<int, string> Hook names registered by init().
	 */
	private function registered_hooks( Login $login ): array {
		$login->init();

		return array_map(
			static fn ( array $hook ): string => $hook[1],
			$GLOBALS['siratik_baseline_test_hooks']
		);
	}

	/**
	 * Unfiltered, both decisions are on: existing behavior is unchanged.
	 */
	public function test_both_on_by_default(): void {
		$hooks = $this->registered_hooks( new Login() );

		$this->assertSame(
			[ 'authenticate', 'authenticate', 'authenticate', 'login_init', 'login_enqueue_scripts', 'wp_login_errors', 'login_errors' ],
			$hooks
		);
	}

	/**
	 * Generic errors follow email-only when the new filter isn't set.
	 */
	public function test_generic_errors_follow_email_only_when_unfiltered(): void {
		$GLOBALS['siratik_baseline_test_filters']['siratik_baseline_login_security'] = false;

		$this->assertSame( [], $this->registered_hooks( new Login() ) );
	}

	/**
	 * Generic errors on with usernames allowed: no email-only hooks.
	 */
	public function test_generic_errors_without_email_only(): void {
		$GLOBALS['siratik_baseline_test_filters']['siratik_baseline_login_security']      = false;
		$GLOBALS['siratik_baseline_test_filters']['siratik_baseline_generic_login_errors'] = true;

		$this->assertSame( [ 'wp_login_errors', 'login_errors' ], $this->registered_hooks( new Login() ) );
	}

	/**
	 * Email-only on with generic errors off: core's error messages stay.
	 */
	public function test_email_only_without_generic_errors(): void {
		$GLOBALS['siratik_baseline_test_filters']['siratik_baseline_generic_login_errors'] = false;

		$this->assertSame(
			[ 'authenticate', 'authenticate', 'authenticate', 'login_init', 'login_enqueue_scripts' ],
			$this->registered_hooks( new Login() )
		);
	}

	/**
	 * With email-only sign-in, the generic message names the email address only.
	 */
	public function test_generic_message_with_email_only(): void {
		$login = new Login();
		$login->init();

		$this->assertSame(
			'The email address or password you entered is incorrect.',
			$login->generic_login_error( '<strong>Error:</strong> Unknown email address.' )
		);
	}

	/**
	 * With usernames allowed, the generic message names usernames too.
	 */
	public function test_generic_message_with_usernames_allowed(): void {
		$GLOBALS['siratik_baseline_test_filters']['siratik_baseline_login_security']      = false;
		$GLOBALS['siratik_baseline_test_filters']['siratik_baseline_generic_login_errors'] = true;

		$login = new Login();
		$login->init();

		$this->assertSame(
			'The username, email address or password you entered is incorrect.',
			$login->generic_login_error( '<strong>Error:</strong> Unknown username.' )
		);
	}

	/**
	 * In email-only mode a username attempt keeps its specific message.
	 */
	public function test_username_attempt_message_in_email_only_mode(): void {
		WP_Mock::userFunction( 'is_email' )->andReturn( false );

		$login = new Login();
		$login->init();
		$error = $login->prevent_username_login( null, 'admin', 'secret' );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame(
			'Please sign in with your email address, not a username.',
			$login->generic_login_error( 'Some error' )
		);
		// The flag is spent: the next failure gets the generic message.
		$this->assertSame(
			'The email address or password you entered is incorrect.',
			$login->generic_login_error( 'Some error' )
		);
	}

	/**
	 * No error, nothing to replace.
	 */
	public function test_empty_errors_pass_through(): void {
		$login = new Login();
		$login->init();

		$this->assertSame( '', $login->generic_login_error( '' ) );
	}

	/**
	 * Run the authenticate chain as core would: the check before core's
	 * password callbacks (19), their result, anything in between, then 30.
	 *
	 * @param mixed $before Result before core (another plugin at < 19).
	 * @param mixed $core   Result after core's callbacks (20).
	 * @param mixed $later  Result after callbacks between 21 and 30.
	 * @return mixed
	 */
	private function authenticate( Login $login, $before, $core, $later, string $username ) {
		$login->note_user_before_core( $before );
		$login->note_user_after_core( $core );

		return $login->prevent_username_login( $later, $username, 'secret' );
	}

	/**
	 * Core's username+password sign-in is still held to email-only.
	 */
	public function test_core_username_sign_in_is_refused(): void {
		WP_Mock::userFunction( 'is_email' )->andReturn( false );
		$login = new Login();
		$user  = new \WP_User();

		$this->assertInstanceOf( \WP_Error::class, $this->authenticate( $login, null, $user, $user, 'admin' ) );
	}

	/**
	 * A plugin that signs in by username after core (LDAP, single sign-on)
	 * is left alone.
	 */
	public function test_a_later_plugin_sign_in_by_username_is_kept(): void {
		WP_Mock::userFunction( 'is_email' )->andReturn( false );
		$login = new Login();
		$user  = new \WP_User();

		$this->assertSame( $user, $this->authenticate( $login, null, new \WP_Error( 'incorrect_password', 'x' ), $user, 'jdoe' ) );
	}

	/**
	 * A plugin that signed in before core's password check is left alone.
	 */
	public function test_an_earlier_plugin_sign_in_by_username_is_kept(): void {
		WP_Mock::userFunction( 'is_email' )->andReturn( false );
		$login = new Login();
		$user  = new \WP_User();

		$this->assertSame( $user, $this->authenticate( $login, $user, $user, $user, 'jdoe' ) );
	}

	/**
	 * Email sign-ins pass whatever produced them.
	 */
	public function test_email_sign_in_is_kept(): void {
		WP_Mock::userFunction( 'is_email' )->andReturn( true );
		$login = new Login();
		$user  = new \WP_User();

		$this->assertSame( $user, $this->authenticate( $login, null, $user, $user, 'jdoe@example.com' ) );
	}

	/**
	 * A failed username attempt still gets the email-only error.
	 */
	public function test_failed_username_attempt_gets_email_only_error(): void {
		WP_Mock::userFunction( 'is_email' )->andReturn( false );
		$login = new Login();

		$result = $this->authenticate( $login, null, null, new \WP_Error( 'incorrect_password', 'x' ), 'admin' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_email', $result->get_error_code() );
	}
}
