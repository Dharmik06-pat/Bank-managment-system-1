<?php
/**
 * =====================================================================
 * admin/profile.php  -  Administrator profile and password
 * ---------------------------------------------------------------------
 * An administrator may change their display name and their password.
 * The username stays fixed: it is the sign-in identity and it appears
 * in the server log lines written whenever an admin changes something.
 * =====================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

$admin     = require_admin();
$pageTitle = 'Admin Profile';

/* =====================================================================
 | FORM HANDLING
 * ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_csrf('admin/profile.php');

    $formAction = post('form_action');

    /* ---------------------------------------------------------------
     | Update the display name
     * ------------------------------------------------------------ */
    if ($formAction === 'update_profile') {

        $fullName = post('full_name');
        $errors   = [];

        if ($fullName === '' || !preg_match("/^[a-zA-Z][a-zA-Z .'-]{2,99}$/", $fullName)) {
            $errors[] = 'Enter a valid full name (letters, spaces, apostrophes and hyphens only).';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('UPDATE admins SET full_name = ? WHERE id = ?');
            $stmt->execute([$fullName, $admin['id']]);

            // Keep the name shown in the sidebar in step with the database.
            $_SESSION['admin_name'] = $fullName;

            clear_old();
            set_flash('success', 'Your profile has been updated.');
            redirect('admin/profile.php');
        }

        set_old($_POST);
        foreach ($errors as $error) {
            set_flash('danger', $error);
        }
        redirect('admin/profile.php');
    }

    /* ---------------------------------------------------------------
     | Change the password
     * ------------------------------------------------------------ */
    if ($formAction === 'change_password') {

        $currentPassword = raw_post('current_password');
        $newPassword     = raw_post('new_password');
        $confirmPassword = raw_post('confirm_password');

        $errors = [];

        // 1. The current password must be correct.
        if ($currentPassword === '') {
            $errors[] = 'Please enter your current password.';
        } elseif (!password_verify($currentPassword, $admin['password'])) {
            $errors[] = 'Your current password is incorrect.';
        }

        // 2. The new password must meet the rules.
        if ($newPassword === '') {
            $errors[] = 'Please enter a new password.';
        } else {
            foreach (password_problems($newPassword) as $problem) {
                $errors[] = $problem;
            }
        }

        // 3. Confirmation must match.
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'The new password and its confirmation do not match.';
        }

        // 4. It must actually be different.
        if ($newPassword !== '' && $currentPassword !== ''
            && password_verify($newPassword, $admin['password'])) {
            $errors[] = 'Your new password must be different from your current one.';
        }

        // 5. Refuse to keep the well-known default.
        if (strtolower($newPassword) === 'admin123') {
            $errors[] = 'That is the default password shipped with the project. Choose something else.';
        }

        if (!$errors) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare('UPDATE admins SET password = ? WHERE id = ?');
            $stmt->execute([$newHash, $admin['id']]);

            // Rotate the session ID so any captured session is cut off.
            session_regenerate_id(true);

            error_log('[BMS] Admin ' . $admin['username'] . ' changed their password.');

            set_flash('success', 'Your administrator password has been changed.');
            redirect('admin/profile.php');
        }

        foreach ($errors as $error) {
            set_flash('danger', $error);
        }
        redirect('admin/profile.php');
    }

    set_flash('danger', 'Unknown action.');
    redirect('admin/profile.php');
}

/* =====================================================================
 | CONTEXT FOR THE PAGE
 * ================================================================== */
$adminCount = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();

// Is the default password still in place? Worth warning about.
$usingDefaultPassword = password_verify('admin123', $admin['password']);

require_once __DIR__ . '/../includes/admin_header.php';
?>

<?php if ($usingDefaultPassword): ?>
    <div class="info-note info-note-warn mb-3 mb-xl-4">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div>
            <strong>You are still using the default password</strong>
            <p class="mb-0">
                Every copy of this project ships with <code>admin123</code>, and it is
                printed on the sign-in page. Anyone who can reach this site can sign in
                as an administrator until you change it. Use the form below.
            </p>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3 g-xl-4">

    <!-- ==============================================================
         IDENTITY CARD
    =============================================================== -->
    <div class="col-xl-4">
        <div class="panel-card profile-card">
            <div class="profile-cover profile-cover-admin" aria-hidden="true"></div>

            <div class="profile-body">
                <span class="avatar-circle avatar-lg avatar-admin"><?= e(initials($admin['full_name'])) ?></span>
                <h3 class="profile-name"><?= e($admin['full_name']) ?></h3>
                <p class="profile-username">@<?= e($admin['username']) ?></p>
                <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis">
                    <i class="bi bi-shield-check me-1"></i>Administrator
                </span>

                <ul class="profile-meta">
                    <li>
                        <i class="bi bi-hash"></i>
                        <div><span>Admin ID</span><strong>#<?= (int) $admin['id'] ?></strong></div>
                    </li>
                    <li>
                        <i class="bi bi-person-badge"></i>
                        <div><span>Username</span><strong><?= e($admin['username']) ?></strong></div>
                    </li>
                    <li>
                        <i class="bi bi-calendar-check"></i>
                        <div><span>Account created</span><strong><?= e(pretty_date($admin['created_at'])) ?></strong></div>
                    </li>
                    <li>
                        <i class="bi bi-people"></i>
                        <div><span>Administrators</span><strong><?= number_format($adminCount) ?> in total</strong></div>
                    </li>
                </ul>
            </div>
        </div>

        <div class="info-note mt-3">
            <i class="bi bi-key"></i>
            <div>
                <strong>Adding another administrator</strong>
                <p class="mb-0">
                    There is deliberately no "create admin" button in this panel &mdash; it
                    would be the single most dangerous form in the project. Add one through
                    phpMyAdmin using a hash produced by <code>password_hash()</code>. The
                    README shows the exact snippet.
                </p>
            </div>
        </div>
    </div>

    <!-- ==============================================================
         FORMS
    =============================================================== -->
    <div class="col-xl-8">

        <!-- ---------- Display name ---------- -->
        <div class="panel-card mb-3 mb-xl-4">
            <div class="panel-card-head">
                <h3><i class="bi bi-person-gear me-2"></i>Profile details</h3>
            </div>

            <div class="panel-card-body">
                <form method="post" action="<?= url('admin/profile.php') ?>" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="update_profile">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label">Full name <span class="req">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="full_name" name="full_name"
                                       value="<?= old('full_name', $admin['full_name']) ?>" maxlength="100" required>
                            </div>
                            <div class="form-text">Shown in the sidebar and the top bar.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="text" class="form-control" value="<?= e($admin['username']) ?>" disabled>
                            </div>
                            <div class="form-text">Fixed &mdash; it identifies you in the activity log.</div>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-primary-glow">
                                <i class="bi bi-check2-circle me-1"></i> Save profile
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ---------- Password ---------- -->
        <div class="panel-card">
            <div class="panel-card-head">
                <h3><i class="bi bi-shield-lock me-2"></i>Change password</h3>
            </div>

            <div class="panel-card-body">
                <form method="post" action="<?= url('admin/profile.php') ?>" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="change_password">

                    <div class="mb-3">
                        <label for="current_password" class="form-label">
                            Current password <span class="req">*</span>
                        </label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="current_password"
                                   name="current_password" required
                                   placeholder="Enter your current password">
                            <button class="btn btn-eye" type="button" data-toggle-password="#current_password"
                                    aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="row g-3">
                        <div class="col-md-6">
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

                        <div class="col-md-6">
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

                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-primary-glow btn-lg">
                                <i class="bi bi-shield-check me-1"></i> Update password
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
clear_old();
require_once __DIR__ . '/../includes/admin_footer.php';
?>
