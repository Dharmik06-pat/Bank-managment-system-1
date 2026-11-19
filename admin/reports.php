<?php
/**
 * =====================================================================
 * admin/reports.php  -  Reports and analytics
 * ---------------------------------------------------------------------
 * Choose a date range, see the figures for that period, and download
 * the underlying rows as CSV.
 *
 * The CSV export runs BEFORE any HTML is sent, because headers cannot
 * be changed once output has started.
 *
 * Note the guard on every CSV cell: a value beginning with = + - or @
 * is prefixed with an apostrophe. Otherwise Excel treats it as a
 * formula, which is a real attack path - a customer could register with
 * a name like "=HYPERLINK(...)" and have it execute when an admin opens
 * the export. This is CSV injection, and it is easy to miss.
 * =====================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

$admin     = require_admin();
$pageTitle = 'Reports';

/* =====================================================================
 | DATE RANGE
 * ================================================================== */
$dateFrom = get('from');
$dateTo   = get('to');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = date('Y-m-01', strtotime('-5 months'));   // start of a 6-month window
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = date('Y-m-d');
}

// If the range is backwards, swap it rather than returning nothing.
if ($dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

$rangeParams = [$dateFrom, $dateTo];

/* =====================================================================
 | CSV EXPORT  (must run before any HTML)
 * ================================================================== */
if (get('export') === 'csv') {

    $exportStmt = $pdo->prepare(
        "SELECT t.transaction_id, t.created_at, u.full_name, u.username,
                a.account_number, a.account_type, t.transaction_type,
                t.related_account_number, t.description, t.amount, t.status
         FROM transactions t
         INNER JOIN accounts a ON a.id = t.account_id
         INNER JOIN users u    ON u.id = a.user_id
         WHERE DATE(t.created_at) BETWEEN ? AND ?
         ORDER BY t.created_at ASC, t.id ASC"
    );
    $exportStmt->execute($rangeParams);

    $filename = 'transactions_' . $dateFrom . '_to_' . $dateTo . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');

    // UTF-8 byte order mark so Excel shows the rupee symbol correctly.
    fwrite($out, "\xEF\xBB\xBF");

    /**
     * Stop a spreadsheet from treating a cell as a formula.
     * See the note in this file's header comment.
     */
    $safeCell = static function ($value): string {
        $value = (string) $value;
        if ($value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) {
            return "'" . $value;
        }
        return $value;
    };

    fputcsv($out, [
        'Transaction ID', 'Date & Time', 'Customer Name', 'Username',
        'Account Number', 'Account Type', 'Type', 'Counterparty Account',
        'Description', 'Amount', 'Status',
    ]);

    while ($row = $exportStmt->fetch()) {
        fputcsv($out, [
            $safeCell($row['transaction_id']),
            $safeCell($row['created_at']),
            $safeCell($row['full_name']),
            $safeCell($row['username']),
            $safeCell($row['account_number']),
            $safeCell($row['account_type']),
            $safeCell($row['transaction_type']),
            $safeCell($row['related_account_number'] ?? ''),
            $safeCell($row['description'] ?? ''),
            number_format((float) $row['amount'], 2, '.', ''),
            $safeCell($row['status']),
        ]);
    }

    fclose($out);
    exit;
}

/* =====================================================================
 | FIGURES FOR THE SELECTED RANGE
 * ================================================================== */
$rangeStmt = $pdo->prepare(
    "SELECT
        COUNT(*)                                                                          AS total_count,
        COALESCE(SUM(amount), 0)                                                          AS total_value,
        COALESCE(SUM(CASE WHEN transaction_type = 'Deposit'    THEN amount ELSE 0 END), 0) AS deposits,
        COALESCE(SUM(CASE WHEN transaction_type = 'Withdrawal' THEN amount ELSE 0 END), 0) AS withdrawals,
        COALESCE(SUM(CASE WHEN transaction_type = 'Transfer Sent' THEN amount ELSE 0 END), 0) AS transfers,
        SUM(CASE WHEN transaction_type = 'Deposit'       THEN 1 ELSE 0 END)               AS deposit_count,
        SUM(CASE WHEN transaction_type = 'Withdrawal'    THEN 1 ELSE 0 END)               AS withdrawal_count,
        SUM(CASE WHEN transaction_type = 'Transfer Sent' THEN 1 ELSE 0 END)               AS transfer_count
     FROM transactions
     WHERE status = 'Success' AND DATE(created_at) BETWEEN ? AND ?"
);
$rangeStmt->execute($rangeParams);
$range = $rangeStmt->fetch();

$newCustomersStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM users WHERE DATE(created_at) BETWEEN ? AND ?'
);
$newCustomersStmt->execute($rangeParams);
$newCustomers = (int) $newCustomersStmt->fetchColumn();

$newAccountsStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM accounts WHERE DATE(created_at) BETWEEN ? AND ?'
);
$newAccountsStmt->execute($rangeParams);
$newAccounts = (int) $newAccountsStmt->fetchColumn();

$netFlow = (float) $range['deposits'] - (float) $range['withdrawals'];

/* =====================================================================
 | MONTHLY BREAKDOWN  (for the chart and the table)
 * ================================================================== */
$monthlyStmt = $pdo->prepare(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym,
            COUNT(*) AS cnt,
            COALESCE(SUM(CASE WHEN transaction_type = 'Deposit'    THEN amount ELSE 0 END), 0) AS deposits,
            COALESCE(SUM(CASE WHEN transaction_type = 'Withdrawal' THEN amount ELSE 0 END), 0) AS withdrawals,
            COALESCE(SUM(CASE WHEN transaction_type = 'Transfer Sent' THEN amount ELSE 0 END), 0) AS transfers
     FROM transactions
     WHERE status = 'Success' AND DATE(created_at) BETWEEN ? AND ?
     GROUP BY ym
     ORDER BY ym ASC"
);
$monthlyStmt->execute($rangeParams);
$monthly = $monthlyStmt->fetchAll();

$monthLabels   = [];
$monthDeposits = [];
$monthWithdraw = [];

foreach ($monthly as $month) {
    $monthLabels[]   = date('M Y', strtotime($month['ym'] . '-01'));
    $monthDeposits[] = (float) $month['deposits'];
    $monthWithdraw[] = (float) $month['withdrawals'];
}

/* =====================================================================
 | TRANSACTION MIX  (for the doughnut chart)
 * ================================================================== */
$mixStmt = $pdo->prepare(
    "SELECT transaction_type, COUNT(*) AS cnt
     FROM transactions
     WHERE status = 'Success' AND DATE(created_at) BETWEEN ? AND ?
     GROUP BY transaction_type"
);
$mixStmt->execute($rangeParams);
$mixRows = $mixStmt->fetchAll();

$mixLabels = [];
$mixValues = [];
foreach ($mixRows as $mixRow) {
    $mixLabels[] = $mixRow['transaction_type'];
    $mixValues[] = (int) $mixRow['cnt'];
}

/* =====================================================================
 | TOP CUSTOMERS BY ACTIVITY IN THE RANGE
 * ================================================================== */
$topStmt = $pdo->prepare(
    "SELECT u.id, u.full_name, u.username,
            COUNT(t.id)              AS txn_count,
            COALESCE(SUM(t.amount), 0) AS txn_value,
            (SELECT COALESCE(SUM(a2.balance), 0) FROM accounts a2 WHERE a2.user_id = u.id) AS balance
     FROM transactions t
     INNER JOIN accounts a ON a.id = t.account_id
     INNER JOIN users u    ON u.id = a.user_id
     WHERE t.status = 'Success' AND DATE(t.created_at) BETWEEN ? AND ?
     GROUP BY u.id, u.full_name, u.username
     ORDER BY txn_value DESC
     LIMIT 10"
);
$topStmt->execute($rangeParams);
$topCustomers = $topStmt->fetchAll();

/* =====================================================================
 | ACCOUNTS WITH THE LARGEST BALANCES  (all time)
 * ================================================================== */
$richest = $pdo->query(
    "SELECT a.account_number, a.account_type, a.balance, u.full_name, u.id AS user_id
     FROM accounts a
     INNER JOIN users u ON u.id = a.user_id
     WHERE a.balance > 0
     ORDER BY a.balance DESC
     LIMIT 10"
)->fetchAll();

require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- ==================================================================
     RANGE PICKER
=================================================================== -->
<div class="panel-card mb-3 mb-xl-4">
    <div class="panel-card-head">
        <h3><i class="bi bi-calendar-range me-2"></i>Reporting period</h3>
        <span class="text-muted small">
            <?= e(pretty_date($dateFrom, false)) ?> &ndash; <?= e(pretty_date($dateTo, false)) ?>
        </span>
    </div>

    <div class="panel-card-body">
        <form method="get" action="<?= url('admin/reports.php') ?>" class="row g-3 filter-form">

            <div class="col-lg-3 col-md-6">
                <label for="from" class="form-label">From date</label>
                <input type="date" class="form-control" id="from" name="from" value="<?= e($dateFrom) ?>" required>
            </div>

            <div class="col-lg-3 col-md-6">
                <label for="to" class="form-label">To date</label>
                <input type="date" class="form-control" id="to" name="to" value="<?= e($dateTo) ?>" required>
            </div>

            <div class="col-lg-6 d-flex align-items-end gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary-glow">
                    <i class="bi bi-arrow-repeat me-1"></i> Update report
                </button>
                <a href="<?= url('admin/reports.php?from=' . date('Y-m-01') . '&to=' . date('Y-m-d')) ?>"
                   class="btn btn-light-soft">This month</a>
                <a href="<?= url('admin/reports.php?from=' . date('Y-01-01') . '&to=' . date('Y-m-d')) ?>"
                   class="btn btn-light-soft">This year</a>
                <a href="<?= url('admin/reports.php?from=' . e($dateFrom) . '&to=' . e($dateTo) . '&export=csv') ?>"
                   class="btn btn-outline-primary">
                    <i class="bi bi-download me-1"></i> Export CSV
                </a>
            </div>
        </form>
    </div>
</div>

<!-- ==================================================================
     HEADLINE FIGURES FOR THE PERIOD
=================================================================== -->
<div class="row g-3 mb-3 mb-xl-4">
    <div class="col-xl-3 col-md-6">
        <div class="mini-stat">
            <span class="ms-icon bg-success-soft"><i class="bi bi-box-arrow-in-down"></i></span>
            <div>
                <span class="ms-label">Deposits</span>
                <span class="ms-value text-success"><?= money($range['deposits']) ?></span>
                <span class="ms-sub"><?= number_format((int) $range['deposit_count']) ?> transaction(s)</span>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="mini-stat">
            <span class="ms-icon bg-danger-soft"><i class="bi bi-box-arrow-up"></i></span>
            <div>
                <span class="ms-label">Withdrawals</span>
                <span class="ms-value text-danger"><?= money($range['withdrawals']) ?></span>
                <span class="ms-sub"><?= number_format((int) $range['withdrawal_count']) ?> transaction(s)</span>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="mini-stat">
            <span class="ms-icon bg-info-soft"><i class="bi bi-send"></i></span>
            <div>
                <span class="ms-label">Transfers</span>
                <span class="ms-value"><?= money($range['transfers']) ?></span>
                <span class="ms-sub"><?= number_format((int) $range['transfer_count']) ?> transfer(s)</span>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="mini-stat">
            <span class="ms-icon <?= $netFlow >= 0 ? 'bg-success-soft' : 'bg-danger-soft' ?>">
                <i class="bi <?= $netFlow >= 0 ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow' ?>"></i>
            </span>
            <div>
                <span class="ms-label">Net flow</span>
                <span class="ms-value <?= $netFlow >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= $netFlow >= 0 ? '+' : '-' ?> <?= money(abs($netFlow)) ?>
                </span>
                <span class="ms-sub">Deposits minus withdrawals</span>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3 mb-xl-4">
    <div class="col-xl-4 col-md-6">
        <div class="mini-stat">
            <span class="ms-icon bg-primary-soft"><i class="bi bi-person-plus"></i></span>
            <div>
                <span class="ms-label">New customers in period</span>
                <span class="ms-value"><?= number_format($newCustomers) ?></span>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="mini-stat">
            <span class="ms-icon bg-secondary-soft"><i class="bi bi-wallet2"></i></span>
            <div>
                <span class="ms-label">New accounts in period</span>
                <span class="ms-value"><?= number_format($newAccounts) ?></span>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="mini-stat">
            <span class="ms-icon bg-info-soft"><i class="bi bi-list-check"></i></span>
            <div>
                <span class="ms-label">Transactions in period</span>
                <span class="ms-value"><?= number_format((int) $range['total_count']) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- ==================================================================
     CHARTS
=================================================================== -->
<div class="row g-3 g-xl-4 mb-3 mb-xl-4">

    <div class="col-xl-8">
        <div class="panel-card h-100">
            <div class="panel-card-head">
                <h3><i class="bi bi-bar-chart-line me-2"></i>Monthly deposits vs withdrawals</h3>
            </div>
            <div class="panel-card-body">
                <?php if (!$monthly): ?>
                    <div class="empty-inline">
                        <i class="bi bi-bar-chart"></i>
                        <p>No successful transactions in this period.</p>
                    </div>
                <?php else: ?>
                    <div class="chart-holder chart-holder-lg">
                        <canvas id="reportFlowChart"
                                data-chart="bars"
                                data-labels='<?= e(json_encode($monthLabels)) ?>'
                                data-credits='<?= e(json_encode($monthDeposits)) ?>'
                                data-debits='<?= e(json_encode($monthWithdraw)) ?>'></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="panel-card h-100">
            <div class="panel-card-head">
                <h3><i class="bi bi-pie-chart me-2"></i>Transaction mix</h3>
            </div>
            <div class="panel-card-body">
                <?php if (!$mixRows): ?>
                    <div class="empty-inline">
                        <i class="bi bi-pie-chart"></i>
                        <p>Nothing to chart for this period.</p>
                    </div>
                <?php else: ?>
                    <div class="chart-holder">
                        <canvas id="reportMixChart"
                                data-chart="doughnut"
                                data-labels='<?= e(json_encode($mixLabels)) ?>'
                                data-values='<?= e(json_encode($mixValues)) ?>'></canvas>
                    </div>
                    <ul class="legend-list mt-3">
                        <?php
                        $mixColours = ['#1d4ed8', '#ef4444', '#0ea5e9', '#22c55e', '#f59e0b'];
                        foreach ($mixLabels as $index => $label): ?>
                            <li>
                                <span class="legend-dot" style="background:<?= e($mixColours[$index % count($mixColours)]) ?>"></span>
                                <?= e($label) ?> <strong><?= number_format($mixValues[$index]) ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================================================================
     MONTHLY TABLE
=================================================================== -->
<div class="panel-card mb-3 mb-xl-4">
    <div class="panel-card-head">
        <h3><i class="bi bi-table me-2"></i>Month-by-month summary</h3>
    </div>
    <div class="panel-card-body p-0">
        <?php if (!$monthly): ?>
            <div class="empty-inline">
                <i class="bi bi-table"></i>
                <p>No data for the selected period.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table data-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th class="text-center">Transactions</th>
                            <th class="text-end">Deposits</th>
                            <th class="text-end">Withdrawals</th>
                            <th class="text-end">Transfers</th>
                            <th class="text-end">Net flow</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthly as $month):
                            $monthNet = (float) $month['deposits'] - (float) $month['withdrawals']; ?>
                            <tr>
                                <td class="fw-semibold"><?= e(date('F Y', strtotime($month['ym'] . '-01'))) ?></td>
                                <td class="text-center"><?= number_format((int) $month['cnt']) ?></td>
                                <td class="text-end text-success"><?= money($month['deposits']) ?></td>
                                <td class="text-end text-danger"><?= money($month['withdrawals']) ?></td>
                                <td class="text-end"><?= money($month['transfers']) ?></td>
                                <td class="text-end fw-semibold <?= $monthNet >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= $monthNet >= 0 ? '+' : '-' ?> <?= money(abs($monthNet)) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ==================================================================
     LEADERBOARDS
=================================================================== -->
<div class="row g-3 g-xl-4">

    <div class="col-xl-6">
        <div class="panel-card h-100">
            <div class="panel-card-head">
                <h3><i class="bi bi-trophy me-2"></i>Most active customers</h3>
                <span class="text-muted small">In selected period</span>
            </div>
            <div class="panel-card-body p-0">
                <?php if (!$topCustomers): ?>
                    <div class="empty-inline">
                        <i class="bi bi-people"></i>
                        <p>No customer activity in this period.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table data-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Customer</th>
                                    <th class="text-center">Txns</th>
                                    <th class="text-end">Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topCustomers as $index => $customer): ?>
                                    <tr>
                                        <td class="text-muted"><?= $index + 1 ?></td>
                                        <td>
                                            <div class="cell-user">
                                                <span class="avatar-circle avatar-sm"><?= e(initials($customer['full_name'])) ?></span>
                                                <div class="cell-stack">
                                                    <a href="<?= url('admin/customers.php?action=view&id=' . (int) $customer['id']) ?>"
                                                       class="fw-semibold text-decoration-none"><?= e($customer['full_name']) ?></a>
                                                    <small>@<?= e($customer['username']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center"><?= number_format((int) $customer['txn_count']) ?></td>
                                        <td class="text-end fw-semibold"><?= money($customer['txn_value']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="panel-card h-100">
            <div class="panel-card-head">
                <h3><i class="bi bi-cash-stack me-2"></i>Largest balances</h3>
                <span class="text-muted small">All time</span>
            </div>
            <div class="panel-card-body p-0">
                <?php if (!$richest): ?>
                    <div class="empty-inline">
                        <i class="bi bi-wallet2"></i>
                        <p>No accounts hold a balance yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table data-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Account</th>
                                    <th>Type</th>
                                    <th class="text-end">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($richest as $index => $account): ?>
                                    <tr>
                                        <td class="text-muted"><?= $index + 1 ?></td>
                                        <td>
                                            <div class="cell-stack">
                                                <a href="<?= url('admin/customers.php?action=view&id=' . (int) $account['user_id']) ?>"
                                                   class="fw-semibold text-decoration-none"><?= e($account['full_name']) ?></a>
                                                <small class="mono-text"><?= e($account['account_number']) ?></small>
                                            </div>
                                        </td>
                                        <td class="small text-muted"><?= e(str_replace(' Account', '', $account['account_type'])) ?></td>
                                        <td class="text-end fw-semibold"><?= money($account['balance']) ?></td>
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

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
