<?php
/**
 * =====================================================================
 * admin/logout.php  -  End the administrator session
 * =====================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

logout_admin();
destroy_session();

session_start();
set_flash('success', 'You have been signed out of the admin console.');

redirect('admin/login.php');
