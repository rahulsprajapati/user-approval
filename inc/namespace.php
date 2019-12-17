<?php
/**
 * User_Approval Namespace.
 *
 * @package user-approval
 */

namespace User_Approval;

use WP_User;

/**
 * Hook up all the filters and actions.
 */
function bootstrap() {

	// Load text-domain for language translation.
	add_action( 'plugins_loaded', __NAMESPACE__ . '\\load_textdomain' );
}

/**
 * Load plugin text domain for text translation.
 */
function load_textdomain() {

	load_plugin_textdomain(
		'user-approval',
		false,
		basename( plugin_dir_url( __DIR__ ) ) . '/languages'
	);
}

/**
 * Get default user role to which new registered user will be assigned.
 *
 * @return string
 */
function get_default_user_role() {
	return apply_filters( 'user_approval_default_user_role', get_option( 'default_role' ) );
}

/**
 * Check if given user is with default user role which need user status verification.
 *
 * @param WP_User $user WP_User object.
 *
 * @return bool
 */
function is_default_role_user( $user ) {

	return (
		$user instanceof WP_User
		&& in_array( get_default_user_role(), $user->roles )
	);
}


/**
 * Get all user role names array.
 *
 * @return array|string[]
 */
function get_role_names() {
	$user_roles_obj = wp_roles();

	return $user_roles_obj->role_names ?? [];
}

/**
 * Get user role which should be pre-approved.
 *
 * @return array
 */
function get_pre_approved_user_roles() {
	$user_roles = get_role_names();

	// Remove default role.
	unset( $user_roles[ get_default_user_role() ] );

	return $user_roles;
}

/**
 * Get all user status.
 *
 * @param string $status Status key to get label of respective user status.
 *
 * @return string|array
 */
function get_user_status( $status = '' ) {

	$user_status = [
		'pre-approved' => esc_html__( 'Pre Approved', 'user-approval' ),
		'pending'      => esc_html__( 'Pending', 'user-approval' ),
		'approved'     => esc_html__( 'Approve', 'user-approval' ),
		'blocked'      => esc_html__( 'Block', 'user-approval' ),
	];

	if ( isset( $user_status[ $status ] ) ) {
		return $user_status[ $status ];
	}

	return empty( $status ) ? $user_status['pending'] : $user_status;
}
