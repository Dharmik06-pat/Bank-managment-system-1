<?php
/**
 * =====================================================================
 * login.php  -  Customer sign-in
 * ---------------------------------------------------------------------
 * Accepts either the username or the email address, verifies the
 * password with password_verify() and starts a fresh session.
 *
 * Two deliberate security choices:
 *   1. The error message never says whether it was the username or the
 *      password that was wrong. Telling an attacker "that username
 *      exists, wrong password" hands them a list of valid accounts.
 *   2. Repeated failures from the same session are slowed down, which
 *      makes automated password guessing impractical.
 * =====================================================================
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth_check.php';

redirect_if_logged_in();

$pageTitle       = 'Internet Banking Login';
$pageDescription = 'Sign in to your ' . BANK_NAME . ' internet banking account.';

/* Brute-force throttle settings */
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_SECONDS', 300);   // 5 minutes

/* =====================================================================
 | FORM HANDLING
 * ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_csrf('login.php');

    $identifier = post('identifier');      // username OR email
    $password   = raw_post('password');

    /* ----- Are they currently locked out? ------------------------- */
    $attempts   = (int) ($_SESSION['login_attempts'] ?? 0);
    $lockedAt   = (int) ($_SESSION['login_locked_at'] ?? 0);
    $secondsAgo = time() - $lockedAt;

    if ($attempts >= MAX_LOGIN_ATTEMPTS && $secondsAgo < LOGIN_LOCKOUT_SECONDS) {
        $waitMinutes = (int) ceil((LOGIN_LOCKOUT_SECONDS - $secondsAgo) / 60);
        set_flash('danger', 'Too many failed sign-in attempts. Please wait about ' . $waitMinutes . ' minute(s) and try again.');
        redirect('login.php');
    }

    // The lockout window has passed, so start counting again.
    if ($attempts >= MAX_LOGIN_ATTEMPTS && $secondsAgo >= LOGIN_LOCKOUT_SECONDS) {
        $_SESSION['login_attempts']  = 0;
        unset($_SESSION['login_locked_at']);
    }

    /* ----- Basic validation --------------------------------------- */
    if ($identifier === '' || $password === '') {
        set_old(['identifier' => $identifier]);
        set_flash('danger', 'Please enter both your username (or email) and your password.');
        redirect('login.php');
    }

    /* ----- Look the customer up ----------------------------------- */
    // One query handles both login styles. Prepared statement, so the
    // input can never be interpreted as SQL.
    $stmt = $pdo->prepare(
        'SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1'
    );
    $stmt->execute([$identifier, strtolower($identifier)]);
    $user = $stmt->fetch();

    /* ----- Verify the password ------------------------------------ */
    // password_verify() re-hashes the supplied password with the salt
    // stored inside the hash and compares the two in constant time.
    $passwordOk = $user && password_verify($password, $user['password']);

    if (!$passwordOk) {
        $_SESSION['login_attempts'] = $attempts + 1;

        if ($_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
            $_SESSION['login_locked_at'] = time();
            set_flash('danger', 'Too many failed attempts. Sign-in is locked for 5 minutes.');
        } else {
            $remaining = MAX_LOGIN_ATTEMPTS - $_SESSION['login_attempts'];
            set_flash('danger', 'Invalid credentials. Please check your details and try again. (' . $remaining . ' attempt(s) remaining)');
        }

        set_old(['identifier' => $identifier]);
        redirect('login.php');
    }

    /* ----- Blocked accounts cannot sign in ------------------------ */
    if ($user['status'] !== 'Active') {
        set_flash('danger', 'Your account is currently deactivated. Please call 1800 200 4000 for assistance.');
        set_old(['identifier' => $identifier]);
        redirect('login.php');
    }

    /* ----- Success ------------------------------------------------ */

    // Upgrade the stored hash if PHP's default algorithm has moved on.
    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $update  = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $update->execute([$newHash, $user['id']]);
    }

    // Clear the throttle counters and start the session.
    unset($_SESSION['login_attempts'], $_SESSION['login_locked_at']);
    clear_old();

    login_customer($user);

    set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');
    redirect('customer/dashboard.php');
}

/* =====================================================================
 | PAGE OUTPUT
 * ================================================================== */
$suppressFlash = true;   // alerts appear inside the card

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<section class="auth-section">
    <div class="hero-bg" aria-hidden="true">
        <span class="hero-orb hero-orb-1"></span>
        <span class="hero-orb hero-orb-2"></span>
        <span class="hero-grid"></span>
    </div>

    <div class="container position-relative">
        <div class="row justify-content-center align-items-center g-5">

            <!-- Marketing side -->
            <div class="col-lg-6 d-none d-lg-block">
                <span class="hero-badge"><i class="bi bi-shield-lock-fill"></i> Secure sign-in</span>
                <h1 class="auth-aside-title">Your bank,<br>ready when you are</h1>
                <p class="auth-aside-sub">
                    Check balances, move money and review every transaction you have
                    ever made &mdash; all from one clean dashboard.
                </p>

                <ul class="auth-aside-list">
                    <li><i class="bi bi-check-circle-fill"></i> Real-time balance across all your accounts</li>
                    <li><i class="bi bi-check-circle-fill"></i> Instant transfers with permanent receipts</li>
                    <li><i class="bi bi-check-circle-fill"></i> Searchable, filterable statement history</li>
                    <li><i class="bi bi-check-circle-fill"></i> Sessions end automatically when you go idle</li>
                </ul>

                <div class="auth-aside-note">
                    <i class="bi bi-info-circle"></i>
                    <span>
                        We will never ask for your password by phone or email.
                        If someone does, hang up and call 1800 200 4000.
                    </span>
                </div>
            </div>

            <!-- Sign-in card -->
            <div class="col-lg-5">
                <div class="auth-card reveal">

                    <div class="auth-head text-center">
                        <span class="auth-icon"><i class="bi bi-box-arrow-in-right"></i></span>
                        <h2 class="auth-title">Internet Banking</h2>
                        <p class="auth-sub">Sign in to continue to your dashboard</p>
                    </div>

                    <?= show_flash() ?>

                    <form method="post" action="<?= url('login.php') ?>" novalidate>
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label for="identifier" class="form-label">Username or email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control form-control-lg" id="identifier"
                                       name="identifier" value="<?= old('identifier') ?>" required autofocus
                                       placeholder="Enter username or email">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control form-control-lg" id="password"
                                       name="password" required placeholder="Enter password">
                                <button class="btn btn-eye" type="button" data-toggle-password="#password" aria-label="Show password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                                <label class="form-check-label small" for="remember">Keep me signed in</label>
                            </div>
                            <a href="<?= url('contact.php') ?>" class="small link-navy">Forgot password?</a>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary-glow btn-lg">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Sign in securely
                            </button>
                        </div>
                    </form>

                    <div class="auth-divider"><span>New to <?= e(BANK_NAME) ?>?</span></div>

                    <div class="d-grid">
                        <a href="<?= url('register.php') ?>" class="btn btn-outline-navy btn-lg">
                            Open an account in 3 minutes
                        </a>
                    </div>

                    <p class="auth-foot">
                        <i class="bi bi-shield-lock me-1"></i>
                        Bank staff? <a href="<?= url('admin/login.php') ?>">Use the admin console</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
clear_old();
require_once __DIR__ . '/includes/footer.php';
?>
