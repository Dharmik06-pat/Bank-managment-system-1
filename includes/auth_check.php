<?php
/**
 * =====================================================================
 * includes/auth_check.php
 * ---------------------------------------------------------------------
 * Access control for the two panels.
 *
 * Customer pages start with:   require_customer();
 * Admin pages start with:      require_admin();
 *
 * Customers and admins use different session keys, so signing in as an
 * admin never grants customer access (and the other way round).
 * =====================================================================
 */

if (!defined('SESSION_IDLE_TIMEOUT')) {
    define('SESSION_IDLE_TIMEOUT', 1800);   // auto sign-out after 30 idle minutes
}

/* =====================================================================
 | LOGGED-IN CHECKS
 * ================================================================== */

function is_customer_logged_in(): bool
{
    return !empty($_SESSION['customer_id']);
}

function is_admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

/* =====================================================================
 | SIGN IN / SIGN OUT
 * ================================================================== */

/**
 * Start a customer session.
 * session_regenerate_id() issues a brand new session ID at the moment
 * privileges change, which defeats session-fixation attacks.
 */
function login_customer(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['customer_id']   = (int) $user['id'];
    $_SESSION['customer_name'] = $user['full_name'];
    $_SESSION['last_activity'] = time();

    unset($_SESSION['csrf_token']);   // fresh token for the new session
}

function login_admin(array $admin): void
{
    session_regenerate_id(true);

    $_SESSION['admin_id']       = (int) $admin['id'];
    $_SESSION['admin_name']     = $admin['full_name'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['last_activity']  = time();

    unset($_SESSION['csrf_token']);
}

/** Clear only the customer keys (an admin in the same browser stays in). */
function logout_customer(): void
{
    unset($_SESSION['customer_id'], $_SESSION['customer_name']);
}

/** Clear only the admin keys. */
function logout_admin(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_username']);
}

/** Wipe the whole session and its cookie. */
function destroy_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'],
                  $params['secure'], $params['httponly']);
    }

    session_destroy();
}

/* =====================================================================
 | IDLE TIMEOUT
 * ================================================================== */

/**
 * Sign the visitor out when they have been inactive too long.
 * Called from inside require_customer() / require_admin().
 */
function enforce_idle_timeout(string $redirectTo): void
{
    if (isset($_SESSION['last_activity'])
        && (time() - (int) $_SESSION['last_activity']) > SESSION_IDLE_TIMEOUT) {

        destroy_session();
        session_start();
        set_flash('warning', 'You were signed out automatically after 30 minutes of inactivity.');
        redirect($redirectTo);
    }

    $_SESSION['last_activity'] = time();
}

/* =====================================================================
 | PAGE GUARDS
 * ================================================================== */

/**
 * Block the page unless a customer is signed in AND still Active.
 * Returns the customer's database row so the page can use it directly.
 */
function require_customer(): array
{
    global $pdo;

    if (!is_customer_logged_in()) {
        set_flash('warning', 'Please sign in to continue.');
        redirect('login.php');
    }

    enforce_idle_timeout('login.php');

    // Re-read the row on every request: if an administrator deactivated
    // or deleted this customer a moment ago, they lose access instantly.
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['customer_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        logout_customer();
        set_flash('danger', 'Your account could not be found. Please sign in again.');
        redirect('login.php');
    }

    if ($user['status'] !== 'Active') {
        logout_customer();
        set_flash('danger', 'Your account has been deactivated. Please contact the bank for assistance.');
        redirect('login.php');
    }

    // Keep the display name in sync if the profile was edited.
    $_SESSION['customer_name'] = $user['full_name'];

    return $user;
}

/**
 * Block the page unless an administrator is signed in.
 * Returns the admin's database row.
 */
function require_admin(): array
{
    global $pdo;

    if (!is_admin_logged_in()) {
        set_flash('warning', 'Please sign in to the admin panel to continue.');
        redirect('admin/login.php');
    }

    enforce_idle_timeout('admin/login.php');

    $stmt = $pdo->prepare('SELECT * FROM admins WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();

    if (!$admin) {
        logout_admin();
        set_flash('danger', 'Administrator account not found. Please sign in again.');
        redirect('admin/login.php');
    }

    $_SESSION['admin_name'] = $admin['full_name'];

    return $admin;
}

/**
 * Used by login.php and register.php so a signed-in visitor does not
 * see the sign-in form again.
 */
function redirect_if_logged_in(): void
{
    if (is_customer_logged_in()) {
        redirect('customer/dashboard.php');
    }
}

/* =====================================================================
 | OWNERSHIP CHECK
 * ================================================================== */

/**
 * Fetch an account only if it belongs to the given customer.
 * This is what stops someone editing a hidden form field and paying
 * money out of a stranger's account.
 */
function get_owned_account(PDO $pdo, int $accountId, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM accounts WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$accountId, $userId]);
    $account = $stmt->fetch();

    return $account ?: null;
}

/** Every account owned by a customer, newest last. */
function get_user_accounts(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM accounts WHERE user_id = ? ORDER BY created_at ASC, id ASC'
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}
