<?php
/**
 * =====================================================================
 * admin/login.php  -  Administrator sign-in
 * ---------------------------------------------------------------------
 * Separate from the customer login on purpose: different session keys,
 * a different table, and a stricter lockout policy.
 *
 * Default credentials created by the SQL import:
 *     Username: admin
 *     Password: admin123
 * Change this password immediately in a real deployment.
 * =====================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Already signed in as an admin? Go to the console.
if (is_admin_logged_in()) {
    redirect('admin/dashboard.php');
}

$pageTitle = 'Admin Login';

/* Stricter than the customer side: fewer tries, longer lockout. */
define('ADMIN_MAX_ATTEMPTS', 5);
define('ADMIN_LOCKOUT_SECONDS', 600);   // 10 minutes

/* =====================================================================
 | FORM HANDLING
 * ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_csrf('admin/login.php');

    $username = post('username');
    $password = raw_post('password');

    /* ----- Lockout check ------------------------------------------ */
    $attempts   = (int) ($_SESSION['admin_attempts'] ?? 0);
    $lockedAt   = (int) ($_SESSION['admin_locked_at'] ?? 0);
    $secondsAgo = time() - $lockedAt;

    if ($attempts >= ADMIN_MAX_ATTEMPTS && $secondsAgo < ADMIN_LOCKOUT_SECONDS) {
        $waitMinutes = (int) ceil((ADMIN_LOCKOUT_SECONDS - $secondsAgo) / 60);
        set_flash('danger', 'Too many failed attempts. Admin sign-in is locked for about ' . $waitMinutes . ' more minute(s).');
        redirect('admin/login.php');
    }

    if ($attempts >= ADMIN_MAX_ATTEMPTS && $secondsAgo >= ADMIN_LOCKOUT_SECONDS) {
        $_SESSION['admin_attempts'] = 0;
        unset($_SESSION['admin_locked_at']);
    }

    /* ----- Validate ----------------------------------------------- */
    if ($username === '' || $password === '') {
        set_flash('danger', 'Please enter both the username and the password.');
        set_old(['username' => $username]);
        redirect('admin/login.php');
    }

    /* ----- Look up the administrator ------------------------------ */
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    // Deliberately vague message: never reveal which half was wrong.
    if (!$admin || !password_verify($password, $admin['password'])) {

        $_SESSION['admin_attempts'] = $attempts + 1;

        if ($_SESSION['admin_attempts'] >= ADMIN_MAX_ATTEMPTS) {
            $_SESSION['admin_locked_at'] = time();
            set_flash('danger', 'Too many failed attempts. Admin sign-in is locked for 10 minutes.');
        } else {
            $remaining = ADMIN_MAX_ATTEMPTS - $_SESSION['admin_attempts'];
            set_flash('danger', 'Invalid administrator credentials. (' . $remaining . ' attempt(s) remaining)');
        }

        error_log('[BMS] Failed admin login attempt for username: ' . $username);
        set_old(['username' => $username]);
        redirect('admin/login.php');
    }

    /* ----- Success ------------------------------------------------ */
    if (password_needs_rehash($admin['password'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $update  = $pdo->prepare('UPDATE admins SET password = ? WHERE id = ?');
        $update->execute([$newHash, $admin['id']]);
    }

    unset($_SESSION['admin_attempts'], $_SESSION['admin_locked_at']);
    clear_old();

    login_admin($admin);

    set_flash('success', 'Signed in as ' . $admin['full_name'] . '.');
    redirect('admin/dashboard.php');
}

/* =====================================================================
 | PAGE OUTPUT  (standalone layout - no public navbar)
 * ================================================================== */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Login &middot; <?= e(BANK_NAME) ?></title>

    <link rel="icon" type="image/svg+xml" href="<?= url('assets/images/logo.svg') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="admin-login-body">

<main class="admin-login-wrap">
    <section class="admin-login-card" aria-labelledby="admin-login-title">
        <div class="admin-login-heading">
            <h1 id="admin-login-title" class="admin-login-title">Admin login</h1>
            <p class="admin-login-sub"><?= e(BANK_NAME) ?></p>
        </div>

        <?= show_flash() ?>

        <form method="post" action="<?= url('admin/login.php') ?>">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control form-control-lg" id="username" name="username"
                       value="<?= old('username') ?>" required autofocus autocomplete="username"
                       placeholder="Enter username">
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control form-control-lg" id="password" name="password"
                       required autocomplete="current-password" placeholder="Enter password">
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg">Sign in</button>
        </form>

        <p class="admin-login-foot">
            <a href="<?= url('index.php') ?>">Back to website</a>
            <span aria-hidden="true">&middot;</span>
            <a href="<?= url('login.php') ?>">Customer login</a>
        </p>
    </section>
</main>

</body>
</html>
<?php clear_old(); ?>
