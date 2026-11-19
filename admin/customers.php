<?php
/**
 * =====================================================================
 * admin/customers.php  -  Manage customers
 * ---------------------------------------------------------------------
 * One file, three views, chosen by ?action=
 *     (none)  list    - searchable, filterable, paginated table
 *     view    detail  - full profile, accounts and recent activity
 *     edit    form    - update the customer's contact details
 *
 * State changes (edit, activate, deactivate) are POST-only and carry a
 * CSRF token. A plain link can be followed by an image tag or a stray
 * click, so it must never be able to deactivate somebody's account.
 * =====================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

$admin  = require_admin();
$action = get('action', 'list');
$id     = (int) get('id');

/* =====================================================================
 | POST HANDLERS
 * ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_csrf('admin/customers.php');

    $postAction = post('form_action');
    $customerId = (int) post('customer_id');

    // Confirm the customer exists before doing anything to them.
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();

    if (!$customer) {
        set_flash('danger', 'That customer could not be found.');
        redirect('admin/customers.php');
    }

    /* ---------------------------------------------------------------
     | Activate / deactivate
     |
     | Deactivating blocks sign-in and blocks incoming transfers, but
     | deliberately leaves the balance and the transaction history
     | untouched. Money and records are never destroyed from here.
     * ------------------------------------------------------------ */
    if ($postAction === 'toggle_status') {

        $newStatus = $customer['status'] === 'Active' ? 'Inactive' : 'Active';

        $update = $pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
        $update->execute([$newStatus, $customerId]);

        error_log('[BMS] Admin ' . $admin['username'] . ' set customer #' . $customerId . ' to ' . $newStatus);

        set_flash(
            $newStatus === 'Active' ? 'success' : 'warning',
            $customer['full_name'] . ' has been marked ' . strtolower($newStatus) . '.'
            . ($newStatus === 'Inactive'
                ? ' They can no longer sign in, and their accounts cannot receive transfers.'
                : ' They can sign in again.')
        );

        redirect('admin/customers.php?action=view&id=' . $customerId);
    }

    /* ---------------------------------------------------------------
     | Edit contact details
     |
     | An administrator may correct contact details, but the username
     | and the email address stay fixed - they are the customer's
     | identity and changing them would break the audit trail.
     * ------------------------------------------------------------ */
    if ($postAction === 'update_customer') {

        $fullName = post('full_name');
        $mobile   = post('mobile');
        $address  = post('address');
        $city     = post('city');
        $state    = post('state');
        $pinCode  = post('pin_code');
        $status   = post('status');

        $errors = [];

        if ($fullName === '' || !preg_match("/^[a-zA-Z][a-zA-Z .'-]{2,99}$/", $fullName)) {
            $errors[] = 'Enter a valid full name (letters, spaces, apostrophes and hyphens only).';
        }
        if (!is_valid_mobile($mobile)) {
            $errors[] = 'Mobile number must be 10 digits starting with 6, 7, 8 or 9.';
        }
        if ($address === '' || mb_strlen($address) < 5 || mb_strlen($address) > 255) {
            $errors[] = 'Address must be between 5 and 255 characters.';
        }
        if ($city === '' || mb_strlen($city) > 80) {
            $errors[] = 'Enter a valid city.';
        }
        if ($state === '' || mb_strlen($state) > 80) {
            $errors[] = 'Enter a valid state.';
        }
        if (!is_valid_pin($pinCode)) {
            $errors[] = 'PIN code must be exactly 6 digits.';
        }
        if (!in_array($status, ['Active', 'Inactive'], true)) {
            $errors[] = 'Status must be either Active or Inactive.';
        }

        if ($errors) {
            set_old($_POST);
            foreach ($errors as $error) {
                set_flash('danger', $error);
            }
            redirect('admin/customers.php?action=edit&id=' . $customerId);
        }

        $update = $pdo->prepare(
            'UPDATE users
             SET full_name = ?, mobile = ?, address = ?, city = ?, state = ?, pin_code = ?, status = ?
             WHERE id = ?'
        );
        $update->execute([$fullName, $mobile, $address, $city, $state, $pinCode, $status, $customerId]);

        clear_old();
        set_flash('success', 'Customer details updated for ' . $fullName . '.');
        redirect('admin/customers.php?action=view&id=' . $customerId);
    }

    set_flash('danger', 'Unknown action.');
    redirect('admin/customers.php');
}

/* =====================================================================
 | VIEW:  SINGLE CUSTOMER
 * ================================================================== */
if ($action === 'view' || $action === 'edit') {

    $stmt = $pdo->prepare(
        'SELECT u.*,
                (SELECT COUNT(*) FROM accounts a WHERE a.user_id = u.id) AS account_count,
                (SELECT COALESCE(SUM(a.balance), 0) FROM accounts a WHERE a.user_id = u.id) AS total_balance
         FROM users u WHERE u.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $customer = $stmt->fetch();

    if (!$customer) {
        set_flash('danger', 'That customer could not be found.');
        redirect('admin/customers.php');
    }

    $pageTitle = $action === 'edit' ? 'Edit Customer' : 'Customer Details';

    // Their accounts
    $acctStmt = $pdo->prepare(
        'SELECT a.*,
                (SELECT COUNT(*) FROM transactions t WHERE t.account_id = a.id) AS txn_count
         FROM accounts a WHERE a.user_id = ? ORDER BY a.id ASC'
    );
    $acctStmt->execute([$id]);
    $customerAccounts = $acctStmt->fetchAll();

    // Their last 10 transactions
    $txnStmt = $pdo->prepare(
        'SELECT t.*, a.account_number
         FROM transactions t
         INNER JOIN accounts a ON a.id = t.account_id
         WHERE a.user_id = ?
         ORDER BY t.created_at DESC, t.id DESC
         LIMIT 10'
    );
    $txnStmt->execute([$id]);
    $customerTransactions = $txnStmt->fetchAll();

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

    require_once __DIR__ . '/../includes/admin_header.php';
    ?>

    <div class="page-actions mb-3">
        <a href="<?= url('admin/customers.php') ?>" class="btn btn-light-soft">
            <i class="bi bi-arrow-left me-1"></i> Back to customers
        </a>

        <?php if ($action === 'view'): ?>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= url('admin/customers.php?action=edit&id=' . (int) $customer['id']) ?>"
                   class="btn btn-primary-glow">
                    <i class="bi bi-pencil-square me-1"></i> Edit details
                </a>

                <form method="post" action="<?= url('admin/customers.php') ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="toggle_status">
                    <input type="hidden" name="customer_id" value="<?= (int) $customer['id'] ?>">
                    <button type="submit"
                            class="btn <?= $customer['status'] === 'Active' ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                            data-confirm="<?= $customer['status'] === 'Active'
                                ? 'Deactivate this customer? They will not be able to sign in, and their accounts will stop receiving transfers.'
                                : 'Reactivate this customer and restore their access?' ?>">
                        <i class="bi <?= $customer['status'] === 'Active' ? 'bi-person-x' : 'bi-person-check' ?> me-1"></i>
                        <?= $customer['status'] === 'Active' ? 'Deactivate' : 'Activate' ?>
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($action === 'edit'): ?>

        <!-- ==========================================================
             EDIT FORM
        =========================================================== -->
        <div class="row g-3 g-xl-4 justify-content-center">
            <div class="col-xl-8">
                <div class="panel-card">
                    <div class="panel-card-head">
                        <h3><i class="bi bi-person-gear me-2"></i>Edit <?= e($customer['full_name']) ?></h3>
                    </div>

                    <div class="panel-card-body">

                        <div class="info-note info-note-sm mb-4">
                            <i class="bi bi-shield-lock"></i>
                            <div>
                                <strong>Identity fields are locked</strong>
                                <p class="mb-0">
                                    Username, email address, date of birth and account numbers cannot be
                                    changed from the admin console. They anchor the customer's identity
                                    and the audit trail depends on them staying stable.
                                </p>
                            </div>
                        </div>

                        <form method="post" action="<?= url('admin/customers.php') ?>" novalidate>
                            <?= csrf_field() ?>
                            <input type="hidden" name="form_action" value="update_customer">
                            <input type="hidden" name="customer_id" value="<?= (int) $customer['id'] ?>">

                            <h4 class="form-section-title"><span><i class="bi bi-lock"></i></span> Locked</h4>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" value="<?= e($customer['username']) ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email address</label>
                                    <input type="email" class="form-control" value="<?= e($customer['email']) ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Date of birth</label>
                                    <input type="text" class="form-control"
                                           value="<?= e(pretty_date($customer['date_of_birth'], false)) ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Gender</label>
                                    <input type="text" class="form-control" value="<?= e($customer['gender']) ?>" disabled>
                                </div>
                            </div>

                            <h4 class="form-section-title"><span><i class="bi bi-pencil"></i></span> Editable</h4>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="full_name" class="form-label">Full name <span class="req">*</span></label>
                                    <input type="text" class="form-control" id="full_name" name="full_name"
                                           value="<?= old('full_name', $customer['full_name']) ?>" maxlength="100" required>
                                </div>

                                <div class="col-md-6">
                                    <label for="mobile" class="form-label">Mobile <span class="req">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">+91</span>
                                        <input type="tel" class="form-control" id="mobile" name="mobile"
                                               value="<?= old('mobile', $customer['mobile']) ?>" maxlength="10"
                                               inputmode="numeric" required data-digits-only>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label for="address" class="form-label">Address <span class="req">*</span></label>
                                    <input type="text" class="form-control" id="address" name="address"
                                           value="<?= old('address', $customer['address']) ?>" maxlength="255" required>
                                </div>

                                <div class="col-md-4">
                                    <label for="city" class="form-label">City <span class="req">*</span></label>
                                    <input type="text" class="form-control" id="city" name="city"
                                           value="<?= old('city', $customer['city']) ?>" maxlength="80" required>
                                </div>

                                <div class="col-md-4">
                                    <label for="state" class="form-label">State <span class="req">*</span></label>
                                    <?php $selectedState = $_SESSION['old']['state'] ?? $customer['state']; ?>
                                    <select class="form-select" id="state" name="state" required>
                                        <option value="">Choose state</option>
                                        <?php foreach ($indianStates as $stateOption): ?>
                                            <option value="<?= e($stateOption) ?>" <?= $selectedState === $stateOption ? 'selected' : '' ?>>
                                                <?= e($stateOption) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label for="pin_code" class="form-label">PIN code <span class="req">*</span></label>
                                    <input type="text" class="form-control" id="pin_code" name="pin_code"
                                           value="<?= old('pin_code', $customer['pin_code']) ?>" maxlength="6"
                                           inputmode="numeric" required data-digits-only>
                                </div>

                                <div class="col-md-6">
                                    <label for="status" class="form-label">Account status <span class="req">*</span></label>
                                    <?php $selectedStatus = $_SESSION['old']['status'] ?? $customer['status']; ?>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="Active"   <?= $selectedStatus === 'Active'   ? 'selected' : '' ?>>Active &mdash; can sign in</option>
                                        <option value="Inactive" <?= $selectedStatus === 'Inactive' ? 'selected' : '' ?>>Inactive &mdash; access blocked</option>
                                    </select>
                                </div>

                                <div class="col-12 d-flex flex-wrap gap-2 mt-3">
                                    <button type="submit" class="btn btn-primary-glow btn-lg">
                                        <i class="bi bi-check2-circle me-1"></i> Save changes
                                    </button>
                                    <a href="<?= url('admin/customers.php?action=view&id=' . (int) $customer['id']) ?>"
                                       class="btn btn-light-soft btn-lg">Cancel</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>

        <!-- ==========================================================
             DETAIL VIEW
        =========================================================== -->
        <div class="row g-3 g-xl-4">

            <div class="col-xl-4">
                <div class="panel-card profile-card">
                    <div class="profile-cover profile-cover-admin" aria-hidden="true"></div>
                    <div class="profile-body">
                        <span class="avatar-circle avatar-lg"><?= e(initials($customer['full_name'])) ?></span>
                        <h3 class="profile-name"><?= e($customer['full_name']) ?></h3>
                        <p class="profile-username">@<?= e($customer['username']) ?></p>
                        <?= status_badge($customer['status']) ?>

                        <ul class="profile-meta">
                            <li><i class="bi bi-hash"></i>
                                <div><span>Customer ID</span><strong>#<?= (int) $customer['id'] ?></strong></div></li>
                            <li><i class="bi bi-envelope"></i>
                                <div><span>Email</span><strong><?= e($customer['email']) ?></strong></div></li>
                            <li><i class="bi bi-telephone"></i>
                                <div><span>Mobile</span><strong>+91 <?= e($customer['mobile']) ?></strong></div></li>
                            <li><i class="bi bi-calendar-event"></i>
                                <div><span>Date of birth</span><strong>
                                    <?= e(pretty_date($customer['date_of_birth'], false)) ?>
                                    (<?= age_from_dob($customer['date_of_birth']) ?> yrs)
                                </strong></div></li>
                            <li><i class="bi bi-person"></i>
                                <div><span>Gender</span><strong><?= e($customer['gender']) ?></strong></div></li>
                            <li><i class="bi bi-geo-alt"></i>
                                <div><span>Address</span><strong>
                                    <?= e($customer['address']) ?><br>
                                    <?= e($customer['city']) ?>, <?= e($customer['state']) ?> &ndash; <?= e($customer['pin_code']) ?>
                                </strong></div></li>
                            <li><i class="bi bi-clock-history"></i>
                                <div><span>Registered</span><strong><?= e(pretty_date($customer['created_at'])) ?></strong></div></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">

                <!-- Quick figures -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="mini-stat">
                            <span class="ms-icon bg-primary-soft"><i class="bi bi-wallet2"></i></span>
                            <div>
                                <span class="ms-label">Accounts held</span>
                                <span class="ms-value"><?= number_format((int) $customer['account_count']) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mini-stat">
                            <span class="ms-icon bg-success-soft"><i class="bi bi-cash-stack"></i></span>
                            <div>
                                <span class="ms-label">Combined balance</span>
                                <span class="ms-value"><?= money($customer['total_balance']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Accounts -->
                <div class="panel-card mb-3">
                    <div class="panel-card-head">
                        <h3><i class="bi bi-wallet2 me-2"></i>Accounts</h3>
                    </div>
                    <div class="panel-card-body p-0">
                        <?php if (!$customerAccounts): ?>
                            <div class="empty-inline">
                                <i class="bi bi-wallet2"></i>
                                <p>This customer has no accounts.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table data-table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Account number</th>
                                            <th>Type</th>
                                            <th>Opened</th>
                                            <th class="text-center">Transactions</th>
                                            <th class="text-end">Balance</th>
                                            <th class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($customerAccounts as $account): ?>
                                            <tr>
                                                <td><span class="mono-text fw-semibold"><?= e($account['account_number']) ?></span></td>
                                                <td><?= e($account['account_type']) ?></td>
                                                <td class="text-muted small"><?= e(pretty_date($account['created_at'], false)) ?></td>
                                                <td class="text-center"><?= number_format((int) $account['txn_count']) ?></td>
                                                <td class="text-end fw-semibold"><?= money($account['balance']) ?></td>
                                                <td class="text-center"><?= status_badge($account['account_status']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent activity -->
                <div class="panel-card">
                    <div class="panel-card-head">
                        <h3><i class="bi bi-clock-history me-2"></i>Recent activity</h3>
                        <a href="<?= url('admin/transactions.php?search=' . urlencode($customer['username'])) ?>" class="panel-link">
                            All transactions <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                    <div class="panel-card-body p-0">
                        <?php if (!$customerTransactions): ?>
                            <div class="empty-inline">
                                <i class="bi bi-receipt"></i>
                                <p>No transactions recorded for this customer.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table data-table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Reference</th>
                                            <th>Account</th>
                                            <th>Type</th>
                                            <th>Date</th>
                                            <th class="text-end">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($customerTransactions as $txn): ?>
                                            <tr>
                                                <td><span class="txn-ref"><?= e($txn['transaction_id']) ?></span></td>
                                                <td><span class="mono-text"><?= e(mask_account($txn['account_number'])) ?></span></td>
                                                <td><?= transaction_type_badge($txn['transaction_type']) ?></td>
                                                <td class="text-muted small"><?= e(pretty_date($txn['created_at'])) ?></td>
                                                <td class="text-end"><?= signed_amount($txn['transaction_type'], $txn['amount']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>

    <?php
    clear_old();
    require_once __DIR__ . '/../includes/admin_footer.php';
    exit;   // stop here - the list view below must not also render
}

/* =====================================================================
 | VIEW:  CUSTOMER LIST
 * ================================================================== */
$pageTitle = 'Manage Customers';

$search       = get('search');
$filterStatus = get('status');
$sortBy       = get('sort', 'newest');
$page         = max(1, (int) get('page', '1'));

$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = '(u.full_name LIKE ? OR u.email LIKE ? OR u.username LIKE ? OR u.mobile LIKE ? OR u.city LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like, $like, $like, $like]);
}

if (in_array($filterStatus, ['Active', 'Inactive'], true)) {
    $where[]  = 'u.status = ?';
    $params[] = $filterStatus;
} else {
    $filterStatus = '';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ORDER BY comes from a fixed list, never straight from the URL.
$sortOptions = [
    'newest'   => 'u.created_at DESC, u.id DESC',
    'oldest'   => 'u.created_at ASC, u.id ASC',
    'name_asc' => 'u.full_name ASC',
    'balance'  => 'total_balance DESC',
];
if (!isset($sortOptions[$sortBy])) {
    $sortBy = 'newest';
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u $whereSql");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();

$totalPages = max(1, (int) ceil($totalRows / RECORDS_PER_PAGE));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * RECORDS_PER_PAGE;

$sql = "SELECT u.*,
               (SELECT COUNT(*) FROM accounts a WHERE a.user_id = u.id) AS account_count,
               (SELECT COALESCE(SUM(a.balance), 0) FROM accounts a WHERE a.user_id = u.id) AS total_balance
        FROM users u
        $whereSql
        ORDER BY {$sortOptions[$sortBy]}
        LIMIT " . (int) RECORDS_PER_PAGE . " OFFSET " . (int) $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$hasFilters = ($search !== '' || $filterStatus !== '');

require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- ==================================================================
     FILTERS
=================================================================== -->
<div class="panel-card mb-3 mb-xl-4">
    <div class="panel-card-head">
        <h3><i class="bi bi-search me-2"></i>Find a customer</h3>
        <span class="text-muted small"><?= number_format($totalRows) ?> customer(s) match</span>
    </div>

    <div class="panel-card-body">
        <form method="get" action="<?= url('admin/customers.php') ?>" class="row g-3 filter-form">

            <div class="col-lg-5 col-md-6">
                <label for="search" class="form-label">Search</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="search" name="search"
                           value="<?= e($search) ?>" placeholder="Name, email, username, mobile or city">
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All customers</option>
                    <option value="Active"   <?= $filterStatus === 'Active'   ? 'selected' : '' ?>>Active only</option>
                    <option value="Inactive" <?= $filterStatus === 'Inactive' ? 'selected' : '' ?>>Inactive only</option>
                </select>
            </div>

            <div class="col-lg-2 col-md-6">
                <label for="sort" class="form-label">Sort by</label>
                <select class="form-select" id="sort" name="sort">
                    <option value="newest"   <?= $sortBy === 'newest'   ? 'selected' : '' ?>>Newest first</option>
                    <option value="oldest"   <?= $sortBy === 'oldest'   ? 'selected' : '' ?>>Oldest first</option>
                    <option value="name_asc" <?= $sortBy === 'name_asc' ? 'selected' : '' ?>>Name (A&ndash;Z)</option>
                    <option value="balance"  <?= $sortBy === 'balance'  ? 'selected' : '' ?>>Highest balance</option>
                </select>
            </div>

            <div class="col-lg-2 col-md-6 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary-glow w-100">
                    <i class="bi bi-funnel me-1"></i> Apply
                </button>
                <?php if ($hasFilters): ?>
                    <a href="<?= url('admin/customers.php') ?>" class="btn btn-light-soft">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- ==================================================================
     RESULTS
=================================================================== -->
<div class="panel-card">
    <div class="panel-card-head">
        <h3><i class="bi bi-people me-2"></i>Customers</h3>
        <span class="text-muted small">
            <?php if ($totalRows > 0): ?>
                Showing <?= number_format($offset + 1) ?>&ndash;<?= number_format(min($page * RECORDS_PER_PAGE, $totalRows)) ?>
                of <?= number_format($totalRows) ?>
            <?php else: ?>
                No results
            <?php endif; ?>
        </span>
    </div>

    <div class="panel-card-body p-0">
        <?php if (!$customers): ?>
            <div class="empty-inline">
                <i class="bi bi-people"></i>
                <p>
                    <?= $hasFilters
                        ? 'No customers match your search. Try a different term or clear the filters.'
                        : 'No customers have registered yet.' ?>
                </p>
                <?php if ($hasFilters): ?>
                    <a href="<?= url('admin/customers.php') ?>" class="btn btn-light-soft btn-sm">Clear filters</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table data-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Location</th>
                            <th class="text-center">Accounts</th>
                            <th class="text-end">Balance</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td>
                                    <div class="cell-user">
                                        <span class="avatar-circle avatar-sm"><?= e(initials($customer['full_name'])) ?></span>
                                        <div class="cell-stack">
                                            <strong><?= e($customer['full_name']) ?></strong>
                                            <small>@<?= e($customer['username']) ?> &middot; joined <?= e(pretty_date($customer['created_at'], false)) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="cell-stack">
                                        <span><?= e($customer['email']) ?></span>
                                        <small>+91 <?= e($customer['mobile']) ?></small>
                                    </div>
                                </td>
                                <td class="text-muted small">
                                    <?= e($customer['city']) ?><br><?= e($customer['state']) ?>
                                </td>
                                <td class="text-center"><?= number_format((int) $customer['account_count']) ?></td>
                                <td class="text-end fw-semibold"><?= money($customer['total_balance']) ?></td>
                                <td class="text-center"><?= status_badge($customer['status']) ?></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= url('admin/customers.php?action=view&id=' . (int) $customer['id']) ?>"
                                           class="btn btn-outline-primary" title="View details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="<?= url('admin/customers.php?action=edit&id=' . (int) $customer['id']) ?>"
                                           class="btn btn-outline-secondary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </div>

                                    <form method="post" action="<?= url('admin/customers.php') ?>" class="d-inline ms-1">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="form_action" value="toggle_status">
                                        <input type="hidden" name="customer_id" value="<?= (int) $customer['id'] ?>">
                                        <button type="submit"
                                                class="btn btn-sm <?= $customer['status'] === 'Active' ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                                title="<?= $customer['status'] === 'Active' ? 'Deactivate' : 'Activate' ?>"
                                                data-confirm="<?= $customer['status'] === 'Active'
                                                    ? 'Deactivate ' . e($customer['full_name']) . '? They will not be able to sign in.'
                                                    : 'Reactivate ' . e($customer['full_name']) . '?' ?>">
                                            <i class="bi <?= $customer['status'] === 'Active' ? 'bi-person-x' : 'bi-person-check' ?>"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============ PAGINATION ============ -->
    <?php if ($totalPages > 1): ?>
        <div class="panel-card-foot">
            <nav aria-label="Customer pages">
                <ul class="pagination pagination-sm mb-0 justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e(query_with(['page' => $page - 1])) ?>"><i class="bi bi-chevron-left"></i></a>
                    </li>

                    <?php
                    $windowStart = max(1, $page - 2);
                    $windowEnd   = min($totalPages, $page + 2);

                    if ($windowStart > 1): ?>
                        <li class="page-item"><a class="page-link" href="<?= e(query_with(['page' => 1])) ?>">1</a></li>
                        <?php if ($windowStart > 2): ?>
                            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                        <?php endif;
                    endif;

                    for ($p = $windowStart; $p <= $windowEnd; $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= e(query_with(['page' => $p])) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor;

                    if ($windowEnd < $totalPages):
                        if ($windowEnd < $totalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= e(query_with(['page' => $totalPages])) ?>"><?= $totalPages ?></a>
                        </li>
                    <?php endif; ?>

                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e(query_with(['page' => $page + 1])) ?>"><i class="bi bi-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
