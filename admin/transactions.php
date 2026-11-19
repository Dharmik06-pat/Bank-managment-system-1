<?php
/**
 * =====================================================================
 * admin/transactions.php  -  Monitor transactions  (READ ONLY)
 * ---------------------------------------------------------------------
 * The specification is explicit: transactions must not be editable or
 * deletable, for security and data integrity. This file honours that
 * literally - it contains no UPDATE and no DELETE statement anywhere,
 * and it accepts no POST request at all.
 *
 * The reason matters. A ledger is only trustworthy if entries are
 * permanent. If an administrator could quietly edit a past transaction,
 * the balance could no longer be proven from the history, and fraud
 * would be indistinguishable from a correction. Real banks fix mistakes
 * by posting a reversing entry, never by rewriting the original.
 * =====================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

$admin     = require_admin();
$pageTitle = 'Manage Transactions';

/* This page is read-only. Reject any attempt to post to it. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(405);
    set_flash('danger', 'Transactions cannot be modified. This page is read-only by design.');
    redirect('admin/transactions.php');
}

/* =====================================================================
 | FILTERS
 * ================================================================== */
$search       = get('search');
$filterType   = get('type');
$filterStatus = get('status');
$dateFrom     = get('from');
$dateTo       = get('to');
$minAmount    = get('min');
$sortBy       = get('sort', 'date_desc');
$page         = max(1, (int) get('page', '1'));

$where  = [];
$params = [];

if ($search !== '') {
    $where[] = '(t.transaction_id LIKE ? OR t.description LIKE ? OR t.related_account_number LIKE ?
                 OR a.account_number LIKE ? OR u.full_name LIKE ? OR u.username LIKE ?)';
    $like    = '%' . $search . '%';
    $params  = array_merge($params, array_fill(0, 6, $like));
}

$allowedTypes = ['Deposit', 'Withdrawal', 'Transfer Sent', 'Transfer Received'];
if (in_array($filterType, $allowedTypes, true)) {
    $where[]  = 't.transaction_type = ?';
    $params[] = $filterType;
} else {
    $filterType = '';
}

$allowedStatuses = ['Success', 'Pending', 'Failed'];
if (in_array($filterStatus, $allowedStatuses, true)) {
    $where[]  = 't.status = ?';
    $params[] = $filterStatus;
} else {
    $filterStatus = '';
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

// "Large transactions only" - useful for spotting unusual activity.
if ($minAmount !== '' && is_numeric($minAmount) && (float) $minAmount > 0) {
    $where[]  = 't.amount >= ?';
    $params[] = (float) $minAmount;
} else {
    $minAmount = '';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* Sorting comes from a whitelist, never from the URL directly. */
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
    "SELECT t.*, a.account_number, a.account_type, u.full_name, u.username, u.id AS user_id
     $joinSql $whereSql
     ORDER BY {$sortOptions[$sortBy]}
     LIMIT " . (int) RECORDS_PER_PAGE . " OFFSET " . (int) $offset
);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

/* Totals across the whole filtered set. */
$sumStmt = $pdo->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN t.transaction_type IN ('Deposit','Transfer Received') THEN t.amount ELSE 0 END), 0) AS total_in,
        COALESCE(SUM(CASE WHEN t.transaction_type IN ('Withdrawal','Transfer Sent')  THEN t.amount ELSE 0 END), 0) AS total_out,
        COALESCE(SUM(t.amount), 0) AS total_value,
        COALESCE(AVG(t.amount), 0) AS avg_value
     $joinSql $whereSql"
);
$sumStmt->execute($params);
$sums = $sumStmt->fetch();

$hasFilters = ($search !== '' || $filterType !== '' || $filterStatus !== ''
               || $dateFrom !== '' || $dateTo !== '' || $minAmount !== '');

require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- ==================================================================
     READ-ONLY NOTICE
=================================================================== -->
<div class="info-note info-note-lock mb-3 mb-xl-4">
    <i class="bi bi-shield-lock-fill"></i>
    <div>
        <strong>Transactions are permanent records</strong>
        <p class="mb-0">
            This screen is read-only. Transactions cannot be edited or deleted by
            anyone, including administrators, because the balance of every account
            is derived from this history. A mistake is corrected by posting a new
            reversing transaction, never by changing an old one.
        </p>
    </div>
</div>

<!-- ==================================================================
     SUMMARY TILES
=================================================================== -->
<div class="row g-3 mb-3 mb-xl-4">
    <div class="col-lg-3 col-md-6">
        <div class="mini-stat">
            <span class="ms-icon bg-primary-soft"><i class="bi bi-list-check"></i></span>
            <div>
                <span class="ms-label">Matching records</span>
                <span class="ms-value"><?= number_format($totalRows) ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="mini-stat">
            <span class="ms-icon bg-success-soft"><i class="bi bi-arrow-down-left"></i></span>
            <div>
                <span class="ms-label">Credits</span>
                <span class="ms-value text-success"><?= money($sums['total_in']) ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="mini-stat">
            <span class="ms-icon bg-danger-soft"><i class="bi bi-arrow-up-right"></i></span>
            <div>
                <span class="ms-label">Debits</span>
                <span class="ms-value text-danger"><?= money($sums['total_out']) ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="mini-stat">
            <span class="ms-icon bg-info-soft"><i class="bi bi-graph-up"></i></span>
            <div>
                <span class="ms-label">Average value</span>
                <span class="ms-value"><?= money($sums['avg_value']) ?></span>
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
            <a href="<?= url('admin/transactions.php') ?>" class="panel-link">
                <i class="bi bi-x-circle"></i> Clear all
            </a>
        <?php endif; ?>
    </div>

    <div class="panel-card-body">
        <form method="get" action="<?= url('admin/transactions.php') ?>" class="row g-3 filter-form">

            <div class="col-lg-4 col-md-6">
                <label for="search" class="form-label">Search</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="search" name="search"
                           value="<?= e($search) ?>" placeholder="Reference, account, customer or description">
                </div>
            </div>

            <div class="col-lg-2 col-md-6">
                <label for="type" class="form-label">Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">All types</option>
                    <?php foreach ($allowedTypes as $type): ?>
                        <option value="<?= e($type) ?>" <?= $filterType === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-2 col-md-6">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    <?php foreach ($allowedStatuses as $status): ?>
                        <option value="<?= e($status) ?>" <?= $filterStatus === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-2 col-md-6">
                <label for="from" class="form-label">From date</label>
                <input type="date" class="form-control" id="from" name="from" value="<?= e($dateFrom) ?>">
            </div>

            <div class="col-lg-2 col-md-6">
                <label for="to" class="form-label">To date</label>
                <input type="date" class="form-control" id="to" name="to" value="<?= e($dateTo) ?>">
            </div>

            <div class="col-lg-2 col-md-6">
                <label for="min" class="form-label">Minimum amount</label>
                <div class="input-group">
                    <span class="input-group-text"><?= CURRENCY_SYMBOL ?></span>
                    <input type="number" class="form-control" id="min" name="min"
                           value="<?= e($minAmount) ?>" min="0" step="0.01" placeholder="0.00">
                </div>
            </div>

            <div class="col-lg-2 col-md-6">
                <label for="sort" class="form-label">Sort by</label>
                <select class="form-select" id="sort" name="sort">
                    <option value="date_desc"   <?= $sortBy === 'date_desc'   ? 'selected' : '' ?>>Newest first</option>
                    <option value="date_asc"    <?= $sortBy === 'date_asc'    ? 'selected' : '' ?>>Oldest first</option>
                    <option value="amount_desc" <?= $sortBy === 'amount_desc' ? 'selected' : '' ?>>Highest amount</option>
                    <option value="amount_asc"  <?= $sortBy === 'amount_asc'  ? 'selected' : '' ?>>Lowest amount</option>
                </select>
            </div>

            <div class="col-lg-8 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary-glow">
                    <i class="bi bi-funnel me-1"></i> Apply filters
                </button>
                <?php if ($hasFilters): ?>
                    <a href="<?= url('admin/transactions.php') ?>" class="btn btn-light-soft">Reset</a>
                <?php endif; ?>
                <a href="<?= url('admin/reports.php') ?>" class="btn btn-light-soft">
                    <i class="bi bi-download me-1"></i> Export from Reports
                </a>
            </div>
        </form>
    </div>
</div>

<!-- ==================================================================
     LEDGER
=================================================================== -->
<div class="panel-card">
    <div class="panel-card-head">
        <h3><i class="bi bi-journal-text me-2"></i>Transaction ledger</h3>
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
        <?php if (!$transactions): ?>
            <div class="empty-inline">
                <i class="bi bi-journal-text"></i>
                <p>
                    <?= $hasFilters
                        ? 'No transactions match your filters. Try widening the date range.'
                        : 'No transactions have been recorded yet.' ?>
                </p>
                <?php if ($hasFilters): ?>
                    <a href="<?= url('admin/transactions.php') ?>" class="btn btn-light-soft btn-sm">Clear filters</a>
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
                            <th>Type</th>
                            <th>Description</th>
                            <th>Counterparty</th>
                            <th>Date &amp; Time</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $txn): ?>
                            <!-- id lets ?highlight=<id> scroll straight to this row -->
                            <tr id="row-<?= (int) $txn['id'] ?>">
                                <td><span class="txn-ref"><?= e($txn['transaction_id']) ?></span></td>
                                <td>
                                    <div class="cell-stack">
                                        <a href="<?= url('admin/customers.php?action=view&id=' . (int) $txn['user_id']) ?>"
                                           class="fw-semibold text-decoration-none"><?= e($txn['full_name']) ?></a>
                                        <small>@<?= e($txn['username']) ?></small>
                                    </div>
                                </td>
                                <td><span class="mono-text"><?= e($txn['account_number']) ?></span></td>
                                <td><?= transaction_type_badge($txn['transaction_type']) ?></td>
                                <td class="text-muted small"><?= e($txn['description'] ?: '-') ?></td>
                                <td>
                                    <?php if (!empty($txn['related_account_number'])): ?>
                                        <span class="mono-text"><?= e($txn['related_account_number']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?= e(pretty_date($txn['created_at'])) ?></td>
                                <td class="text-end"><?= signed_amount($txn['transaction_type'], $txn['amount']) ?></td>
                                <td class="text-center"><?= transaction_status_badge($txn['status']) ?></td>
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
            <nav aria-label="Transaction pages">
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
