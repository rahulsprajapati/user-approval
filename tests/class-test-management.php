<?php
/**
 * Test for User_Approval User Account management.
 *
 * @package user-approval
 */

namespace User_Approval\Tests;

use User_Approval\Management;
use WP_UnitTestCase;
use function User_Approval\get_pre_approved_user_roles;
use function User_Approval\get_user_status;
use const User_Approval\STATUS_APPROVED_NONCE;
use const User_Approval\STATUS_BLOCKED_NONCE;

/**
 * User_Approval\Management test case.
 */
class Test_Management extends WP_UnitTestCase {

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

		Management\bootstrap();

		$this->assertEquals( 1, has_action( 'manage_users_columns', 'User_Approval\\Management\\user_verification_column' ) );

		$this->assertEquals( 10, has_filter( 'manage_users_custom_column', 'User_Approval\\Management\\add_user_verification_status' ) );
		$this->assertEquals( 10, has_filter( 'user_row_actions', 'User_Approval\\Management\\add_user_verification_action' ) );

		$this->assertEquals( 10, has_action( 'admin_action_aj_user_status', 'User_Approval\\Management\\aj_user_status_update' ) );

		$this->assertEquals( 50, has_filter( 'wp_new_user_notification_email', 'User_Approval\\Management\\update_approved_new_user_email' ) );

		$this->assertEquals( 10, has_filter( 'manage_users_extra_tablenav', 'User_Approval\\Management\\add_user_status_filters' ) );

		$this->assertEquals( 100, has_filter( 'users_list_table_query_args', 'User_Approval\\Management\\filter_user_list_by_status' ) );
	}

	/**
	 * Test case for user_verification_column function.
	 */
	public function test_user_verification_column() {

		// Call the user list table class to set default columns for users screen.
		_get_list_table( 'WP_Users_List_Table', [ 'screen' => 'users', ] );

		$this->assertArrayHasKey( 'aj_user_status', get_column_headers( 'users' ) );
	}

	/**
	 * Test for add_user_verification_status function.
	 */
	public function test_add_user_verification_status() {
		$wp_list_table = _get_list_table( 'WP_Users_List_Table', [ 'screen' => 'users', ] );
		$user_row = $wp_list_table->single_row( self::$contributor );

		$this->assertContains( '<td class=\'aj_user_status column-aj_user_status\' data-colname="Status">Pending</td>', $user_row );

		update_user_meta( self::$contributor->ID, 'aj_user_status', 'blocked' );

		$user_row = $wp_list_table->single_row( self::$contributor );

		$this->assertContains( '<td class=\'aj_user_status column-aj_user_status\' data-colname="Status">Blocked</td>', $user_row );

		update_user_meta( self::$contributor->ID, 'aj_user_status', 'approved' );

		$user_row = $wp_list_table->single_row( self::$contributor );

		$this->assertContains( '<td class=\'aj_user_status column-aj_user_status\' data-colname="Status">Approved</td>', $user_row );
	}

	/**
	 * Get user verification links to compare with output data of user list table.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array
	 */
	private function get_verification_action_items( $user_id ) {
		$query_args = [
			'user'   => $user_id,
			'action' => 'aj_user_status',
			'status' => 'approved',
		];

		// Approve action link.
		$approve_link = add_query_arg( $query_args );
		$approve_link = remove_query_arg( [ 'new_role' ], $approve_link );
		$approve_link = wp_nonce_url( $approve_link, STATUS_APPROVED_NONCE );

		$query_args['status'] = 'blocked';

		// Block action link.
		$block_link = add_query_arg( $query_args );
		$block_link = remove_query_arg( [ 'new_role' ], $block_link );
		$block_link = wp_nonce_url( $block_link, STATUS_BLOCKED_NONCE );

		$approve_action = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $approve_link ),
			get_user_status( 'approved' )
		);

		$block_action = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $block_link ),
			get_user_status( 'blocked' )
		);

		return [
			'approved' => $approve_action,
			'blocked' => $block_action,
		];
	}

	/**
	 * Test case for add_user_verification_action function.
	 */
	public function test_add_user_verification_action() {
		wp_set_current_user( self::$admin->ID );

		$action_items = $this->get_verification_action_items( self::$contributor->ID );

		$user_lists = _get_list_table( 'WP_Users_List_Table', [ 'screen' => 'users', ] );

		$contributor_user_row = $user_lists->single_row( self::$contributor );

		$this->assertContains( $action_items['approved'], $contributor_user_row );
		$this->assertContains( $action_items['blocked'], $contributor_user_row );

		update_user_meta( self::$contributor->ID, 'aj_user_status', 'blocked' );

		$contributor_user_row = $user_lists->single_row( self::$contributor );

		$this->assertContains( $action_items['approved'], $contributor_user_row );
		$this->assertNotContains( $action_items['blocked'], $contributor_user_row );

		update_user_meta( self::$contributor->ID, 'aj_user_status', 'approved' );

		$contributor_user_row = $user_lists->single_row( self::$contributor );

		$this->assertNotContains( $action_items['approved'], $contributor_user_row );
		$this->assertContains( $action_items['blocked'], $contributor_user_row );

		$editor_user_row = $user_lists->single_row( self::$editor );

		$action_items = $this->get_verification_action_items( self::$editor->ID );

		$this->assertNotContains( $action_items['approved'], $editor_user_row );
		$this->assertNotContains( $action_items['blocked'], $editor_user_row );
	}

	/**
	 * Test case for aj_user_status_update function.
	 *
	 * @covers \User_Approval\Management\aj_user_status_update()
	 * @covers \User_Approval\Management\update_approved_new_user_email()
	 * @covers \User_Approval\Management\send_user_blocked_email()
	 */
	public function test_aj_user_status_update() {
		wp_set_current_user( self::$admin->ID );
		reset_phpmailer_instance();

		$email = tests_retrieve_phpmailer_instance();

		$user_status = get_user_meta( self::$contributor->ID, 'aj_user_status', true );

		$this->assertEmpty( $user_status );

		$_GET['user']         = self::$contributor->ID;
		$_GET['status']       = 'blocked';
		$_REQUEST['_wpnonce'] = wp_create_nonce( STATUS_BLOCKED_NONCE );

		Management\aj_user_status_update();

		$user_status = get_user_meta( self::$contributor->ID, 'aj_user_status', true );

		$this->assertEquals( 'blocked', $user_status );

		$blocker_user_email = $email->get_sent( 0 );

		$this->assertContains( 'You have been denied access to', $blocker_user_email->body );

		reset_phpmailer_instance();

		$email = tests_retrieve_phpmailer_instance();

		$_GET['user']         = self::$contributor->ID;
		$_GET['status']       = 'approved';
		$_REQUEST['_wpnonce'] = wp_create_nonce( STATUS_APPROVED_NONCE );

		Management\aj_user_status_update();

		$user_status = get_user_meta( self::$contributor->ID, 'aj_user_status', true );

		$this->assertEquals( 'approved', $user_status );

		$approved_user_email = $email->get_sent( 0 );

		$this->assertContains( 'You have been approved to access', $approved_user_email->body );
	}

	/**
	 * Test case for filter users by uesr status. add_user_status_filters function.
	 */
	public function test_add_user_status_filters() {

		$all_user_status     = get_user_status( 'all' );
		$status              = 'approved';
		$_GET['user_status'] = $status;

		$expected_markup = '';

		foreach ( $all_user_status as $value => $label ) {

			$expected_markup .= sprintf(
				'<option %s value="%s">%s</option>',
				( $value === $status ) ? esc_html( 'selected="selected"' ) : '',
				esc_attr( $value ),
				esc_html( $label )
			);
		}

		$user_lists = _get_list_table( 'WP_Users_List_Table', [ 'screen' => 'users', ] );

		ob_start();
		$user_lists->display();
		$user_lists_markup = ob_get_contents();
		ob_end_clean();

		$this->assertContains( $expected_markup, $user_lists_markup );
	}

	/**
	 * Test case for filter_user_list_by_status().
	 * Query args should be updated based on status requested from GET query args.
	 */
	public function test_filter_user_list_by_status() {

		$_GET['user_status'] = 'invalid-status';

		$args = apply_filters( 'users_list_table_query_args', [] );

		$this->assertEmpty( $args );

		$_GET['user_status'] = 'blocked';

		$args = apply_filters( 'users_list_table_query_args', [] );

		$this->assertEmpty( $args );

		set_current_screen( 'users' );

		$args = apply_filters( 'users_list_table_query_args', [] );

		$this->assertEquals(
			[
				'meta_key'   => 'aj_user_status',
				'meta_value' => 'blocked',
			],
			$args
		);

		$_GET['user_status'] = 'pending';

		$args = apply_filters( 'users_list_table_query_args', [] );

		$this->assertEquals(
			[
				'role'         => self::$default_user_role,
				'meta_key'     => 'aj_user_status',
				'meta_compare' => 'NOT EXISTS',
			],
			$args
		);

		$_GET['user_status'] = 'pre-approved';

		$args = apply_filters( 'users_list_table_query_args', [] );

		$this->assertEquals(
			[
				'role__in' => array_keys( get_pre_approved_user_roles() ),
			],
			$args
		);
	}
}
