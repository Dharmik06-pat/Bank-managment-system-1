<?php
/**
 * =====================================================================
 * admin/dashboard.php  -  Administrator overview
 * ---------------------------------------------------------------------
 * Six headline statistics required by the specification:
 *     1. Total customers      4. Total transactions
 *     2. Total accounts       5. Total deposits
 *     3. Total balance        6. Total withdrawals
 * plus the most recent customers and transactions.
 *
 * Every figure here is read-only. Nothing on this page modifies data.
 * =====================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

$admin     = require_admin();
$pageTitle = 'Dashboard';

/* =====================================================================
 | 1-6. HEADLINE STATISTICS
 |
 | One aggregate query per box. Written plainly rather than as one
 | clever combined query, so it is easy to read and easy to change.
 * ================================================================== */

$totalCustomers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$activeCustomers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'Active'")->fetchColumn();

$totalAccounts  = (int) $pdo->query('SELECT COUNT(*) FROM accounts')->fetchColumn();
$activeAccounts = (int) $pdo->query("SELECT COUNT(*) FROM accounts WHERE account_status = 'Active'")->fetchColumn();

// Money the bank is currently holding across every account.
$totalBalance = (float) $pdo->query('SELECT COALESCE(SUM(balance), 0) FROM accounts')->fetchColumn();

$totalTransactions = (int) $pdo->query('SELECT COUNT(*) FROM transactions')->fetchColumn();

$depositRow = $pdo->query(
    "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total
     FROM transactions
     WHERE transaction_type = 'Deposit' AND status = 'Success'"
)->fetch();

$withdrawalRow = $pdo->query(
    "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total
     FROM transactions
     WHERE transaction_type = 'Withdrawal' AND status = 'Success'"
)->fetch();

$transferRow = $pdo->query(
    "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total
     FROM transactions
     WHERE transaction_type = 'Transfer Sent' AND status = 'Success'"
)->fetch();

$totalDeposits      = (float) $depositRow['total'];
$depositCount       = (int)   $depositRow['cnt'];
$totalWithdrawals   = (float) $withdrawalRow['total'];
$withdrawalCount    = (int)   $withdrawalRow['cnt'];
$totalTransfers     = (float) $transferRow['total'];
$transferCount      = (int)   $transferRow['cnt'];

/* Today's activity, for the small strip under the stat cards. */
$todayRow = $pdo->query(
    "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total
     FROM transactions
     WHERE DATE(created_at) = CURDATE()"
)->fetch();

$newCustomersToday = (int) $pdo->query(
    'SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()'
)->fetchColumn();

/* Account mix, for the doughnut chart. */
$savingsCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM accounts WHERE account_type = 'Savings Account'"
)->fetchColumn();
$currentCount = $totalAccounts - $savingsCount;

/* =====================================================================
 | RECENT CUSTOMERS
 * ================================================================== */
$recentCustomers = $pdo->query(
    'SELECT u.*,
            (SELECT COUNT(*) FROM accounts a WHERE a.user_id = u.id)              AS account_count,
            (SELECT COALESCE(SUM(a.balance), 0) FROM accounts a WHERE a.user_id = u.id) AS total_balance
     FROM users u
     ORDER BY u.created_at DESC, u.id DESC
     LIMIT 6'
)->fetchAll();

/* =====================================================================
 | RECENT TRANSACTIONS
 * ================================================================== */
$recentTransactions = $pdo->query(
    'SELECT t.*, a.account_number, u.full_name
     FROM transactions t
     INNER JOIN accounts a ON a.id = t.account_id
     INNER JOIN users u    ON u.id = a.user_id
     ORDER BY t.created_at DESC, t.id DESC
     LIMIT 8'
)->fetchAll();

/* =====================================================================
 | SIX-MONTH TREND  (for the bar chart)
 * ================================================================== */
$chartLabels   = [];
$chartDeposits = [];
$chartWithdraw = [];

for ($i = 5; $i >= 0; $i--) {
    $monthStart = date('Y-m-01', strtotime("-$i months"));
    $monthEnd   = date('Y-m-t', strtotime("-$i months"));
    $chartLabels[] = date('M Y', strtotime($monthStart));

    $stmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN transaction_type = 'Deposit'    THEN amount ELSE 0 END), 0) AS dep,
            COALESCE(SUM(CASE WHEN transaction_type = 'Withdrawal' THEN amount ELSE 0 END), 0) AS wdr
         FROM transactions
         WHERE status = 'Success' AND DATE(created_at) BETWEEN ? AND ?"
    );
    $stmt->execute([$monthStart, $monthEnd]);
    $monthRow = $stmt->fetch();

    $chartDeposits[] = (float) $monthRow['dep'];
    $chartWithdraw[] = (float) $monthRow['wdr'];
}

require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- ==================================================================
     WELCOME STRIP
=================================================================== -->
<div class="admin-welcome reveal">
    <div>
        <h2>Welcome back, <?= e(explode(' ', $admin['full_name'])[0]) ?></h2>
        <p>
            Here is what has happened across <?= e(BANK_NAME) ?> today:
            <strong><?= number_format((int) $todayRow['cnt']) ?></strong> transaction(s)
            worth <strong><?= money($todayRow['total']) ?></strong>,
            and <strong><?= number_format($newCustomersToday) ?></strong> new customer(s).
        </p>
    </div>
    <div class="admin-welcome-actions">
        <a href="<?= url('admin/reports.php') ?>" class="btn btn-light-soft">
            <i class="bi bi-bar-chart-line me-1"></i> Reports
        </a>
        <a href="<?= url('admin/customers.php') ?>" class="btn btn-primary-glow">
            <i class="bi bi-people me-1"></i> Customers
        </a>
    </div>
</div>

<!-- ==================================================================
     THE SIX STATISTICS
=================================================================== -->
<div class="row g-3 g-xl-4 mb-3 mb-xl-4">

    <!-- 1. Total customers -->
    <div class="col-xl-4 col-md-6">
        <a href="<?= url('admin/customers.php') ?>" class="stat-card stat-card-link reveal">
            <span class="stat-icon bg-primary-soft"><i class="bi bi-people-fill"></i></span>
            <div class="stat-meta">
                <span class="stat-label">Total Customers</span>
                <span class="stat-value" data-counter="<?= $totalCustomers ?>">0</span>
                <span class="stat-sub">
                    <i class="bi bi-check-circle text-success"></i>
                    <?= number_format($activeCustomers) ?> active
                    <?php if ($totalCustomers > $activeCustomers): ?>
                        &middot; <?= number_format($totalCustomers - $activeCustomers) ?> inactive
                    <?php endif; ?>
                </span>
            </div>
        </a>
    </div>

    <!-- 2. Total accounts -->
    <div class="col-xl-4 col-md-6">
        <a href="<?= url('admin/accounts.php') ?>" class="stat-card stat-card-link reveal">
            <span class="stat-icon bg-info-soft"><i class="bi bi-wallet-fill"></i></span>
            <div class="stat-meta">
                <span class="stat-label">Total Accounts</span>
                <span class="stat-value" data-counter="<?= $totalAccounts ?>">0</span>
                <span class="stat-sub">
                    <i class="bi bi-piggy-bank"></i>
                    <?= number_format($savingsCount) ?> savings &middot;
                    <?= number_format($currentCount) ?> current
                </span>
            </div>
        </a>
    </div>

    <!-- 3. Total balance held -->
    <div class="col-xl-4 col-md-6">
        <div class="stat-card stat-card-accent reveal">
            <span class="stat-icon bg-white-soft"><i class="bi bi-cash-stack"></i></span>
            <div class="stat-meta">
                <span class="stat-label">Total Balance Held</span>
                <span class="stat-value"><?= money($totalBalance) ?></span>
                <span class="stat-sub">
                    <i class="bi bi-bank"></i> Across <?= number_format($activeAccounts) ?> active account(s)
                </span>
            </div>
        </div>
    </div>

    <!-- 4. Total transactions -->
    <div class="col-xl-4 col-md-6">
        <a href="<?= url('admin/transactions.php') ?>" class="stat-card stat-card-link reveal">
            <span class="stat-icon bg-secondary-soft"><i class="bi bi-arrow-left-right"></i></span>
            <div class="stat-meta">
                <span class="stat-label">Total Transactions</span>
                <span class="stat-value" data-counter="<?= $totalTransactions ?>">0</span>
                <span class="stat-sub">
                    <i class="bi bi-send"></i>
                    <?= number_format($transferCount) ?> transfer(s) &middot; <?= money($totalTransfers) ?>
                </span>
            </div>
        </a>
    </div>

    <!-- 5. Total deposits -->
    <div class="col-xl-4 col-md-6">
        <a href="<?= url('admin/deposits.php') ?>" class="stat-card stat-card-link reveal">
            <span class="stat-icon bg-success-soft"><i class="bi bi-box-arrow-in-down"></i></span>
            <div class="stat-meta">
                <span class="stat-label">Total Deposits</span>
                <span class="stat-value text-success"><?= money($totalDeposits) ?></span>
                <span class="stat-sub">
                    <i class="bi bi-receipt"></i> <?= number_format($depositCount) ?> deposit(s) recorded
                </span>
            </div>
        </a>
    </div>

    <!-- 6. Total withdrawals -->
    <div class="col-xl-4 col-md-6">
        <a href="<?= url('admin/withdrawals.php') ?>" class="stat-card stat-card-link reveal">
            <span class="stat-icon bg-danger-soft"><i class="bi bi-box-arrow-up"></i></span>
            <div class="stat-meta">
                <span class="stat-label">Total Withdrawals</span>
                <span class="stat-value text-danger"><?= money($totalWithdrawals) ?></span>
                <span class="stat-sub">
                    <i class="bi bi-receipt"></i> <?= number_format($withdrawalCount) ?> withdrawal(s) recorded
                </span>
            </div>
        </a>
    </div>
</div>

<!-- ==================================================================
     CHARTS
=================================================================== -->
<div class="row g-3 g-xl-4 mb-3 mb-xl-4">

    <div class="col-xl-8">
        <div class="panel-card h-100">
            <div class="panel-card-head">
                <h3><i class="bi bi-bar-chart-line me-2"></i>Deposits vs withdrawals</h3>
                <span class="text-muted small">Last 6 months</span>
            </div>
            <div class="panel-card-body">
                <div class="chart-holder chart-holder-lg">
                    <canvas id="adminFlowChart"
                            data-chart="bars"
                            data-labels='<?= e(json_encode($chartLabels)) ?>'
                            data-credits='<?= e(json_encode($chartDeposits)) ?>'
                            data-debits='<?= e(json_encode($chartWithdraw)) ?>'></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="panel-card h-100">
            <div class="panel-card-head">
                <h3><i class="bi bi-pie-chart me-2"></i>Account mix</h3>
            </div>
            <div class="panel-card-body">
                <?php if ($totalAccounts === 0): ?>
                    <div class="empty-inline">
                        <i class="bi bi-pie-chart"></i>
                        <p>No accounts have been opened yet.</p>
                    </div>
                <?php else: ?>
                    <div class="chart-holder">
                        <canvas id="accountMixChart"
                                data-chart="doughnut"
                                data-labels='<?= e(json_encode(['Savings Account', 'Current Account'])) ?>'
                                data-values='<?= e(json_encode([$savingsCount, $currentCount])) ?>'></canvas>
                    </div>
                    <ul class="legend-list mt-3">
                        <li>
                            <span class="legend-dot" style="background:#1d4ed8"></span>
                            Savings <strong><?= number_format($savingsCount) ?></strong>
                        </li>
                        <li>
                            <span class="legend-dot" style="background:#0ea5e9"></span>
                            Current <strong><?= number_format($currentCount) ?></strong>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================================================================
     RECENT CUSTOMERS  +  RECENT TRANSACTIONS
=================================================================== -->
<div class="row g-3 g-xl-4">

    <!-- ---------- Recent customers ---------- -->
    <div class="col-xl-5">
        <div class="panel-card h-100">
            <div class="panel-card-head">
                <h3><i class="bi bi-person-plus me-2"></i>Recent customers</h3>
                <a href="<?= url('admin/customers.php') ?>" class="panel-link">
                    View all <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <div class="panel-card-body p-0">
                <?php if (!$recentCustomers): ?>
                    <div class="empty-inline">
                        <i class="bi bi-people"></i>
                        <p>No customers have registered yet.</p>
                    </div>
                <?php else: ?>
                    <ul class="mini-list">
                        <?php foreach ($recentCustomers as $customer): ?>
                            <li>
                                <span class="avatar-circle avatar-sm"><?= e(initials($customer['full_name'])) ?></span>
                                <div class="mini-meta">
                                    <strong><?= e($customer['full_name']) ?></strong>
                                    <small>
                                        @<?= e($customer['username']) ?> &middot;
                                        <?= (int) $customer['account_count'] ?> account(s) &middot;
                                        <?= e(pretty_date($customer['created_at'], false)) ?>
                                    </small>
                                </div>
                                <div class="mini-side text-end">
                                    <span class="mini-amt"><?= money($customer['total_balance']) ?></span>
                                    <?= status_badge($customer['status']) ?>
                                </div>
                                <a class="stretched-link"
                                   href="<?= url('admin/customers.php?action=view&id=' . (int) $customer['id']) ?>"
                                   aria-label="View <?= e($customer['full_name']) ?>"></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ---------- Recent transactions ---------- -->
    <div class="col-xl-7">
        <div class="panel-card h-100">
            <div class="panel-card-head">
                <h3><i class="bi bi-clock-history me-2"></i>Recent transactions</h3>
                <a href="<?= url('admin/transactions.php') ?>" class="panel-link">
                    View all <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <div class="panel-card-body p-0">
                <?php if (!$recentTransactions): ?>
                    <div class="empty-inline">
                        <i class="bi bi-receipt"></i>
                        <p>No transactions have been recorded yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table data-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>Customer</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentTransactions as $txn): ?>
                                    <tr>
                                        <td><span class="txn-ref"><?= e($txn['transaction_id']) ?></span></td>
                                        <td>
                                            <div class="cell-stack">
                                                <strong><?= e($txn['full_name']) ?></strong>
                                                <small class="mono-text"><?= e($txn['account_number']) ?></small>
                                            </div>
                                        </td>
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

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
