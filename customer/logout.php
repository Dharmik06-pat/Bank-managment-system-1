<?php
/**
 * =====================================================================
 * customer/logout.php  -  End the customer session
 * =====================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

logout_customer();
destroy_session();

session_start();
set_flash('success', 'You have been signed out securely. See you soon!');

redirect('login.php');
