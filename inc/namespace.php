<?php
/**
 * User_Approval Namespace.
 *
 * @package user-approval
 */

namespace User_Approval;

use WP_User;

const STATUS_PRE_APPROVED   = 'pre-approved';
const STATUS_PENDING        = 'pending';
const STATUS_APPROVED       = 'approved';
const STATUS_BLOCKED        = 'blocked';
const STATUS_META_KEY       = 'aj_user_status';
const STATUS_APPROVED_NONCE = 'aj-user-approve';
const STATUS_BLOCKED_NONCE  = 'aj-user-blocked';

/**
 * Hook up all the filters and actions.
 */
function bootstrap() {

	// Load text-domain for language translation.
	add_action( 'plugins_loaded', __NAMESPACE__ . '\\load_textdomain' );
}

/**
 * Load plugin text domain for text translation.
 *
 * @codeCoverageIgnore
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
		&& in_array( get_default_user_role(), $user->roles, true )
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
		STATUS_PRE_APPROVED => esc_html__( 'Pre Approved', 'user-approval' ),
		STATUS_PENDING      => esc_html__( 'Pending', 'user-approval' ),
		STATUS_APPROVED     => esc_html__( 'Approved', 'user-approval' ),
		STATUS_BLOCKED      => esc_html__( 'Blocked', 'user-approval' ),
	];

	if ( isset( $user_status[ $status ] ) ) {
		return $user_status[ $status ];
	}

	return empty( $status ) ? $user_status[ STATUS_PENDING ] : $user_status;
}

/**
 * This method is an improved version of PHP's filter_input() and
 * works well on PHP Cli as well which PHP default method does not.
 *
 * @param int    $type One of INPUT_GET, INPUT_POST, INPUT_COOKIE, INPUT_SERVER, or INPUT_ENV.
 * @param string $variable_name Name of a variable to get.
 * @param int    $filter The ID of the filter to apply.
 * @param mixed  $options filter to apply.
 *
 * @codeCoverageIgnore
 *
 * @return mixed Value of the requested variable on success, FALSE if the filter fails, or NULL if the variable_name variable is not set.
 */
function filter_input( $type, $variable_name, $filter = FILTER_DEFAULT, $options = null ) {

	if ( php_sapi_name() !== 'cli' ) {
		/*
		 * Code is not running on PHP Cli and we are in clear.
		 * Use the PHP method and bail out.
		 */
		switch ( $filter ) {
			case FILTER_SANITIZE_STRING:
				$sanitized_variable = sanitize_text_field( \filter_input( $type, $variable_name, $filter ) );
				break;
			default:
				$sanitized_variable = \filter_input( $type, $variable_name, $filter, $options );
				break;
		}

		return $sanitized_variable;
	}

	$allowed_html_tags = wp_kses_allowed_html( 'post' );

	/**
	 * Marking the switch() block below to be ignored by PHPCS
	 * because PHPCS squawks on using superglobals like $_POST or $_GET
	 * directly but it can't be helped in this case as this code
	 * is running on Cli.
	 */

	// @codingStandardsIgnoreStart
	switch ( $type ) {

		case INPUT_GET:
			if ( ! isset( $_GET[ $variable_name ] ) ) {
				return null;
			}
			$input = wp_kses( $_GET[ $variable_name ], $allowed_html_tags );
			break;

		case INPUT_POST:
			if ( ! isset( $_POST[ $variable_name ] ) ) {
				return null;
			}

			$input = wp_kses( $_POST[ $variable_name ], $allowed_html_tags );
			break;

		case INPUT_COOKIE:
			if ( ! isset( $_COOKIE[ $variable_name ] ) ) {
				return null;
			}
			$input = wp_kses( $_COOKIE[ $variable_name ], $allowed_html_tags );
			break;

		case INPUT_SERVER:
			if ( ! isset( $_SERVER[ $variable_name ] ) ) {
				return null;
			}

			$input = wp_kses( $_SERVER[ $variable_name ], $allowed_html_tags );
			break;

		case INPUT_ENV:
			if ( ! isset( $_ENV[ $variable_name ] ) ) {
				return null;
			}

			$input = wp_kses( $_ENV[ $variable_name ], $allowed_html_tags );
			break;

		default:
			return null;
			break;

	}
	// @codingStandardsIgnoreEnd​

	return filter_var( $input, $filter );
}
