<?php
/**
 * =====================================================================
 * customer/transactions.php  -  Statement / transaction history
 * ---------------------------------------------------------------------
 * Search by reference or description, filter by account, type, status
 * and date range, then page through the results.
 *
 * Every filter value is bound as a parameter. The only part of the SQL
 * built from user input is the ORDER BY column, and that is picked from
 * a fixed whitelist - never taken from the URL directly.
 * =====================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user      = require_customer();
$pageTitle = 'Transaction History';

/* =====================================================================
 | READ THE FILTERS FROM THE URL
 * ================================================================== */
$search      = get('search');
$filterAcct  = (int) get('account');
$filterType  = get('type');
$filterStat  = get('status');
$dateFrom    = get('from');
$dateTo      = get('to');
$sortBy      = get('sort', 'date_desc');
$page        = max(1, (int) get('page', '1'));

$accounts = get_user_accounts($pdo, (int) $user['id']);
$validIds = array_map('intval', array_column($accounts, 'id'));

/* =====================================================================
 | BUILD THE QUERY
 * ================================================================== */
$transactions = 0;
$rows         = [];
$totalPages   = 1;
$totalRows    = 0;
$pageTotals   = ['in' => 0.0, 'out' => 0.0];

if ($validIds) {

    /* ----- WHERE clause, assembled from the active filters -------- */
    $where  = [];
    $params = [];

    // Always restrict to accounts this customer owns.
    if ($filterAcct > 0 && in_array($filterAcct, $validIds, true)) {
        $where[]  = 't.account_id = ?';
        $params[] = $filterAcct;
    } else {
        $filterAcct   = 0;   // ignore an invalid account filter
        $placeholders = implode(',', array_fill(0, count($validIds), '?'));
        $where[]      = "t.account_id IN ($placeholders)";
        $params       = array_merge($params, $validIds);
    }

    // Free-text search across reference, description and other party.
    if ($search !== '') {
        $where[]  = '(t.transaction_id LIKE ? OR t.description LIKE ? OR t.related_account_number LIKE ?)';
        $like     = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    // Transaction type, validated against the allowed set.
    $allowedTypes = ['Deposit', 'Withdrawal', 'Transfer Sent', 'Transfer Received'];
    if (in_array($filterType, $allowedTypes, true)) {
        $where[]  = 't.transaction_type = ?';
        $params[] = $filterType;
    } else {
        $filterType = '';
    }

    // Status
    $allowedStatuses = ['Success', 'Pending', 'Failed'];
    if (in_array($filterStat, $allowedStatuses, true)) {
        $where[]  = 't.status = ?';
        $params[] = $filterStat;
    } else {
        $filterStat = '';
    }

    // Date range. DATE() strips the time so "to" includes the whole day.
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

    /* ----- Sorting: whitelist only -------------------------------- */
    $sortOptions = [
        'date_desc'   => 't.created_at DESC, t.id DESC',
        'date_asc'    => 't.created_at ASC, t.id ASC',
        'amount_desc' => 't.amount DESC, t.id DESC',
        'amount_asc'  => 't.amount ASC, t.id ASC',
    ];
    if (!isset($sortOptions[$sortBy])) {
        $sortBy = 'date_desc';
    }
    $orderSql = $sortOptions[$sortBy];

    /* ----- How many rows match? ----------------------------------- */
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM transactions t $whereSql");
    $countStmt->execute($params);
    $totalRows = (int) $countStmt->fetchColumn();

    $totalPages = max(1, (int) ceil($totalRows / RECORDS_PER_PAGE));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * RECORDS_PER_PAGE;

    /* ----- Fetch the current page --------------------------------- */
    $sql = "SELECT t.*, a.account_number, a.account_type
            FROM transactions t
            INNER JOIN accounts a ON a.id = t.account_id
            $whereSql
            ORDER BY $orderSql
            LIMIT " . (int) RECORDS_PER_PAGE . " OFFSET " . (int) $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    /* ----- Totals for the whole filtered set ---------------------- */
    $sumStmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN t.transaction_type IN ('Deposit','Transfer Received')
                              THEN t.amount ELSE 0 END), 0) AS total_in,
            COALESCE(SUM(CASE WHEN t.transaction_type IN ('Withdrawal','Transfer Sent')
                              THEN t.amount ELSE 0 END), 0) AS total_out
         FROM transactions t $whereSql"
    );
    $sumStmt->execute($params);
    $sums = $sumStmt->fetch();

    $pageTotals['in']  = (float) $sums['total_in'];
    $pageTotals['out'] = (float) $sums['total_out'];
}

$hasFilters = ($search !== '' || $filterAcct > 0 || $filterType !== ''
               || $filterStat !== '' || $dateFrom !== '' || $dateTo !== '');

require_once __DIR__ . '/../includes/customer_header.php';
?>

<?php if (!$accounts): ?>

    <div class="empty-panel">
        <span class="empty-icon"><i class="bi bi-clock-history"></i></span>
        <h3>No accounts yet</h3>
        <p>Open an account to start building your transaction history.</p>
        <a href="<?= url('customer/accounts.php') ?>" class="btn btn-primary-glow btn-lg mt-2">
            <i class="bi bi-plus-circle me-1"></i> Open an account
        </a>
    </div>

<?php else: ?>

<!-- ==================================================================
     SUMMARY TILES
=================================================================== -->
<div class="row g-3 mb-3 mb-xl-4">
    <div class="col-md-4">
        <div class="mini-stat">
            <span class="ms-icon bg-primary-soft"><i class="bi bi-list-check"></i></span>
            <div>
                <span class="ms-label">Matching transactions</span>
                <span class="ms-value"><?= number_format($totalRows) ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="mini-stat">
            <span class="ms-icon bg-success-soft"><i class="bi bi-arrow-down-left"></i></span>
            <div>
                <span class="ms-label">Money in</span>
                <span class="ms-value text-success"><?= money($pageTotals['in']) ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="mini-stat">
            <span class="ms-icon bg-danger-soft"><i class="bi bi-arrow-up-right"></i></span>
            <div>
                <span class="ms-label">Money out</span>
                <span class="ms-value text-danger"><?= money($pageTotals['out']) ?></span>
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
            <a href="<?= url('customer/transactions.php') ?>" class="panel-link">
                <i class="bi bi-x-circle"></i> Clear all
            </a>
        <?php endif; ?>
    </div>

    <div class="panel-card-body">
        <form method="get" action="<?= url('customer/transactions.php') ?>" class="row g-3 filter-form">

            <div class="col-lg-4 col-md-6">
                <label for="search" class="form-label">Search</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="search" name="search"
                           value="<?= e($search) ?>" placeholder="Reference, description or account">
                </div>
            </div>

            <div class="col-lg-2 col-md-6">
                <label for="account" class="form-label">Account</label>
                <select class="form-select" id="account" name="account">
                    <option value="">All accounts</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?= (int) $account['id'] ?>" <?= $filterAcct === (int) $account['id'] ? 'selected' : '' ?>>
                            <?= e(mask_account($account['account_number'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-2 col-md-6">
                <label for="type" class="form-label">Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">All types</option>
                    <?php foreach (['Deposit', 'Withdrawal', 'Transfer Sent', 'Transfer Received'] as $type): ?>
                        <option value="<?= e($type) ?>" <?= $filterType === $type ? 'selected' : '' ?>>
                            <?= e($type) ?>
                        </option>
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
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    <?php foreach (['Success', 'Pending', 'Failed'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= $filterStat === $status ? 'selected' : '' ?>>
                            <?= e($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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
                    <a href="<?= url('customer/transactions.php') ?>" class="btn btn-light-soft">Reset</a>
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
        <h3><i class="bi bi-receipt me-2"></i>Statement</h3>
        <span class="text-muted small">
            <?php if ($totalRows > 0): ?>
                Showing <?= number_format(($page - 1) * RECORDS_PER_PAGE + 1) ?>&ndash;<?= number_format(min($page * RECORDS_PER_PAGE, $totalRows)) ?>
                of <?= number_format($totalRows) ?>
            <?php else: ?>
                No results
            <?php endif; ?>
        </span>
    </div>

    <div class="panel-card-body p-0">
        <?php if (!$rows): ?>
            <div class="empty-inline">
                <i class="bi bi-search"></i>
                <p>
                    <?= $hasFilters
                        ? 'No transactions match your filters. Try widening the date range or clearing the search.'
                        : 'No transactions yet. Make a deposit to get started.' ?>
                </p>
                <?php if ($hasFilters): ?>
                    <a href="<?= url('customer/transactions.php') ?>" class="btn btn-light-soft btn-sm">Clear filters</a>
                <?php else: ?>
                    <a href="<?= url('customer/deposit.php') ?>" class="btn btn-primary-glow btn-sm">Deposit money</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table data-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Account</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Other party</th>
                            <th>Date &amp; Time</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $txn): ?>
                            <!-- id lets ?highlight=<id> scroll straight to this row -->
                            <tr id="row-<?= (int) $txn['id'] ?>">
                                <td><span class="txn-ref"><?= e($txn['transaction_id']) ?></span></td>
                                <td><span class="mono-text"><?= e(mask_account($txn['account_number'])) ?></span></td>
                                <td><?= transaction_type_badge($txn['transaction_type']) ?></td>
                                <td class="text-muted"><?= e($txn['description'] ?: '-') ?></td>
                                <td>
                                    <?php if (!empty($txn['related_account_number'])): ?>
                                        <span class="mono-text"><?= e(mask_account($txn['related_account_number'])) ?></span>
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
                        <a class="page-link" href="<?= e(query_with(['page' => $page - 1])) ?>" aria-label="Previous">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>

                    <?php
                    // Show a compact window of page numbers around the current page.
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
                        <a class="page-link" href="<?= e(query_with(['page' => $page + 1])) ?>" aria-label="Next">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/customer_footer.php'; ?>
