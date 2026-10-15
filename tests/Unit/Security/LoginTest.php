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
			[ 'authenticate', 'login_init', 'login_enqueue_scripts', 'wp_login_errors', 'login_errors' ],
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
			[ 'authenticate', 'login_init', 'login_enqueue_scripts' ],
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
}
