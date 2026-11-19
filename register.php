<?php
/**
 * =====================================================================
 * register.php  -  Customer registration
 * ---------------------------------------------------------------------
 * Validates every field on the server, checks that the email and
 * username are not already taken, hashes the password with bcrypt and
 * creates the customer together with their first savings account.
 *
 * The INSERT into users and the INSERT into accounts run inside one
 * database transaction, so a customer can never be created without an
 * account (or the other way round).
 * =====================================================================
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth_check.php';

// Already signed in? Straight to the dashboard.
redirect_if_logged_in();

$pageTitle       = 'Open an Account';
$pageDescription = 'Open a free savings or current account with ' . BANK_NAME . ' in minutes.';

/* =====================================================================
 | FORM HANDLING
 * ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_csrf('register.php');

    /* ----- 1. Collect input --------------------------------------- */
    $fullName    = post('full_name');
    $email       = strtolower(post('email'));
    $mobile      = post('mobile');
    $dob         = post('date_of_birth');
    $gender      = post('gender');
    $address     = post('address');
    $city        = post('city');
    $state       = post('state');
    $pinCode     = post('pin_code');
    $username    = post('username');
    $password    = raw_post('password');
    $confirmPass = raw_post('confirm_password');
    $accountType = post('account_type', 'Savings Account');
    $agreed      = isset($_POST['terms']);

    $errors = [];

    /* ----- 2. Validate every field -------------------------------- */

    // Full name: letters, spaces, dots and apostrophes only
    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    } elseif (mb_strlen($fullName) < 3 || mb_strlen($fullName) > 120) {
        $errors[] = 'Full name must be between 3 and 120 characters.';
    } elseif (!preg_match("/^[A-Za-z .']+$/", $fullName)) {
        $errors[] = 'Full name may only contain letters, spaces, dots and apostrophes.';
    }

    // Email
    if ($email === '') {
        $errors[] = 'Email address is required.';
    } elseif (!is_valid_email($email)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (mb_strlen($email) > 150) {
        $errors[] = 'Email address is too long.';
    }

    // Mobile
    if ($mobile === '') {
        $errors[] = 'Mobile number is required.';
    } elseif (!is_valid_mobile($mobile)) {
        $errors[] = 'Mobile number must be 10 digits and start with 6, 7, 8 or 9.';
    }

    // Date of birth: must be a real date and at least 18 years ago
    if ($dob === '') {
        $errors[] = 'Date of birth is required.';
    } else {
        $dateParts = explode('-', $dob);
        $validDate = count($dateParts) === 3
            && checkdate((int) $dateParts[1], (int) $dateParts[2], (int) $dateParts[0]);

        if (!$validDate) {
            $errors[] = 'Please enter a valid date of birth.';
        } else {
            $age = age_from_dob($dob);
            if ($age === null || $age < 18) {
                $errors[] = 'You must be at least 18 years old to open an account.';
            } elseif ($age > 120) {
                $errors[] = 'Please check the date of birth you entered.';
            }
        }
    }

    // Gender
    if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
        $errors[] = 'Please select a gender.';
    }

    // Address block
    if ($address === '' || mb_strlen($address) < 5) {
        $errors[] = 'Please enter your full address (at least 5 characters).';
    } elseif (mb_strlen($address) > 255) {
        $errors[] = 'Address is too long (max 255 characters).';
    }

    if ($city === '' || mb_strlen($city) > 80) {
        $errors[] = 'Please enter a valid city.';
    }

    if ($state === '' || mb_strlen($state) > 80) {
        $errors[] = 'Please select your state.';
    }

    if (!is_valid_pin($pinCode)) {
        $errors[] = 'PIN code must be exactly 6 digits.';
    }

    // Username
    if ($username === '') {
        $errors[] = 'Username is required.';
    } elseif (!is_valid_username($username)) {
        $errors[] = 'Username must be 4-20 characters using only letters, numbers and underscores.';
    }

    // Password
    if ($password === '') {
        $errors[] = 'Password is required.';
    } else {
        foreach (password_problems($password) as $problem) {
            $errors[] = $problem;
        }
        if ($password !== $confirmPass) {
            $errors[] = 'Password and confirm password do not match.';
        }
    }

    // Account type
    if (!in_array($accountType, ['Savings Account', 'Current Account'], true)) {
        $errors[] = 'Please choose a valid account type.';
    }

    // Terms
    if (!$agreed) {
        $errors[] = 'Please accept the terms and conditions to continue.';
    }

    /* ----- 3. Uniqueness checks (prepared statements) ------------- */
    if (!$errors) {
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetchColumn()) {
            $errors[] = 'That email address is already registered. Try signing in instead.';
        }

        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        if ($stmt->fetchColumn()) {
            $errors[] = 'That username is already taken. Please choose another.';
        }
    }

    /* ----- 4. Save the customer ----------------------------------- */
    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Hash the password. Never store the plain text.
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                'INSERT INTO users
                    (full_name, email, mobile, date_of_birth, gender,
                     address, city, state, pin_code, username, password, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Active")'
            );
            $stmt->execute([
                $fullName, $email, $mobile, $dob, $gender,
                $address, $city, $state, $pinCode, $username, $passwordHash,
            ]);

            $newUserId = (int) $pdo->lastInsertId();

            // Give the new customer their first account straight away.
            $accountNumber = generate_account_number($pdo);

            $stmt = $pdo->prepare(
                'INSERT INTO accounts
                    (user_id, account_number, account_type, balance, account_status)
                 VALUES (?, ?, ?, 0.00, "Active")'
            );
            $stmt->execute([$newUserId, $accountNumber, $accountType]);

            $pdo->commit();

            clear_old();
            set_flash('success', 'Welcome aboard! Your account number is ' . $accountNumber . '. Please sign in to get started.');
            redirect('login.php');

        } catch (Throwable $ex) {
            // Throwable, not just PDOException: generate_account_number()
            // raises a RuntimeException if it cannot find a free number,
            // and that happens *inside* the transaction. Catching only
            // PDOException would let it escape past the rollBack() and
            // leave the INSERT above holding locks.
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[BMS] Registration failed: ' . $ex->getMessage());
            $errors[] = 'Something went wrong while creating your account. Please try again.';
        }
    }

    /* ----- 5. Fall through: redisplay the form with errors -------- */
    set_old($_POST);
    foreach ($errors as $error) {
        set_flash('danger', $error);
    }
    redirect('register.php');
}

/* =====================================================================
 | PAGE OUTPUT
 * ================================================================== */
$suppressFlash = true;   // alerts are rendered inside the form card

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

// The 28 Indian states and 8 union territories, for the dropdown.
$indianStates = [
    'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
    'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka',
    'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram',
    'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu',
    'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
    'Andaman and Nicobar Islands', 'Chandigarh',
    'Dadra and Nagar Haveli and Daman and Diu', 'Delhi', 'Jammu and Kashmir',
    'Ladakh', 'Lakshadweep', 'Puducherry',
];

// Customers must be 18+, so the date picker stops 18 years ago.
$maxDob = date('Y-m-d', strtotime('-18 years'));
?>

<section class="auth-section">
    <div class="hero-bg" aria-hidden="true">
        <span class="hero-orb hero-orb-1"></span>
        <span class="hero-grid"></span>
    </div>

    <div class="container position-relative">
        <div class="row justify-content-center">
            <div class="col-xl-10">

                <div class="auth-card auth-card-wide reveal">

                    <div class="auth-head text-center">
                        <span class="auth-icon"><i class="bi bi-person-plus"></i></span>
                        <h1 class="auth-title">Open your account</h1>
                        <p class="auth-sub">
                            It takes about three minutes. Your account number is generated
                            as soon as you finish.
                        </p>
                    </div>

                    <?= show_flash() ?>

                    <form method="post" action="<?= url('register.php') ?>" novalidate id="registerForm">
                        <?= csrf_field() ?>

                        <!-- ============ Personal details ============ -->
                        <h2 class="form-section-title"><span>1</span> Personal details</h2>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="full_name" class="form-label">Full name <span class="req">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="full_name" name="full_name"
                                           value="<?= old('full_name') ?>" maxlength="120" required
                                           placeholder="As printed on your ID">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Email address <span class="req">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?= old('email') ?>" maxlength="150" required
                                           placeholder="you@example.com">
                                </div>
                                <div class="form-text">You cannot change this later.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="mobile" class="form-label">Mobile number <span class="req">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">+91</span>
                                    <input type="tel" class="form-control" id="mobile" name="mobile"
                                           value="<?= old('mobile') ?>" maxlength="10" inputmode="numeric"
                                           pattern="[6-9][0-9]{9}" required placeholder="10-digit number"
                                           data-digits-only>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="date_of_birth" class="form-label">Date of birth <span class="req">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                                           value="<?= old('date_of_birth') ?>" max="<?= e($maxDob) ?>" required>
                                </div>
                                <div class="form-text">You must be 18 or older.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label d-block">Gender <span class="req">*</span></label>
                                <div class="d-flex flex-wrap gap-3">
                                    <?php foreach (['Male', 'Female', 'Other'] as $genderOption): ?>
                                        <div class="form-check radio-pill">
                                            <input class="form-check-input" type="radio" name="gender"
                                                   id="gender<?= e($genderOption) ?>" value="<?= e($genderOption) ?>"
                                                   <?= old_is('gender', $genderOption) ? 'checked' : ($genderOption === 'Male' && !isset($_SESSION['old']['gender']) ? 'checked' : '') ?>>
                                            <label class="form-check-label" for="gender<?= e($genderOption) ?>"><?= e($genderOption) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- ============ Address ============ -->
                        <h2 class="form-section-title mt-4"><span>2</span> Address</h2>

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="address" class="form-label">Street address <span class="req">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-house"></i></span>
                                    <input type="text" class="form-control" id="address" name="address"
                                           value="<?= old('address') ?>" maxlength="255" required
                                           placeholder="House / flat number, street, area">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="city" class="form-label">City <span class="req">*</span></label>
                                <input type="text" class="form-control" id="city" name="city"
                                       value="<?= old('city') ?>" maxlength="80" required placeholder="City">
                            </div>

                            <div class="col-md-4">
                                <label for="state" class="form-label">State <span class="req">*</span></label>
                                <select class="form-select" id="state" name="state" required>
                                    <option value="">Choose your state</option>
                                    <?php foreach ($indianStates as $stateOption): ?>
                                        <option value="<?= e($stateOption) ?>" <?= old_is('state', $stateOption) ? 'selected' : '' ?>>
                                            <?= e($stateOption) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="pin_code" class="form-label">PIN code <span class="req">*</span></label>
                                <input type="text" class="form-control" id="pin_code" name="pin_code"
                                       value="<?= old('pin_code') ?>" maxlength="6" inputmode="numeric"
                                       pattern="[1-9][0-9]{5}" required placeholder="6 digits" data-digits-only>
                            </div>
                        </div>

                        <!-- ============ Account & security ============ -->
                        <h2 class="form-section-title mt-4"><span>3</span> Account &amp; security</h2>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label d-block">Account type <span class="req">*</span></label>
                                <div class="row g-3">
                                    <?php
                                    $accountChoices = [
                                        'Savings Account' => ['bi-piggy-bank', 'Earn 4.5% interest. Best for personal use.'],
                                        'Current Account' => ['bi-briefcase',  'Higher limits. Best for business use.'],
                                    ];
                                    $hasOldType = isset($_SESSION['old']['account_type']);
                                    foreach ($accountChoices as $type => $meta):
                                        [$icon, $blurb] = $meta;
                                        $checked = $hasOldType
                                            ? old_is('account_type', $type)
                                            : ($type === 'Savings Account');
                                    ?>
                                        <div class="col-md-6">
                                            <label class="choice-card">
                                                <input type="radio" name="account_type" value="<?= e($type) ?>" <?= $checked ? 'checked' : '' ?>>
                                                <span class="choice-inner">
                                                    <i class="bi <?= e($icon) ?>"></i>
                                                    <span class="choice-text">
                                                        <strong><?= e($type) ?></strong>
                                                        <small><?= e($blurb) ?></small>
                                                    </span>
                                                    <i class="bi bi-check-circle-fill choice-tick"></i>
                                                </span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="username" class="form-label">Username <span class="req">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-at"></i></span>
                                    <input type="text" class="form-control" id="username" name="username"
                                           value="<?= old('username') ?>" maxlength="20" required
                                           pattern="[A-Za-z0-9_]{4,20}" placeholder="4-20 characters">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="password" class="form-label">Password <span class="req">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password"
                                           required minlength="8" placeholder="Min 8 characters"
                                           data-strength-target="#pwStrength">
                                    <button class="btn btn-eye" type="button" data-toggle-password="#password" aria-label="Show password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="pw-strength" id="pwStrength">
                                    <span class="pw-bar"><span></span></span>
                                    <small class="pw-text">Use 8+ characters with letters and numbers</small>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="confirm_password" class="form-label">Confirm password <span class="req">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" class="form-control" id="confirm_password"
                                           name="confirm_password" required minlength="8"
                                           placeholder="Re-type password" data-match="#password">
                                    <button class="btn btn-eye" type="button" data-toggle-password="#confirm_password" aria-label="Show password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text match-text"></div>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="terms" name="terms" value="1" required>
                                    <label class="form-check-label" for="terms">
                                        I confirm the details above are correct and I accept the
                                        <a href="<?= url('services.php') ?>" target="_blank" rel="noopener">terms of service</a>.
                                    </label>
                                </div>
                            </div>

                            <div class="col-12 d-grid">
                                <button type="submit" class="btn btn-primary-glow btn-lg">
                                    <i class="bi bi-shield-check me-1"></i> Create my account
                                </button>
                            </div>
                        </div>
                    </form>

                    <p class="auth-foot">
                        Already registered? <a href="<?= url('login.php') ?>">Sign in to internet banking</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
clear_old();   // old input has been rendered, do not keep it around
require_once __DIR__ . '/includes/footer.php';
?>
