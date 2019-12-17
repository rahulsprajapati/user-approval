<?php
/**
 * User account management - approval/block.
 *
 * @package user-approval
 */

namespace User_Approval\Management;

use WP_User;
use function User_Approval\get_default_user_role;
use function User_Approval\get_user_status;

const APPROVE_STATUS_NONCE = 'aj-user-approve';
const BLOCKED_STATUS_NONCE = 'aj-user-blocked';

/**
 * Hook up all the filters and actions.
 */
function bootstrap() {
	add_action( 'manage_users_columns', __NAMESPACE__ . '\\user_verification_column', 1 );

	add_filter( 'manage_users_custom_column', __NAMESPACE__ . '\\add_user_verification_status', 10, 3 );
	add_filter( 'user_row_actions', __NAMESPACE__ . '\\add_user_verification_action', 10, 2 );

	add_action( 'admin_action_aj_user_status', __NAMESPACE__ . '\\aj_user_status_update' );

	add_filter( 'wp_new_user_notification_email', __NAMESPACE__ . '\\update_approved_new_user_email', 50, 3 );

	add_filter( 'manage_users_extra_tablenav', __NAMESPACE__ . '\\add_user_status_filters', 10 );
	add_filter( 'users_list_table_query_args', __NAMESPACE__ . '\\filter_user_list_by_status', 100 );

}

/**
 * Add new column for users list page for user status.
 *
 * @param array $columns Columns list of user data.
 *
 * @return array
 */
function user_verification_column( $columns ) {

	$columns[ 'aj_user_status' ] = esc_html__( 'Status', 'user-approval' );

	return $columns;
}

/**
 * Add a user status to status column of respective user row.
 *
 * @param string $val Default value of column.
 * @param string $column_name Column id/name.
 * @param int    $user_id User id of respective user row.
 *
 * @return array|string
 */
function add_user_verification_status( $val, $column_name, $user_id ) {

	if ( 'aj_user_status' !== $column_name ) {
		return $val;
	}

	$user_status = get_user_meta( $user_id, 'aj_user_status', true ) ?: 'pending';
	$user        = get_userdata( $user_id );
	$user_status = ( ! empty( $user ) && in_array( get_default_user_role(), $user->roles ) ) ? $user_status : 'pre-approved';

	return get_user_status( $user_status );
}

/**
 * Add action links to respective user row to approve/block the user.
 *
 * @param array   $actions All action link lists.
 * @param WP_User $user WP_User object for respective user row.
 *
 * @return array
 */
function add_user_verification_action( $actions, $user ) {

	if ( ! in_array( get_default_user_role(), $user->roles ) ) {
		return $actions;
	}

	$user_current_status = get_user_meta( $user->ID, 'aj_user_status', true );

	$query_args = [
		'user'   => $user->ID,
		'action' => 'aj_user_status',
		'status' => 'approve',
	];

	// Approve action link.
	$approve_link = add_query_arg( $query_args );
	$approve_link = remove_query_arg( [ 'new_role' ], $approve_link );
	$approve_link = wp_nonce_url( $approve_link, APPROVE_STATUS_NONCE );

	$query_args['status'] = 'block';

	// Block action link.
	$block_link = add_query_arg( $query_args );
	$block_link = remove_query_arg( [ 'new_role' ], $block_link );
	$block_link = wp_nonce_url( $block_link, BLOCKED_STATUS_NONCE );

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

	$actions = array_merge( $actions, [
		'approved' => $approve_action,
		'blocked'  => $block_action,
	] );

	if ( isset( $actions[ $user_current_status ] ) ) {
		unset( $actions[ $user_current_status ] );
	}

	return $actions;
}

/**
 * Update user status on post request.
 */
function aj_user_status_update() {
	$user_id = filter_input( INPUT_GET, 'user', FILTER_VALIDATE_INT );
	$status  = filter_input( INPUT_GET, 'status', FILTER_SANITIZE_STRING );

	$user        = get_userdata( $user_id );
	$user_status = get_user_meta( $user_id, 'aj_user_status', true );

	if (
		! $user instanceof WP_User
		|| ! in_array( get_default_user_role(), $user->roles )
		|| ( $status === $user_status ) // Avoid any refresh page.
	) {
		return;
	}

	$current_user_id = get_current_user_id();

	if ( 'approve' === $status && check_admin_referer( APPROVE_STATUS_NONCE ) ) {
		update_user_meta( $user_id, 'aj_user_status', 'approved' );
		wp_send_new_user_notifications( $user_id, 'user' );
		update_user_meta( $user_id, 'aj_user_verified_by', $current_user_id );
	} elseif ( 'block' === $status && check_admin_referer( BLOCKED_STATUS_NONCE ) ) {
		update_user_meta( $user_id, 'aj_user_status', 'blocked' );
		send_user_blocked_email( $user );
		update_user_meta( $user_id, 'aj_user_verified_by', $current_user_id );
	}
}

/**
 * Send a notification/email to approved user.
 *
 * @param array   $email_data {
 *     Used to build wp_mail().
 *
 *     @type string $to      The intended recipient - New user email address.
 *     @type string $subject The subject of the email.
 *     @type string $message The body of the email.
 *     @type string $headers The headers of the email.
 * }
 * @param WP_User $user     User object for new user.
 * @param string  $blogname The site title.
 *
 * @return array Updated email data for wp_mail.
 */
function update_approved_new_user_email( $email_data, $user, $blogname  ) {

	if ( empty( $user ) || ! in_array( get_default_user_role(), $user->roles ) ) {
		return $email_data;
	}

	$message = sprintf(
		/* translators: %s: User login. */
		esc_html__( 'You have been approved to access %s', 'user-approval' ),
		$blogname
	);
	$message .= "\r\n\r\n";
	$message .= $email_data['message'];

	/* translators: Login details notification email subject. %s: Site title. */
	$email_data[ 'subject' ] = esc_html__( '[%s] Login Details [Account Approved]', 'user-approval' );
	$email_data[ 'message' ] = $message;

	return apply_filters( 'user_approval_approved_user_email_data', $email_data );
}

/**
 * Send a notification/email to blocked user.
 *
 * @param WP_User $user WP_User object.
 */
function send_user_blocked_email( $user ) {
	$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

	$message = sprintf(
		/* translators: %s: Site title. */
		esc_html__( 'You have been denied access to %s.', 'user-approval' ),
		$blogname
	);

	$subject = sprintf(
		/* translators: %s: Site title. */
		esc_html__( '[%s] Account Blocked.', 'user-approval' ),
		$blogname
	);

	$email_data = [
		'to'      => $user->user_email,
		'subject' => $subject,
		'message' => $message,
		'headers' => '',
	];

	$email_data = apply_filters( 'user_approval_blocked_user_email_data', $email_data );

	// phpcs:ignore WordPressVIPMinimum.VIP.RestrictedFunctions.wp_mail_wp_mail
	wp_mail(
		$email_data['to'],
		wp_specialchars_decode( $email_data['subject'] ),
		$email_data['message'],
		$email_data['headers']
	);
}

/**
 * Add a filter to list users based on user status.
 *
 * @param string $which The location of the extra table nav markup: 'top' or 'bottom'.
 */
function add_user_status_filters( $which ) {

	if ( 'top' !== $which ) {
		return;
	}

	$status          = filter_input( INPUT_GET, 'user_status', FILTER_SANITIZE_STRING );
	$all_user_status = get_user_status( 'all' );

	?>
	<div class="alignleft actions">
		<label for="filter-by-date" class="screen-reader-text">Filter by user status</label>
		<select name="user_status" id="filter-by-user-status">
			<?php
			printf(
				'<option %s value="%s">%s</option>',
				( 'all' === $status ) ? esc_html( 'selected="selected"' ) : '',
				esc_attr( 'all' ),
				esc_html__( 'All', 'user-approval' )
			);
			foreach ( $all_user_status as $value => $label ) {

				printf(
					'<option %s value="%s">%s</option>',
					( $value === $status ) ? esc_html( 'selected="selected"' ) : '',
					esc_attr( $value ),
					esc_html( $label )
				);
			}
			?>
		</select>
		<button type="submit" name="filter_action" class="button">Filter</button>
	</div>
	<?php
}

/**
 * Filter user list in WP Admin filter by user status.
 *
 * @param array $args Arguments passed to WP_User_Query to retrieve items for the current
 *                    users list table.
 *
 * @return array
 */
function filter_user_list_by_status( $args ) {

	$current_screen = get_current_screen();

	$status = filter_input( INPUT_GET, 'user_status', FILTER_SANITIZE_STRING );

	if (
		! is_admin()
		|| empty( $current_screen )
		|| 'users' !== $current_screen->id
		|| empty( $status )
		|| is_array( get_user_status( $status ) )
	) {
		return $args;
	}

	if ( in_array( $status, [ 'blocked', 'approved' ] ) ) {
		$args['meta_key']   = 'aj_user_status'; // phpcs:ignore WordPress.VIP.SlowDBQuery.slow_db_query_meta_key
		$args['meta_value'] = $status; // phpcs:ignore WordPress.VIP.SlowDBQuery.slow_db_query_meta_value
	} elseif ( 'pending' === $status ) {
		$args['role']         = get_default_user_role();
		$args['meta_key']     = 'aj_user_status'; // phpcs:ignore WordPress.VIP.SlowDBQuery.slow_db_query_meta_key
		$args['meta_compare'] = 'NOT EXISTS';
	} else {
		$user_roles_obj  = wp_roles();
		$user_roles_data = $user_roles_obj->roles ?? [];
		unset( $user_roles_data[ get_default_user_role() ] );

		if ( ! empty( $user_roles_data ) ) {
			$args['role__in'] = array_keys( $user_roles_data );
		}
	}

	return $args;
}
