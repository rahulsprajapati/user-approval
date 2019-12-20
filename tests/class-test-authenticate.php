<?php
/**
 * Test for User_Approval user account authentication.
 *
 * @package user-approval
 */

namespace User_Approval\Tests;

use User_Approval\Authenticate;
use WP_Error;
use WP_UnitTestCase;
use WP_User;

/**
 * User_Approval\Authenticate test case.
 */
class Test_Authenticate extends WP_UnitTestCase {

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
	}

	/**
	 * Test bootstrap.
	 */
	public function test_bootstrap() {

		Authenticate\bootstrap();

		$this->assertEquals( 1, has_action( 'lostpassword_post', 'User_Approval\\Authenticate\\block_non_approved_user_request' ) );
		$this->assertEquals( 10, has_filter( 'wp_authenticate_user', 'User_Approval\\Authenticate\\authenticate_user_by_status' ) );
	}

	/**
	 * Test case for block_non_approved_user_request() function.
	 * Note: Can't use retrieve_password() as it's in wp-login.php file and we can't load it inside unit test.
	 * So used `lostpassword_post` hook for test.
	 */
	public function test_block_non_approved_user_request() {
		$_POST['user_login'] = 'newuser@dummy.email';

		// TEST CASE - 1.
		$errors = new WP_Error();

		$errors->add(
			'test_error',
			__( '<strong>ERROR</strong>: Test Error', 'user-approval' )
		);

		// Test case if error is already there before we check for user status.
		do_action( 'lostpassword_post', $errors );

		$this->assertEquals( [ 'test_error' ], $errors->get_error_codes() );

		// TEST CASE - 2.
		$errors = new WP_Error();

		// Test case when user login value is incorrect and user doesn't exists.
		do_action( 'lostpassword_post', $errors );

		$this->assertEmpty( $errors->get_error_codes() );

		// TEST CASE - 3.
		$errors = new WP_Error();

		$_POST['user_login'] = self::$editor->user_email;

		// Test case when user login role is other then the default user role.
		do_action( 'lostpassword_post', $errors );

		$this->assertEmpty( $errors->get_error_codes() );

		// TEST CASE - 4.
		$errors = new WP_Error();

		$_POST['user_login'] = self::$contributor->user_email;

		// Test case when user status is not approved by admins.
		do_action( 'lostpassword_post', $errors );

		$this->assertEquals( [ 'unapproved_user' ], $errors->get_error_codes() );

		// TEST CASE - 5.
		$errors = new WP_Error();

		update_user_meta( self::$contributor->ID, 'aj_user_status', 'approved' );

		// Test case when user login is approved by admins.
		do_action( 'lostpassword_post', $errors );

		$this->assertEmpty( $errors->get_error_codes() );
	}

	/**
	 * Test case for authenticate_user_by_status() function.
	 */
	public function test_authenticate_user_by_status() {

		// Test case for other then default/pre-approved user role.
		$contributor_auth = wp_authenticate_username_password(
			null,
			self::$editor->user_login,
			'editor_pass'
		);

		$this->assertTrue( $contributor_auth instanceof WP_User );

		// Test case for pending approval.
		$contributor_auth = wp_authenticate_username_password(
			null,
			self::$contributor->user_login,
			'contributor_pass'
		);

		$this->assertTrue( is_wp_error( $contributor_auth ) );

		$this->assertContains( 'pending_approval', $contributor_auth->get_error_codes() );

		update_user_meta( self::$contributor->ID, 'aj_user_status', 'blocked' );

		// Test case for blocked user.
		$contributor_auth = wp_authenticate_username_password(
			null,
			self::$contributor->user_login,
			'contributor_pass'
		);

		$this->assertTrue( is_wp_error( $contributor_auth ) );

		$this->assertContains( 'blocked_access', $contributor_auth->get_error_codes() );

		update_user_meta( self::$contributor->ID, 'aj_user_status', 'approved' );

		// Test case for approved user.
		$contributor_auth = wp_authenticate_username_password(
			null,
			self::$contributor->user_login,
			'contributor_pass'
		);

		$this->assertTrue( $contributor_auth instanceof WP_User );
	}
}
