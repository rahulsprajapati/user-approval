<?php
/**
 * Allow user authentication only on allowed/approved users.
 *
 * @package user-approval
 */

namespace User_Approval\Authenticate;

use WP_Error;
use WP_User;
use function User_Approval\filter_input;
use function User_Approval\get_default_user_role;
use function User_Approval\is_default_role_user;
use const User_Approval\STATUS_APPROVED;
use const User_Approval\STATUS_BLOCKED;
use const User_Approval\STATUS_META_KEY;

/**
 * Hook up all the filters and actions.
 */
function bootstrap() {
	add_action( 'lostpassword_post', __NAMESPACE__ . '\\block_non_approved_user_request', 1 );

	add_filter( 'wp_authenticate_user', __NAMESPACE__ . '\\authenticate_user_by_status' );
}

/**
 * Block non approved user to generate forgot password email/link.
 *
 * @param WP_Error $errors A WP_Error object containing any errors generated
 *                         by using invalid credentials.
 */
function block_non_approved_user_request( $errors ) {

	// Do not do anything if there is already an error.
	if ( $errors->get_error_code() ) {
		return;
	}

	$login = filter_input( INPUT_POST, 'user_login', FILTER_SANITIZE_STRING );

	$user = is_email( $login )
		? get_user_by( 'email', $login )
		: get_user_by( 'login', $login );

	if (
		! $user instanceof WP_User
		|| ! in_array( get_default_user_role(), $user->roles, true )
	) {
		return;
	}

	$user_status = get_user_meta( $user->ID, STATUS_META_KEY, true );

	if ( STATUS_APPROVED !== $user_status ) {
		$errors->add(
			'unapproved_user',
			__( '<strong>ERROR</strong>: Your account is not active.', 'user-approval' )
		);
	}
}

/**
 * Authenticate user based on the user status.
 *
 * @param WP_User|WP_Error $user WP_User or WP_Error object if a previous
 *                               callback failed authentication.
 *
 * @return WP_Error|WP_User
 */
function authenticate_user_by_status( $user ) {

	if ( ! is_default_role_user( $user ) ) {
		return $user;
	}

	$user_status = get_user_meta( $user->ID, STATUS_META_KEY, true );

	switch ( $user_status ) {
		case STATUS_BLOCKED:
			$denied_message = __( '<strong>ERROR</strong>: Your account access has been blocked to this site.', 'user-approval' );
			$user_data      = new WP_Error( 'blocked_access', $denied_message );
			break;
		case STATUS_APPROVED:
			$user_data = $user;
			break;
		default:
			$pending_message = __( '<strong>ERROR</strong>: Your account is still pending approval.', 'user-approval' );
			$user_data       = new WP_Error( 'pending_approval', $pending_message );
			break;
	}

	return $user_data;
}
