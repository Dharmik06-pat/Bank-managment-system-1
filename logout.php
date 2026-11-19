<?php
/**
 * =====================================================================
 * logout.php  -  Sign a customer out (root-level convenience route)
 * ---------------------------------------------------------------------
 * customer/logout.php does the same thing; this copy exists because the
 * required folder structure lists logout.php at the project root too.
 * =====================================================================
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth_check.php';

logout_customer();
destroy_session();

// A brand new session, purely to carry the goodbye message.
session_start();
set_flash('success', 'You have been signed out securely. See you soon!');

redirect('login.php');
