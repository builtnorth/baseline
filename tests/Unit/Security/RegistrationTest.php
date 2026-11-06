<?php
/**
 * Tests for the registration spam hardening.
 *
 * @package Siratik\Baseline\Tests\Unit\Security
 */

declare(strict_types=1);

namespace Siratik\Baseline\Tests\Unit\Security;

use BuiltNorth\WPMockStandIns\WPMockTestCase;
use Siratik\Baseline\Security\Registration;
use WP_Error;
use WP_Mock;
use WP_User;

/**
 * Spam bots put their message in the username; it must not reach the admin
 * inbox, and message-shaped usernames are refused.
 */
class RegistrationTest extends WPMockTestCase {

	public function setUp(): void {
		parent::setUp();
		$GLOBALS['siratik_baseline_test_hooks']   = [];
		$GLOBALS['siratik_baseline_test_filters'] = [];
		WP_Mock::userFunction( '__' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'admin_url' )->andReturnUsing(
			static fn ( string $path = '' ): string => 'https://example.test/wp-admin/' . $path
		);
	}

	public function tearDown(): void {
		unset( $GLOBALS['siratik_baseline_test_hooks'], $GLOBALS['siratik_baseline_test_filters'] );
		parent::tearDown();
	}

	public function test_gate_decides_whether_hooks_are_added(): void {
		$GLOBALS['siratik_baseline_test_filters']['siratik_baseline_registration_security'] = false;
		( new Registration() )->init();
		$this->assertSame( [], $GLOBALS['siratik_baseline_test_hooks'] );

		$GLOBALS['siratik_baseline_test_filters']['siratik_baseline_registration_security'] = true;
		( new Registration() )->init();
		$hooks = array_map( static fn ( array $hook ): string => $hook[1], $GLOBALS['siratik_baseline_test_hooks'] );
		$this->assertSame( [ 'wp_new_user_notification_email_admin', 'registration_errors' ], $hooks );
	}

	public function test_admin_email_has_email_and_profile_link_but_no_username(): void {
		$login = 'Buy cheap pills now visit spam example';
		$user  = new WP_User( [ 'ID' => 42, 'user_login' => $login, 'user_email' => 'bot@example.com' ] );
		$email = [
			'to'      => 'admin@example.test',
			'subject' => 'New User',
			'message' => "Username: {$login}\r\n",
			'headers' => '',
		];

		$result = ( new Registration() )->filter_admin_email( $email, $user, 'Site' );

		$this->assertStringContainsString( 'bot@example.com', $result['message'] );
		$this->assertStringContainsString( 'wp-admin/user-edit.php?user_id=42', $result['message'] );
		$this->assertStringNotContainsString( $login, $result['message'] );
		$this->assertStringNotContainsString( 'Buy cheap', $result['message'] );
	}

	/**
	 * @dataProvider spam_logins
	 */
	public function test_spam_shaped_login_is_refused_and_other_errors_kept( string $login ): void {
		$errors = new WP_Error( 'empty_email', 'Email missing' );

		$result = ( new Registration() )->validate_username( $errors, $login, 'a@example.com' );

		$this->assertSame( $errors, $result );
		$this->assertNotSame( '', $result->get_error_message( 'invalid_username' ) );
		$this->assertSame( 'Email missing', $result->get_error_message( 'empty_email' ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function spam_logins(): array {
		return [
			'http link'  => [ 'Visit HTTPS example.com' ],
			'www link'   => [ 'WWW.spam.example' ],
			'five words' => [ 'cheap pills buy now here' ],
		];
	}

	/**
	 * @dataProvider normal_logins
	 */
	public function test_normal_login_is_accepted( string $login ): void {
		$errors = new WP_Error();

		$result = ( new Registration() )->validate_username( $errors, $login, 'a@example.com' );

		$this->assertFalse( $result->has_errors() );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function normal_logins(): array {
		return [
			'single'      => [ 'john' ],
			'two words'   => [ 'John Smith' ],
			'email'       => [ 'john.smith@example.com' ],
			'three words' => [ 'Mary Ann Smith' ],
			'four words'  => [ 'Mary Ann De Vries' ],
		];
	}
}
