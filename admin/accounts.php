<?php
/**
 * =====================================================================
 * admin/accounts.php  -  Manage bank accounts
 * ---------------------------------------------------------------------
 * Search and filter every account in the bank, and freeze or unfreeze
 * one when needed.
 *
 * Note what is deliberately absent: there is no way to edit a balance
 * from this screen. A balance is the sum of its transaction history,
 * so the only honest way to change it is to record a transaction.
 * Letting an admin type a new number would break that guarantee.
 * =====================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

$admin     = require_admin();
$pageTitle = 'Manage Accounts';

/* =====================================================================
 | POST:  FREEZE / UNFREEZE AN ACCOUNT
 * ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_csrf('admin/accounts.php');

    $accountId  = (int) post('account_id');
    $postAction = post('form_action');

    $stmt = $pdo->prepare(
        'SELECT a.*, u.full_name FROM accounts a
         INNER JOIN users u ON u.id = a.user_id
         WHERE a.id = ? LIMIT 1'
    );
    $stmt->execute([$accountId]);
    $account = $stmt->fetch();

    if (!$account) {
        set_flash('danger', 'That account could not be found.');
        redirect('admin/accounts.php');
    }

    if ($postAction === 'toggle_status') {

        $newStatus = $account['account_status'] === 'Active' ? 'Inactive' : 'Active';

        $update = $pdo->prepare('UPDATE accounts SET account_status = ? WHERE id = ?');
        $update->execute([$newStatus, $accountId]);

        error_log('[BMS] Admin ' . $admin['username'] . ' set account '
                  . $account['account_number'] . ' to ' . $newStatus);

        set_flash(
            $newStatus === 'Active' ? 'success' : 'warning',
            'Account ' . $account['account_number'] . ' (' . $account['full_name'] . ') is now ' . strtolower($newStatus) . '.'
            . ($newStatus === 'Inactive'
                ? ' Deposits, withdrawals and incoming transfers are blocked. The balance is untouched.'
                : ' Normal banking has resumed.')
        );

        // Return to the list. The form posts without a query string, so
        // there are no filters to preserve here.
        redirect('admin/accounts.php');
    }

    set_flash('danger', 'Unknown action.');
    redirect('admin/accounts.php');
}

/* =====================================================================
 | SUMMARY FIGURES
 * ================================================================== */
$summary = $pdo->query(
    "SELECT
        COUNT(*)                                                             AS total,
        COALESCE(SUM(balance), 0)                                            AS total_balance,
        SUM(CASE WHEN account_status = 'Active' THEN 1 ELSE 0 END)           AS active,
        SUM(CASE WHEN account_type = 'Savings Account' THEN 1 ELSE 0 END)    AS savings
     FROM accounts"
)->fetch();

/* =====================================================================
 | FILTERS
 * ================================================================== */
$search       = get('search');
$filterType   = get('type');
$filterStatus = get('status');
$sortBy       = get('sort', 'newest');
$page         = max(1, (int) get('page', '1'));

$where  = [];
$params = [];

if ($search !== '') {
    $where[] = '(a.account_number LIKE ? OR u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
    $like    = '%' . $search . '%';
    $params  = array_merge($params, [$like, $like, $like, $like]);
}

if (in_array($filterType, ['Savings Account', 'Current Account'], true)) {
    $where[]  = 'a.account_type = ?';
    $params[] = $filterType;
} else {
    $filterType = '';
}

if (in_array($filterStatus, ['Active', 'Inactive'], true)) {
    $where[]  = 'a.account_status = ?';
    $params[] = $filterStatus;
} else {
    $filterStatus = '';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sortOptions = [
    'newest'       => 'a.created_at DESC, a.id DESC',
    'oldest'       => 'a.created_at ASC, a.id ASC',
    'balance_desc' => 'a.balance DESC',
    'balance_asc'  => 'a.balance ASC',
    'name_asc'     => 'u.full_name ASC',
];
if (!isset($sortOptions[$sortBy])) {
    $sortBy = 'newest';
}

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM accounts a INNER JOIN users u ON u.id = a.user_id $whereSql"
);
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();

$totalPages = max(1, (int) ceil($totalRows / RECORDS_PER_PAGE));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * RECORDS_PER_PAGE;

$sql = "SELECT a.*, u.full_name, u.username, u.email, u.status AS owner_status,
               (SELECT COUNT(*) FROM transactions t WHERE t.account_id = a.id) AS txn_count
        FROM accounts a
        INNER JOIN users u ON u.id = a.user_id
        $whereSql
        ORDER BY {$sortOptions[$sortBy]}
        LIMIT " . (int) RECORDS_PER_PAGE . " OFFSET " . (int) $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$accounts = $stmt->fetchAll();

// Balance across the filtered set, not just this page.
$sumStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(a.balance), 0) FROM accounts a INNER JOIN users u ON u.id = a.user_id $whereSql"
);
$sumStmt->execute($params);
$filteredBalance = (float) $sumStmt->fetchColumn();

$hasFilters = ($search !== '' || $filterType !== '' || $filterStatus !== '');

require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- ==================================================================
     SUMMARY TILES
=================================================================== -->
<div class="row g-3 mb-3 mb-xl-4">
    <div class="col-lg-3 col-md-6">
        <div class="mini-stat">
            <span class="ms-icon bg-primary-soft"><i class="bi bi-wallet2"></i></span>
            <div>
                <span class="ms-label">Total accounts</span>
                <span class="ms-value"><?= number_format((int) $summary['total']) ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="mini-stat">
            <span class="ms-icon bg-success-soft"><i class="bi bi-check-circle"></i></span>
            <div>
                <span class="ms-label">Active accounts</span>
                <span class="ms-value"><?= number_format((int) $summary['active']) ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="mini-stat">
            <span class="ms-icon bg-info-soft"><i class="bi bi-piggy-bank"></i></span>
            <div>
                <span class="ms-label">Savings / Current</span>
                <span class="ms-value">
                    <?= number_format((int) $summary['savings']) ?>
                    <small class="text-muted">/ <?= number_format((int) $summary['total'] - (int) $summary['savings']) ?></small>
                </span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="mini-stat">
            <span class="ms-icon bg-secondary-soft"><i class="bi bi-cash-stack"></i></span>
            <div>
                <span class="ms-label">Total balance</span>
                <span class="ms-value"><?= money($summary['total_balance']) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- ==================================================================
     FILTERS
=================================================================== -->
<div class="panel-card mb-3 mb-xl-4">
    <div class="panel-card-head">
        <h3><i class="bi bi-funnel me-2"></i>Search &amp; filter</h3>
        <?php if ($hasFilters): ?>
            <span class="text-muted small">Filtered balance: <strong><?= money($filteredBalance) ?></strong></span>
        <?php endif; ?>
    </div>

    <div class="panel-card-body">
        <form method="get" action="<?= url('admin/accounts.php') ?>" class="row g-3 filter-form">

            <div class="col-lg-4 col-md-6">
                <label for="search" class="form-label">Search</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="search" name="search"
                           value="<?= e($search) ?>" placeholder="Account number, name, username or email">
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <label for="type" class="form-label">Account type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">All types</option>
                    <option value="Savings Account" <?= $filterType === 'Savings Account' ? 'selected' : '' ?>>Savings Account</option>
                    <option value="Current Account" <?= $filterType === 'Current Account' ? 'selected' : '' ?>>Current Account</option>
                </select>
            </div>

            <div class="col-lg-2 col-md-6">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    <option value="Active"   <?= $filterStatus === 'Active'   ? 'selected' : '' ?>>Active</option>
                    <option value="Inactive" <?= $filterStatus === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <div class="col-lg-3 col-md-6">
                <label for="sort" class="form-label">Sort by</label>
                <select class="form-select" id="sort" name="sort">
                    <option value="newest"       <?= $sortBy === 'newest'       ? 'selected' : '' ?>>Newest first</option>
                    <option value="oldest"       <?= $sortBy === 'oldest'       ? 'selected' : '' ?>>Oldest first</option>
                    <option value="balance_desc" <?= $sortBy === 'balance_desc' ? 'selected' : '' ?>>Highest balance</option>
                    <option value="balance_asc"  <?= $sortBy === 'balance_asc'  ? 'selected' : '' ?>>Lowest balance</option>
                    <option value="name_asc"     <?= $sortBy === 'name_asc'     ? 'selected' : '' ?>>Customer (A&ndash;Z)</option>
                </select>
            </div>

            <div class="col-12 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary-glow">
                    <i class="bi bi-funnel me-1"></i> Apply filters
                </button>
                <?php if ($hasFilters): ?>
                    <a href="<?= url('admin/accounts.php') ?>" class="btn btn-light-soft">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- ==================================================================
     ACCOUNT TABLE
=================================================================== -->
<div class="panel-card">
    <div class="panel-card-head">
        <h3><i class="bi bi-wallet2 me-2"></i>All accounts</h3>
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
        <?php if (!$accounts): ?>
            <div class="empty-inline">
                <i class="bi bi-wallet2"></i>
                <p>
                    <?= $hasFilters
                        ? 'No accounts match your filters.'
                        : 'No accounts have been opened yet. Accounts are created when a customer registers.' ?>
                </p>
                <?php if ($hasFilters): ?>
                    <a href="<?= url('admin/accounts.php') ?>" class="btn btn-light-soft btn-sm">Clear filters</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table data-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Account number</th>
                            <th>Account holder</th>
                            <th>Type</th>
                            <th>Opened</th>
                            <th class="text-center">Txns</th>
                            <th class="text-end">Balance</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $account): ?>
                            <!-- id lets ?highlight=<id> scroll straight to this row -->
                            <tr id="row-<?= (int) $account['id'] ?>">
                                <td>
                                    <span class="mono-text fw-semibold"><?= e($account['account_number']) ?></span>
                                </td>
                                <td>
                                    <div class="cell-user">
                                        <span class="avatar-circle avatar-sm"><?= e(initials($account['full_name'])) ?></span>
                                        <div class="cell-stack">
                                            <strong><?= e($account['full_name']) ?></strong>
                                            <small>
                                                @<?= e($account['username']) ?>
                                                <?php if ($account['owner_status'] !== 'Active'): ?>
                                                    <span class="text-danger">&middot; customer inactive</span>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="type-chip">
                                        <i class="bi <?= $account['account_type'] === 'Savings Account' ? 'bi-piggy-bank' : 'bi-briefcase' ?>"></i>
                                        <?= e(str_replace(' Account', '', $account['account_type'])) ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?= e(pretty_date($account['created_at'], false)) ?></td>
                                <td class="text-center"><?= number_format((int) $account['txn_count']) ?></td>
                                <td class="text-end fw-semibold"><?= money($account['balance']) ?></td>
                                <td class="text-center"><?= status_badge($account['account_status']) ?></td>
                                <td class="text-end">
                                    <a href="<?= url('admin/customers.php?action=view&id=' . (int) $account['user_id']) ?>"
                                       class="btn btn-sm btn-outline-primary" title="View customer">
                                        <i class="bi bi-person"></i>
                                    </a>
                                    <a href="<?= url('admin/transactions.php?search=' . urlencode($account['account_number'])) ?>"
                                       class="btn btn-sm btn-outline-secondary" title="View transactions">
                                        <i class="bi bi-receipt"></i>
                                    </a>

                                    <form method="post" action="<?= url('admin/accounts.php') ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="form_action" value="toggle_status">
                                        <input type="hidden" name="account_id" value="<?= (int) $account['id'] ?>">
                                        <button type="submit"
                                                class="btn btn-sm <?= $account['account_status'] === 'Active' ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                                title="<?= $account['account_status'] === 'Active' ? 'Freeze account' : 'Unfreeze account' ?>"
                                                data-confirm="<?= $account['account_status'] === 'Active'
                                                    ? 'Freeze account ' . e($account['account_number']) . '? All deposits, withdrawals and incoming transfers will be blocked. The balance is not affected.'
                                                    : 'Unfreeze account ' . e($account['account_number']) . ' and resume normal banking?' ?>">
                                            <i class="bi <?= $account['account_status'] === 'Active' ? 'bi-snow' : 'bi-play-circle' ?>"></i>
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
            <nav aria-label="Account pages">
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

<div class="info-note mt-3">
    <i class="bi bi-info-circle"></i>
    <div>
        <strong>Why balances cannot be edited here</strong>
        <p class="mb-0">
            An account balance is the running total of its transactions. Editing it
            directly would leave the ledger disagreeing with the balance and there
            would be no record of who changed what. To move money, record a deposit
            or a withdrawal instead.
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
