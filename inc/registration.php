<?php
/**
 * User_Approval Namespace.
 *
 * @package user-approval
 */

namespace User_Approval\Registration;

use WP_Error;
use WP_User;
use function User_Approval\get_default_user_role;
use function User_Approval\get_role_names;
use function User_Approval\is_default_role_user;

/**
 * Hook up all the filters and actions.
 */
function bootstrap() {

	// Remove default user emails/notifications on user registration.
	remove_action( 'register_new_user', 'wp_send_new_user_notifications' );

	add_action( 'register_new_user', __NAMESPACE__ . '\\send_notification_to_allowed_users' );

	add_filter( 'wp_new_user_notification_email_admin', __NAMESPACE__ . '\\update_new_user_admin_email', 50, 3 );

	// Add a message after registration about account is being sent for verification.
	add_filter( 'wp_login_errors', __NAMESPACE__ . '\\update_registered_user_message' );
}

/**
 * Send user emails/notification on user registration for pre-approved users.
 *
 * @param int $user_id User id.
 */
function send_notification_to_allowed_users( $user_id ) {
	$user = get_userdata( $user_id );

	if (
		! empty( $user )
		&& is_default_role_user( $user )
	) {
		wp_send_new_user_notifications( $user_id, 'admin' );
		return;
	}

	wp_send_new_user_notifications( $user_id );
}

/**
 * Send a notification/email to admin when user register.
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
function update_new_user_admin_email( $email_data, $user, $blogname ) {

	if ( empty( $user ) || ! is_default_role_user( $user ) ) {
		return $email_data;
	}

	$user_roles = get_role_names();
	$user_role  = $user_roles[ get_default_user_role() ];

	$message = sprintf(
		/* translators: 1: User role label, 2: Site title. */
		esc_html__( 'New %1$s registration on your site %2$s:', 'user-approval' ),
		$user_role,
		$blogname
	);

	$message .= "\r\n\r\n";

	$message .= sprintf(
		/* translators: %s: User login. */
		esc_html__( 'Username: %s', 'user-approval' ),
		$user->user_login
	);

	$message .= "r\n\r\n";

	$message .= sprintf(
		/* translators: %s: User email address. */
		esc_html__( 'Email: %s', 'user-approval' ),
		$user->user_email
	);

	$message .= "\r\n";

	/* translators: New user registration notification email subject. %s: Site title. */
	$email_data['subject'] = esc_html__( '[%s] New Contributor Registration', 'user-approval' );
	$email_data['message'] = $message;

	return apply_filters( 'user_approval_new_user_admin_email_data', $email_data );
}

/**
 * Add a message after registration about account is being sent for verification.
 *
 * @param WP_Error $errors WP Error object.
 *
 * @return WP_Error
 */
function update_registered_user_message( $errors ) {
	$checkemail = filter_input( INPUT_GET, 'checkemail', FILTER_SANITIZE_STRING );

	if ( empty( $checkemail ) || 'registered' !== $checkemail ) {
		return $errors;
	}

	$errors->remove( 'registered' );

	$message = sprintf(
		esc_html__( 'An email has been sent to the site administrator for account verification.', 'user-approval' )
	);
	$message .= ' ';
	$message .= sprintf(
		esc_html__( 'You will receive an email once your account is reviewed. Thanks for your patience.', 'user-approval' )
	);

	$message = apply_filters( 'user_approval_registered_user_message', $message );

	$errors->add( 'registered', $message, 'message' );

	return $errors;
}
