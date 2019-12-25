<?php
/**
 * Plugin Name: User Approval
 * Plugin URI: https://github.com/rahulsprajapati/user-approval
 * Description: Approval/block user account for new registered users.
 * Author: Rahul Prajapati
 * Version: 0.0.2
 * Author URI: https://github.com/rahulsprajapati
 * License: GPL2+
 * Text Domain: user-approval
 * Domain Path: /languages
 *
 * @package user-approval
 */

namespace User_Approval;

const VERSION = '0.0.2';

require_once __DIR__ . '/inc/namespace.php';
bootstrap();

// Add user registration checks.
require_once __DIR__ . '/inc/registration.php';
Registration\bootstrap();

// User management from WP admin to approve/block users.
require_once __DIR__ . '/inc/management.php';
Management\bootstrap();

// User login authentication for approved/blocked user.
require_once __DIR__ . '/inc/authenticate.php';
Authenticate\bootstrap();
