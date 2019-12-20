<?php
/**
 * Test for User_Approval user registration.
 *
 * @package user-approval
 */

namespace User_Approval\Tests;

use User_Approval\Registration;
use WP_Error;
use WP_UnitTestCase;
use function User_Approval\get_default_user_role;
use function User_Approval\get_role_names;

/**
 * User_Approval\Registration test case.
 */
class Test_Registration extends WP_UnitTestCase {

	/**
	 * User object.
	 *
	 * @var \WP_User User object.
	 */
	protected static $contributor;

	/**
	 * User object.
	 *
	 * @var \WP_User User object.
	 */
	protected static $editor;

	/**
	 * User object.
	 *
	 * @var \WP_User User object.
	 */
	protected static $admin;

	/**
	 * Default User role
	 *
	 * @var string Default user role.
	 */
	protected static $default_user_role = 'contributor';

	/**
	 * Setup.
	 */
	public static function wpSetUpBeforeClass() {
		update_option( 'default_role', self::$default_user_role );

		self::$contributor = self::factory()->user->create_and_get(
			[
				'role'      => 'contributor',
				'user_pass' => 'contributor_pass',
			]
		);

		self::$editor = self::factory()->user->create_and_get(
			[
				'role'      => 'editor',
				'user_pass' => 'editor_pass',
			]
		);

		self::$admin = self::factory()->user->create_and_get(
			[
				'role'      => 'administrator',
				'user_pass' => 'administrator_pass',
			]
		);
	}

	/**
	 * Test bootstrap.
	 */
	public function test_bootstrap() {

		Registration\bootstrap();

		$this->assertNotEquals( 10, has_action( 'register_new_user', 'wp_send_new_user_notifications' ) );

		$this->assertEquals( 10, has_action( 'register_new_user', 'User_Approval\\Registration\\send_notification_to_allowed_users' ) );

		$this->assertEquals( 50, has_filter( 'wp_new_user_notification_email_admin', 'User_Approval\\Registration\\update_new_user_admin_email' ) );

		$this->assertEquals( 10, has_filter( 'wp_login_errors', 'User_Approval\\Registration\\update_registered_user_message' ) );
	}

	/**
	 * Test case for send_notification_to_allowed_users function.
	 *
	 * @covers \User_Approval\Registration\send_notification_to_allowed_users()
	 * @covers \User_Approval\Registration\update_new_user_admin_email()
	 */
	public function test_send_notification_to_allowed_users() {
		reset_phpmailer_instance();

		$email = tests_retrieve_phpmailer_instance();

		$admin_email = get_option( 'admin_email' );

		Registration\send_notification_to_allowed_users( self::$editor->ID );

		$this->assertIsObject( $email->get_sent( 0 ) );
		$this->assertIsObject( $email->get_sent( 1 ) );

		$admin_recipient = $email->get_recipient( 'to' );

		$this->assertObjectHasAttribute( 'address', $admin_recipient );

		$this->assertEquals( $admin_email, $admin_recipient->address );

		$editor_recipient = $email->get_recipient( 'to', 1 );

		$this->assertObjectHasAttribute( 'address', $editor_recipient );

		$this->assertEquals( self::$editor->user_email, $editor_recipient->address );

		reset_phpmailer_instance();

		Registration\send_notification_to_allowed_users( self::$contributor->ID );

		$email = tests_retrieve_phpmailer_instance();

		$this->assertIsObject( $email->get_sent( 0 ) );

		$this->assertFalse( $email->get_sent( 1 ) );

		$admin_recipient = $email->get_recipient( 'to' );

		$this->assertObjectHasAttribute( 'address', $admin_recipient );

		$this->assertEquals( $admin_email, $admin_recipient->address );

		$contributor_recipient = $email->get_recipient( 'to', 1 );

		$this->assertFalse( $contributor_recipient );

		$user_roles = get_role_names();
		$user_role  = $user_roles[ get_default_user_role() ];
		$blogname   = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

		$message = sprintf(
			/* translators: 1: User role label, 2: Site title. */
			esc_html__( 'New %1$s registration on your site %2$s:', 'user-approval' ),
			$user_role,
			$blogname
		);

		$admin_mail_data = $email->get_sent( 0 );

		$this->assertContains( $message, $admin_mail_data->body );

		reset_phpmailer_instance();
	}

	/**
	 * Test case for update_registered_user_message function.
	 * Show message to user after successful registration.
	 */
	public function test_update_registered_user_message() {
		$errors = new WP_Error();

		$errors = apply_filters( 'wp_login_errors', $errors );

		$this->assertEmpty( $errors->get_error_codes() );

		$_GET['checkemail'] = 'registered';

		$errors = apply_filters( 'wp_login_errors', $errors );

		$this->assertContains(
			'An email has been sent to the site administrator for account verification.',
			$errors->get_error_message( 'registered' )
		);
	}
}
