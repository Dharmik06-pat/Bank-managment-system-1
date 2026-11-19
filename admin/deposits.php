<?php
/**
 * =====================================================================
 * admin/deposits.php  -  Deposit management
 * ---------------------------------------------------------------------
 * Two jobs on one screen:
 *   1. Monitor every deposit made through the customer portal.
 *   2. Post a counter deposit, the way a branch teller would when a
 *      customer hands over cash at the counter.
 *
 * Posting a deposit CREATES a new transaction. It never edits an
 * existing one, so the ledger stays append-only.
 *
 * The money movement uses the same pattern as the customer side:
 * a database transaction, and a row lock taken BEFORE the balance is
 * read, so two simultaneous updates cannot overwrite each other.
 * =====================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

$admin     = require_admin();
$pageTitle = 'Deposit Management';

define('ADMIN_MAX_DEPOSIT', 1000000);   // 10 lakh per counter deposit

/* =====================================================================
 | POST:  RECORD A COUNTER DEPOSIT
 * ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_csrf('admin/deposits.php');

    $accountNumber = preg_replace('/\s+/', '', post('account_number'));
    $amount        = parse_amount(post('amount'));
    $description   = post('description');

    $errors = [];

    /* ----- Validate the input ------------------------------------- */
    if (!preg_match('/^[0-9]{12}$/', $accountNumber)) {
        $errors[] = 'Enter a valid 12-digit account number.';
    }

    if ($amount === null || $amount <= 0) {
        $errors[] = 'Enter a deposit amount greater than zero.';
    } elseif ($amount > ADMIN_MAX_DEPOSIT) {
        $errors[] = 'A single counter deposit cannot exceed ' . money(ADMIN_MAX_DEPOSIT) . '.';
    }

    if (mb_strlen($description) > 255) {
        $errors[] = 'The description is too long (max 255 characters).';
    }
    if ($description === '') {
        $description = 'Counter deposit posted by ' . $admin['username'];
    }

    /* ----- Find the account --------------------------------------- */
    $account = null;
    if (!$errors) {
        $stmt = $pdo->prepare(
            'SELECT a.*, u.full_name, u.status AS owner_status
             FROM accounts a
             INNER JOIN users u ON u.id = a.user_id
             WHERE a.account_number = ? LIMIT 1'
        );
        $stmt->execute([$accountNumber]);
        $account = $stmt->fetch();

        if (!$account) {
            $errors[] = 'No account exists with the number ' . $accountNumber . '.';
        } elseif ($account['account_status'] !== 'Active') {
            $errors[] = 'That account is frozen. Unfreeze it before posting a deposit.';
        } elseif ($account['owner_status'] !== 'Active') {
            $errors[] = 'The account holder is deactivated. Reactivate the customer first.';
        }
    }

    if ($errors) {
        set_old($_POST);
        foreach ($errors as $error) {
            set_flash('danger', $error);
        }
        redirect('admin/deposits.php');
    }

    /* ----- Move the money, all or nothing ------------------------- */
    try {
        $pdo->beginTransaction();

        // Lock the row first, then read the balance from the locked copy.
        $lock = $pdo->prepare('SELECT * FROM accounts WHERE id = ? FOR UPDATE');
        $lock->execute([(int) $account['id']]);
        $locked = $lock->fetch();

        if (!$locked) {
            $pdo->rollBack();
            set_flash('danger', 'That account disappeared while the deposit was being posted.');
            redirect('admin/deposits.php');
        }

        $newBalance = (float) $locked['balance'] + $amount;

        $update = $pdo->prepare('UPDATE accounts SET balance = ? WHERE id = ?');
        $update->execute([$newBalance, (int) $account['id']]);

        $insert = $pdo->prepare(
            'INSERT INTO transactions
                (transaction_id, account_id, related_account_number, transaction_type,
                 amount, description, status)
             VALUES (?, ?, NULL, ?, ?, ?, ?)'
        );
        $insert->execute([
            generate_transaction_id($pdo),
            (int) $account['id'],
            'Deposit',
            $amount,
            $description,
            'Success',
        ]);

        $pdo->commit();

        error_log('[BMS] Admin ' . $admin['username'] . ' posted a counter deposit of '
                  . $amount . ' to ' . $accountNumber);

        clear_old();
        set_flash(
            'success',
            money($amount) . ' deposited into ' . $accountNumber . ' ('
            . $account['full_name'] . '). New balance: ' . money($newBalance) . '.'
        );
        redirect('admin/deposits.php');

    } catch (Throwable $ex) {
        // Throwable, not just PDOException: any error raised between
        // beginTransaction() and commit() must still reach rollBack(),
        // or the row locks are held until the connection is dropped.
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[BMS] Counter deposit failed: ' . $ex->getMessage());
        set_old($_POST);
        set_flash('danger', 'The deposit could not be completed. Nothing was changed. Please try again.');
        redirect('admin/deposits.php');
    }
}

/* =====================================================================
 | SUMMARY FIGURES
 * ================================================================== */
$summary = $pdo->query(
    "SELECT
        COUNT(*)                     AS cnt,
        COALESCE(SUM(amount), 0)     AS total,
        COALESCE(AVG(amount), 0)     AS average,
        COALESCE(MAX(amount), 0)     AS largest
     FROM transactions
     WHERE transaction_type = 'Deposit' AND status = 'Success'"
)->fetch();

$todayRow = $pdo->query(
    "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total
     FROM transactions
     WHERE transaction_type = 'Deposit' AND status = 'Success'
       AND DATE(created_at) = CURDATE()"
)->fetch();

/* =====================================================================
 | FILTERS
 * ================================================================== */
$search   = get('search');
$dateFrom = get('from');
$dateTo   = get('to');
$sortBy   = get('sort', 'date_desc');
$page     = max(1, (int) get('page', '1'));

$where  = ["t.transaction_type = 'Deposit'"];
$params = [];

if ($search !== '') {
    $where[] = '(t.transaction_id LIKE ? OR a.account_number LIKE ? OR u.full_name LIKE ?
                 OR u.username LIKE ? OR t.description LIKE ?)';
    $like    = '%' . $search . '%';
    $params  = array_merge($params, array_fill(0, 5, $like));
}

if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where[]  = 'DATE(t.created_at) >= ?';
    $params[] = $dateFrom;
} else {
    $dateFrom = '';
}

if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where[]  = 'DATE(t.created_at) <= ?';
    $params[] = $dateTo;
} else {
    $dateTo = '';
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$sortOptions = [
    'date_desc'   => 't.created_at DESC, t.id DESC',
    'date_asc'    => 't.created_at ASC, t.id ASC',
    'amount_desc' => 't.amount DESC, t.id DESC',
    'amount_asc'  => 't.amount ASC, t.id ASC',
];
if (!isset($sortOptions[$sortBy])) {
    $sortBy = 'date_desc';
}

$joinSql = 'FROM transactions t
            INNER JOIN accounts a ON a.id = t.account_id
            INNER JOIN users u    ON u.id = a.user_id';

$countStmt = $pdo->prepare("SELECT COUNT(*) $joinSql $whereSql");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();

$totalPages = max(1, (int) ceil($totalRows / RECORDS_PER_PAGE));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * RECORDS_PER_PAGE;

$stmt = $pdo->prepare(
    "SELECT t.*, a.account_number, a.account_type, a.balance, u.full_name, u.username, u.id AS user_id
     $joinSql $whereSql
     ORDER BY {$sortOptions[$sortBy]}
     LIMIT " . (int) RECORDS_PER_PAGE . " OFFSET " . (int) $offset
);
$stmt->execute($params);
$deposits = $stmt->fetchAll();

$sumStmt = $pdo->prepare("SELECT COALESCE(SUM(t.amount), 0) $joinSql $whereSql");
$sumStmt->execute($params);
$filteredTotal = (float) $sumStmt->fetchColumn();

$hasFilters = ($search !== '' || $dateFrom !== '' || $dateTo !== '');

require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- ==================================================================
     SUMMARY TILES
=================================================================== -->
<div class="row g-3 mb-3 mb-xl-4">
    <div class="col-lg-3 col-md-6">
        <div class="mini-stat">
            <span class="ms-icon bg-success-soft"><i class="bi bi-box-arrow-in-down"></i></span>
            <div>
                <span class="ms-label">Total deposited</span>
                <span class="ms-value text-success"><?= money($summary['total']) ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="mini-stat">
            <span class="ms-icon bg-primary-soft"><i class="bi bi-receipt"></i></span>
            <div>
                <span class="ms-label">Deposits recorded</span>
                <span class="ms-value"><?= number_format((int) $summary['cnt']) ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="mini-stat">
            <span class="ms-icon bg-info-soft"><i class="bi bi-graph-up"></i></span>
            <div>
                <span class="ms-label">Average deposit</span>
                <span class="ms-value"><?= money($summary['average']) ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="mini-stat">
            <span class="ms-icon bg-secondary-soft"><i class="bi bi-calendar-day"></i></span>
            <div>
                <span class="ms-label">Today</span>
                <span class="ms-value">
                    <?= money($todayRow['total']) ?>
                    <small class="text-muted">/ <?= number_format((int) $todayRow['cnt']) ?></small>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 g-xl-4">

    <!-- ==============================================================
         COUNTER DEPOSIT FORM
    =============================================================== -->
    <div class="col-xl-4">
        <div class="panel-card">
            <div class="panel-card-head">
                <h3><i class="bi bi-cash-coin me-2"></i>Post a counter deposit</h3>
            </div>

            <div class="panel-card-body">
                <p class="text-muted small mb-4">
                    Use this when a customer pays cash in at the branch. The deposit is
                    added to the ledger exactly like an online one.
                </p>

                <form method="post" action="<?= url('admin/deposits.php') ?>" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="account_number" class="form-label">
                            Account number <span class="req">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-credit-card-2-front"></i></span>
                            <input type="text" class="form-control mono-text" id="account_number"
                                   name="account_number" value="<?= old('account_number') ?>"
                                   maxlength="12" inputmode="numeric" required data-digits-only
                                   data-verify-account="<?= url('admin/verify_account.php') ?>"
                                   placeholder="12-digit account number" autocomplete="off">
                        </div>
                        <div class="account-check" id="accountCheck"></div>
                    </div>

                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount <span class="req">*</span></label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text"><?= CURRENCY_SYMBOL ?></span>
                            <input type="number" class="form-control" id="amount" name="amount"
                                   value="<?= old('amount') ?>" step="0.01" min="1"
                                   max="<?= ADMIN_MAX_DEPOSIT ?>" required placeholder="0.00">
                        </div>
                        <div class="form-text">Maximum <?= money(ADMIN_MAX_DEPOSIT) ?> per counter deposit.</div>
                    </div>

                    <div class="quick-amounts mb-3">
                        <?php foreach ([500, 1000, 5000, 10000, 25000] as $quick): ?>
                            <button type="button" class="quick-amt" data-amount="<?= $quick ?>" data-target="#amount">
                                <?= CURRENCY_SYMBOL ?><?= number_format($quick) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="form-label">Description</label>
                        <input type="text" class="form-control" id="description" name="description"
                               value="<?= old('description') ?>" maxlength="255"
                               placeholder="e.g. Cash deposit at main branch">
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary-glow btn-lg"
                                data-confirm="Post this deposit? It will be added to the ledger permanently and cannot be deleted.">
                            <i class="bi bi-check2-circle me-1"></i> Post deposit
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="info-note mt-3">
            <i class="bi bi-shield-check"></i>
            <div>
                <strong>Append-only ledger</strong>
                <p class="mb-0">
                    Posting a deposit adds a new record. If you post one by mistake,
                    correct it with a matching withdrawal so both entries stay visible
                    in the history.
                </p>
            </div>
        </div>
    </div>

    <!-- ==============================================================
         DEPOSIT HISTORY
    =============================================================== -->
    <div class="col-xl-8">

        <div class="panel-card mb-3">
            <div class="panel-card-head">
                <h3><i class="bi bi-funnel me-2"></i>Filter deposits</h3>
                <?php if ($hasFilters): ?>
                    <span class="text-muted small">Filtered total: <strong><?= money($filteredTotal) ?></strong></span>
                <?php endif; ?>
            </div>

            <div class="panel-card-body">
                <form method="get" action="<?= url('admin/deposits.php') ?>" class="row g-3 filter-form">

                    <div class="col-md-5">
                        <label for="search" class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="search" name="search"
                                   value="<?= e($search) ?>" placeholder="Reference, account or customer">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label for="from" class="form-label">From</label>
                        <input type="date" class="form-control" id="from" name="from" value="<?= e($dateFrom) ?>">
                    </div>

                    <div class="col-md-3">
                        <label for="to" class="form-label">To</label>
                        <input type="date" class="form-control" id="to" name="to" value="<?= e($dateTo) ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="sort" class="form-label">Sort by</label>
                        <select class="form-select" id="sort" name="sort">
                            <option value="date_desc"   <?= $sortBy === 'date_desc'   ? 'selected' : '' ?>>Newest first</option>
                            <option value="date_asc"    <?= $sortBy === 'date_asc'    ? 'selected' : '' ?>>Oldest first</option>
                            <option value="amount_desc" <?= $sortBy === 'amount_desc' ? 'selected' : '' ?>>Highest amount</option>
                            <option value="amount_asc"  <?= $sortBy === 'amount_asc'  ? 'selected' : '' ?>>Lowest amount</option>
                        </select>
                    </div>

                    <div class="col-md-8 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary-glow">
                            <i class="bi bi-funnel me-1"></i> Apply
                        </button>
                        <?php if ($hasFilters): ?>
                            <a href="<?= url('admin/deposits.php') ?>" class="btn btn-light-soft">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-card-head">
                <h3><i class="bi bi-list-ul me-2"></i>All deposits</h3>
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
                <?php if (!$deposits): ?>
                    <div class="empty-inline">
                        <i class="bi bi-box-arrow-in-down"></i>
                        <p>
                            <?= $hasFilters
                                ? 'No deposits match your filters.'
                                : 'No deposits have been recorded yet.' ?>
                        </p>
                        <?php if ($hasFilters): ?>
                            <a href="<?= url('admin/deposits.php') ?>" class="btn btn-light-soft btn-sm">Clear filters</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table data-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>Customer</th>
                                    <th>Account</th>
                                    <th>Description</th>
                                    <th>Date</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deposits as $deposit): ?>
                                    <tr>
                                        <td><span class="txn-ref"><?= e($deposit['transaction_id']) ?></span></td>
                                        <td>
                                            <a href="<?= url('admin/customers.php?action=view&id=' . (int) $deposit['user_id']) ?>"
                                               class="fw-semibold text-decoration-none"><?= e($deposit['full_name']) ?></a>
                                        </td>
                                        <td><span class="mono-text"><?= e($deposit['account_number']) ?></span></td>
                                        <td class="text-muted small"><?= e($deposit['description'] ?: '-') ?></td>
                                        <td class="text-muted small"><?= e(pretty_date($deposit['created_at'])) ?></td>
                                        <td class="text-end fw-semibold text-success">+ <?= money($deposit['amount']) ?></td>
                                        <td class="text-center"><?= transaction_status_badge($deposit['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="panel-card-foot">
                    <nav aria-label="Deposit pages">
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
    </div>
</div>

<?php
clear_old();
require_once __DIR__ . '/../includes/admin_footer.php';
?>
