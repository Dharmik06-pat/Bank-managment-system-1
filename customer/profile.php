<?php
/**
 * =====================================================================
 * customer/profile.php  -  View and edit personal details
 * ---------------------------------------------------------------------
 * Editable:     mobile, address, city, state, PIN code
 * Locked:       account number, email, username, name, date of birth
 *
 * The locked fields are rendered as disabled inputs AND ignored by the
 * update query. Disabling an input in HTML only stops honest users;
 * the server simply never reads those values.
 * =====================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user      = require_customer();
$pageTitle = 'My Profile';

/* =====================================================================
 | FORM HANDLING
 * ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_csrf('customer/profile.php');

    // Only these five fields are ever read from the request.
    $mobile  = post('mobile');
    $address = post('address');
    $city    = post('city');
    $state   = post('state');
    $pinCode = post('pin_code');

    $errors = [];

    if (!is_valid_mobile($mobile)) {
        $errors[] = 'Mobile number must be 10 digits and start with 6, 7, 8 or 9.';
    }

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

    if (!$errors) {
        try {
            // Note the WHERE clause: a customer can only ever update
            // their own row, whatever is posted.
            $stmt = $pdo->prepare(
                'UPDATE users
                 SET mobile = ?, address = ?, city = ?, state = ?, pin_code = ?
                 WHERE id = ?'
            );
            $stmt->execute([$mobile, $address, $city, $state, $pinCode, $user['id']]);

            clear_old();
            set_flash('success', 'Your profile has been updated.');
            redirect('customer/profile.php');

        } catch (PDOException $ex) {
            error_log('[BMS] Profile update failed: ' . $ex->getMessage());
            $errors[] = 'We could not save your changes. Please try again.';
        }
    }

    set_old($_POST);
    foreach ($errors as $error) {
        set_flash('danger', $error);
    }
    redirect('customer/profile.php');
}

/* =====================================================================
 | DATA
 * ================================================================== */
$accounts = get_user_accounts($pdo, (int) $user['id']);

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

// Prefer old input (after a failed save) over the stored value.
$currentState = $_SESSION['old']['state'] ?? $user['state'];

require_once __DIR__ . '/../includes/customer_header.php';
?>

<div class="row g-3 g-xl-4">

    <!-- ==============================================================
         IDENTITY CARD
    =============================================================== -->
    <div class="col-lg-4">
        <div class="panel-card profile-card">
            <div class="profile-cover" aria-hidden="true"></div>

            <div class="profile-body">
                <span class="avatar-circle avatar-lg"><?= e(initials($user['full_name'])) ?></span>
                <h3 class="profile-name"><?= e($user['full_name']) ?></h3>
                <p class="profile-username">@<?= e($user['username']) ?></p>
                <?= status_badge($user['status']) ?>

                <ul class="profile-meta">
                    <li>
                        <i class="bi bi-envelope"></i>
                        <div><span>Email</span><strong><?= e($user['email']) ?></strong></div>
                    </li>
                    <li>
                        <i class="bi bi-telephone"></i>
                        <div><span>Mobile</span><strong>+91 <?= e($user['mobile']) ?></strong></div>
                    </li>
                    <li>
                        <i class="bi bi-calendar-event"></i>
                        <div><span>Date of birth</span><strong><?= e(pretty_date($user['date_of_birth'], false)) ?></strong></div>
                    </li>
                    <li>
                        <i class="bi bi-person"></i>
                        <div><span>Gender</span><strong><?= e($user['gender']) ?></strong></div>
                    </li>
                    <li>
                        <i class="bi bi-clock-history"></i>
                        <div><span>Customer since</span><strong><?= e(pretty_date($user['created_at'], false)) ?></strong></div>
                    </li>
                </ul>

                <a href="<?= url('customer/change_password.php') ?>" class="btn btn-light-soft w-100 mt-3">
                    <i class="bi bi-shield-lock me-1"></i> Change password
                </a>
            </div>
        </div>

        <!-- Linked accounts -->
        <div class="panel-card mt-3">
            <div class="panel-card-head">
                <h3><i class="bi bi-wallet2 me-2"></i>Linked accounts</h3>
            </div>
            <div class="panel-card-body p-0">
                <?php if (!$accounts): ?>
                    <div class="empty-inline">
                        <i class="bi bi-wallet2"></i>
                        <p>No accounts yet.</p>
                    </div>
                <?php else: ?>
                    <ul class="mini-list">
                        <?php foreach ($accounts as $account): ?>
                            <li>
                                <span class="mini-icon neutral">
                                    <i class="bi <?= $account['account_type'] === 'Savings Account' ? 'bi-piggy-bank' : 'bi-briefcase' ?>"></i>
                                </span>
                                <div class="mini-meta">
                                    <strong><?= e($account['account_number']) ?></strong>
                                    <small><?= e($account['account_type']) ?></small>
                                </div>
                                <span class="mini-amt"><?= money($account['balance']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ==============================================================
         EDIT FORM
    =============================================================== -->
    <div class="col-lg-8">
        <div class="panel-card">
            <div class="panel-card-head">
                <h3><i class="bi bi-person-gear me-2"></i>Edit your details</h3>
            </div>

            <div class="panel-card-body">

                <div class="info-note info-note-sm mb-4">
                    <i class="bi bi-lock-fill"></i>
                    <div>
                        <strong>Some details are locked</strong>
                        <p class="mb-0">
                            Your name, email, username and date of birth cannot be changed
                            online because they are tied to your account identity. Call
                            1800 200 4000 if any of them is wrong.
                        </p>
                    </div>
                </div>

                <form method="post" action="<?= url('customer/profile.php') ?>" novalidate>
                    <?= csrf_field() ?>

                    <!-- ===== Locked fields (display only) ===== -->
                    <h4 class="form-section-title"><span><i class="bi bi-lock"></i></span> Locked details</h4>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Full name</label>
                            <input type="text" class="form-control" value="<?= e($user['full_name']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?= e($user['username']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email address</label>
                            <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date of birth</label>
                            <input type="text" class="form-control"
                                   value="<?= e(pretty_date($user['date_of_birth'], false)) ?>" disabled>
                        </div>
                    </div>

                    <!-- ===== Editable fields ===== -->
                    <h4 class="form-section-title"><span><i class="bi bi-pencil"></i></span> Contact details</h4>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="mobile" class="form-label">Mobile number <span class="req">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">+91</span>
                                <input type="tel" class="form-control" id="mobile" name="mobile"
                                       value="<?= old('mobile', $user['mobile']) ?>" maxlength="10"
                                       inputmode="numeric" pattern="[6-9][0-9]{9}" required data-digits-only>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="pin_code" class="form-label">PIN code <span class="req">*</span></label>
                            <input type="text" class="form-control" id="pin_code" name="pin_code"
                                   value="<?= old('pin_code', $user['pin_code']) ?>" maxlength="6"
                                   inputmode="numeric" pattern="[1-9][0-9]{5}" required data-digits-only>
                        </div>

                        <div class="col-12">
                            <label for="address" class="form-label">Street address <span class="req">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-house"></i></span>
                                <input type="text" class="form-control" id="address" name="address"
                                       value="<?= old('address', $user['address']) ?>" maxlength="255" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="city" class="form-label">City <span class="req">*</span></label>
                            <input type="text" class="form-control" id="city" name="city"
                                   value="<?= old('city', $user['city']) ?>" maxlength="80" required>
                        </div>

                        <div class="col-md-6">
                            <label for="state" class="form-label">State <span class="req">*</span></label>
                            <select class="form-select" id="state" name="state" required>
                                <option value="">Choose your state</option>
                                <?php foreach ($indianStates as $stateOption): ?>
                                    <option value="<?= e($stateOption) ?>" <?= $currentState === $stateOption ? 'selected' : '' ?>>
                                        <?= e($stateOption) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 d-flex flex-wrap gap-2 mt-4">
                            <button type="submit" class="btn btn-primary-glow btn-lg">
                                <i class="bi bi-check2-circle me-1"></i> Save changes
                            </button>
                            <a href="<?= url('customer/dashboard.php') ?>" class="btn btn-light-soft btn-lg">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
clear_old();
require_once __DIR__ . '/../includes/customer_footer.php';
?>
