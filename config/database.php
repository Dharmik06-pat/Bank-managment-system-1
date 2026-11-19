<?php
/**
 * =====================================================================
 * config/database.php
 * ---------------------------------------------------------------------
 * Central bootstrap file for the Banking Management System.
 *
 *   1. Holds the MySQL connection settings for XAMPP
 *   2. Opens a PDO connection (prepared statements = SQL-injection safe)
 *   3. Starts a hardened PHP session
 *   4. Loads the shared helper functions
 *
 * EVERY page in this project starts by including this one file.
 * =====================================================================
 */

/* ---------------------------------------------------------------------
 | 1. DATABASE SETTINGS
 |    These are the XAMPP defaults. If you gave MySQL a root password,
 |    type it into DB_PASS below.
 * ------------------------------------------------------------------ */
define('DB_HOST', 'localhost');
define('DB_NAME', 'banking_management_system');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP default root password is empty
define('DB_CHARSET', 'utf8mb4');

/* ---------------------------------------------------------------------
 | 2. APPLICATION SETTINGS
 * ------------------------------------------------------------------ */
define('BANK_NAME',      'Apex Trust Bank');
define('BANK_TAGLINE',   'Banking, refined.');
define('CURRENCY_SYMBOL', '₹');          // Indian Rupee sign, as a real UTF-8
                                        // character. It must NOT be the HTML entity
                                        // '&#8377;': money() output is escaped with e()
                                        // in flash messages and read by JavaScript as
                                        // textContent, and both would print the entity
                                        // literally instead of the symbol.
define('RECORDS_PER_PAGE', 10);

// Show friendly errors to the user, log the real ones for the developer.
define('DEBUG_MODE', false);            // set true while developing

date_default_timezone_set('Asia/Kolkata');

if (DEBUG_MODE) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

/* ---------------------------------------------------------------------
 | 3. BASE URL
 |    Worked out automatically from the folder this project sits in, so
 |    every link keeps working even if you rename the folder.
 |    Result for C:\xampp\htdocs\banking-management-system
 |      ->  /banking-management-system
 * ------------------------------------------------------------------ */
$__projectDir  = str_replace('\\', '/', dirname(__DIR__));
$__documentRoot = isset($_SERVER['DOCUMENT_ROOT'])
    ? rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/')
    : '';

if ($__documentRoot !== '' && strpos($__projectDir, $__documentRoot) === 0) {
    $__base = substr($__projectDir, strlen($__documentRoot));
} else {
    $__base = '/banking-management-system';
}
define('BASE_URL', rtrim($__base, '/'));

/* ---------------------------------------------------------------------
 | 4. SECURE SESSION
 |    Cookie flags are set BEFORE session_start() or they are ignored.
 * ------------------------------------------------------------------ */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,   // JavaScript cannot read the session cookie
        'samesite' => 'Lax',  // basic CSRF hardening
        // Send the cookie over HTTPS only when the request itself is HTTPS.
        // On XAMPP you will be on plain http://localhost, so this stays
        // false there; put the project behind HTTPS and it turns itself on.
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);

    session_name('BMS_SESSION');
    session_start();
}

/* ---------------------------------------------------------------------
 | 5. PDO CONNECTION
 * ------------------------------------------------------------------ */
$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

$pdoOptions = [
    // Throw exceptions instead of failing silently
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Return associative arrays by default
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Use REAL prepared statements, not emulated ones
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdoOptions);
} catch (PDOException $e) {
    // Never print raw database errors to a visitor.
    error_log('[BMS] Database connection failed: ' . $e->getMessage());

    http_response_code(500);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>Database connection failed</title>'
       . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">'
       . '</head><body class="bg-light"><div class="container py-5"><div class="row justify-content-center">'
       . '<div class="col-lg-8"><div class="card border-0 shadow-sm"><div class="card-body p-4 p-md-5">'
       . '<h1 class="h3 text-danger mb-3">Could not connect to the database</h1>'
       . '<p class="text-muted">The website is fine, but MySQL did not answer. Work through this checklist:</p>'
       . '<ol class="text-muted lh-lg">'
       . '<li>Open the <strong>XAMPP Control Panel</strong> and make sure <strong>MySQL</strong> shows a green "Running" status.</li>'
       . '<li>Import <code>database/banking_management_system.sql</code> through <a href="http://localhost/phpmyadmin" target="_blank" rel="noopener">phpMyAdmin</a>.</li>'
       . '<li>Confirm a database named <code>' . DB_NAME . '</code> now exists.</li>'
       . '<li>If your MySQL root user has a password, add it to <code>DB_PASS</code> in <code>config/database.php</code>.</li>'
       . '</ol>';

    if (DEBUG_MODE) {
        echo '<div class="alert alert-warning mt-3 mb-0"><strong>Debug detail:</strong><br>'
           . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
    }

    echo '</div></div></div></div></div></body></html>';
    exit;
}

/* ---------------------------------------------------------------------
 | 6. SHARED HELPER FUNCTIONS
 * ------------------------------------------------------------------ */
require_once __DIR__ . '/../includes/functions.php';
