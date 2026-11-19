<?php
/**
 * =====================================================================
 * customer/change_password.php  -  Change the sign-in password
 * ---------------------------------------------------------------------
 * Requires the current password before allowing a change. Without that
 * check, anyone who found an unattended signed-in browser could lock
 * the real owner out of their account.
 * =====================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user      = require_customer();
$pageTitle = 'Change Password';

/* =====================================================================
 | FORM HANDLING
 * ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_csrf('customer/change_password.php');

    $currentPassword = raw_post('current_password');
    $newPassword     = raw_post('new_password');
    $confirmPassword = raw_post('confirm_password');

    $errors = [];

    /* ----- 1. The current password must be right ------------------ */
    if ($currentPassword === '') {
        $errors[] = 'Please enter your current password.';
    } elseif (!password_verify($currentPassword, $user['password'])) {
        $errors[] = 'Your current password is incorrect.';
    }

    /* ----- 2. The new password must meet the rules ---------------- */
    if ($newPassword === '') {
        $errors[] = 'Please enter a new password.';
    } else {
        foreach (password_problems($newPassword) as $problem) {
            $errors[] = $problem;
        }
    }

    /* ----- 3. Confirmation must match ----------------------------- */
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'The new password and its confirmation do not match.';
    }

    /* ----- 4. It must actually be a different password ------------ */
    if ($newPassword !== '' && $currentPassword !== ''
        && password_verify($newPassword, $user['password'])) {
        $errors[] = 'Your new password must be different from your current one.';
    }

    if (!$errors) {
        try {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([$newHash, $user['id']]);

            /* Rotate the session ID. If someone had somehow captured the
               old session, changing the password now cuts them off. */
            session_regenerate_id(true);

            set_flash('success', 'Your password has been changed. Use it the next time you sign in.');
            redirect('customer/change_password.php');

        } catch (PDOException $ex) {
            error_log('[BMS] Password change failed: ' . $ex->getMessage());
            $errors[] = 'We could not update your password. Please try again.';
        }
    }

    foreach ($errors as $error) {
        set_flash('danger', $error);
    }
    redirect('customer/change_password.php');
}

require_once __DIR__ . '/../includes/customer_header.php';
?>

<div class="row g-3 g-xl-4 justify-content-center">

    <div class="col-lg-7">
        <div class="panel-card">
            <div class="panel-card-head">
                <h3><i class="bi bi-shield-lock me-2"></i>Change your password</h3>
            </div>

            <div class="panel-card-body">
                <p class="text-muted small mb-4">
                    Choose something you do not use anywhere else. You will stay signed in
                    on this device after the change.
                </p>

                <form method="post" action="<?= url('customer/change_password.php') ?>" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="current_password" class="form-label">
                            Current password <span class="req">*</span>
                        </label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="current_password"
                                   name="current_password" required autofocus
                                   placeholder="Enter your current password">
                            <button class="btn btn-eye" type="button" data-toggle-password="#current_password"
                                    aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="mb-3">
                        <label for="new_password" class="form-label">
                            New password <span class="req">*</span>
                        </label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="password" class="form-control" id="new_password" name="new_password"
                                   required minlength="8" placeholder="At least 8 characters"
                                   data-strength-target="#pwStrength">
                            <button class="btn btn-eye" type="button" data-toggle-password="#new_password"
                                    aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="pw-strength" id="pwStrength">
                            <span class="pw-bar"><span></span></span>
                            <small class="pw-text">Use 8+ characters with letters and numbers</small>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">
                            Confirm new password <span class="req">*</span>
                        </label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                            <input type="password" class="form-control" id="confirm_password"
                                   name="confirm_password" required minlength="8"
                                   placeholder="Re-type the new password" data-match="#new_password">
                            <button class="btn btn-eye" type="button" data-toggle-password="#confirm_password"
                                    aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text match-text"></div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary-glow btn-lg">
                            <i class="bi bi-check2-circle me-1"></i> Update password
                        </button>
                        <a href="<?= url('customer/dashboard.php') ?>" class="btn btn-light-soft btn-lg">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Advice panel -->
    <div class="col-lg-5">
        <div class="info-note mb-3">
            <i class="bi bi-fingerprint"></i>
            <div>
                <strong>How we store your password</strong>
                <p class="mb-0">
                    Your password is hashed with bcrypt before it touches the database.
                    Nobody at the bank, including an administrator, can read it. That is
                    also why a forgotten password has to be reset rather than looked up.
                </p>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-card-head">
                <h3><i class="bi bi-lightbulb me-2"></i>Password tips</h3>
            </div>
            <div class="panel-card-body">
                <ul class="tick-list mb-0">
                    <li><i class="bi bi-check-circle-fill"></i> Use at least 8 characters &mdash; longer is stronger</li>
                    <li><i class="bi bi-check-circle-fill"></i> Mix letters, numbers and symbols</li>
                    <li><i class="bi bi-check-circle-fill"></i> Avoid names, birthdays and phone numbers</li>
                    <li><i class="bi bi-check-circle-fill"></i> Never reuse your banking password elsewhere</li>
                    <li><i class="bi bi-x-circle-fill text-danger"></i> Never share it, even with bank staff</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/customer_footer.php'; ?>
