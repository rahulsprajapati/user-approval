<?php
/**
 * Test for User_Approval.
 *
 * @package user-approval
 */

namespace User_Approval\Tests;

use User_Approval;
use WP_UnitTestCase;

/**
 * User_Approval test case.
 */
class Test_User_Approval extends WP_UnitTestCase {

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
		self::$contributor = self::factory()->user->create_and_get( [ 'role' => 'contributor' ] );
		self::$editor = self::factory()->user->create_and_get( [ 'role' => 'editor' ] );
	}

	/**
	 * Test bootstrap.
	 */
	public function test_bootstrap() {

		User_Approval\bootstrap();

		$this->assertEquals( 10, has_action( 'plugins_loaded', 'User_Approval\\load_textdomain' ) );
	}


	/**
	 * Test for get_default_user_role function.
	 */
	public function test_get_default_user_role() {
		update_option( 'default_role', 'editor');
		$this->assertNotEquals( self::$default_user_role, User_Approval\get_default_user_role() );

		update_option( 'default_role', self::$default_user_role );
		$this->assertEquals( self::$default_user_role, User_Approval\get_default_user_role() );
	}

	/**
	 * Test for is_default_role_user function.
	 */
	public function test_is_default_role_user() {

		$this->assertFalse( User_Approval\is_default_role_user( self::$editor ) );
		$this->assertTrue( User_Approval\is_default_role_user( self::$contributor ) );
	}

	/**
	 * Test case for get_role_names function.
	 */
	public function test_get_role_names() {

		$this->assertContains( self::$default_user_role, array_keys( User_Approval\get_role_names() ) );
	}

	/**
	 * Test case for get_pre_approved_user_roles function.
	 */
	public function test_get_pre_approved_user_roles() {

		$this->assertNotContains( self::$default_user_role, array_keys( User_Approval\get_pre_approved_user_roles() ) );
	}

	/**
	 * Test case for get_user_status function.
	 */
	public function test_get_user_status() {

		$this->assertTrue( is_array( User_Approval\get_user_status( 'all' ) ) );
		$this->assertTrue( is_string( User_Approval\get_user_status( 'blocked' ) ) );
		$this->assertTrue( is_string( User_Approval\get_user_status( '' ) ) );
	}
}
