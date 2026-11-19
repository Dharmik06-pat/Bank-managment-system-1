<?php
/**
 * =====================================================================
 * customer/dashboard.php  -  Customer home screen
 * ---------------------------------------------------------------------
 * Shows the account summary cards, an account switcher, the four
 * statistic tiles and the most recent transactions.
 * =====================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Blocks the page unless an Active customer is signed in.
$user = require_customer();

$pageTitle = 'Dashboard';

/* =====================================================================
 | DATA
 * ================================================================== */

$accounts      = get_user_accounts($pdo, (int) $user['id']);
$accountIds    = array_column($accounts, 'id');
$hasAccounts   = count($accounts) > 0;
$primaryAcct   = $hasAccounts ? $accounts[0] : null;

/* ----- Totals across every account the customer owns -------------- */
$totals = [
    'balance'      => 0.0,
    'deposits'     => 0.0,
    'withdrawals'  => 0.0,
    'transactions' => 0,
];
$recentTransactions = [];
$monthlyFlow        = ['labels' => [], 'credits' => [], 'debits' => []];

if ($hasAccounts) {

    // Sum of balances is read straight from the accounts table.
    foreach ($accounts as $account) {
        $totals['balance'] += (float) $account['balance'];
    }

    /* Build the "IN (?, ?, ?)" placeholder list safely. The number of
       placeholders comes from a count, never from user input. */
    $placeholders = implode(',', array_fill(0, count($accountIds), '?'));

    /* ----- Money in, money out, and how many movements ------------ */
    $sql = "SELECT
                COALESCE(SUM(CASE WHEN transaction_type IN ('Deposit','Transfer Received')
                                  THEN amount ELSE 0 END), 0) AS total_in,
                COALESCE(SUM(CASE WHEN transaction_type IN ('Withdrawal','Transfer Sent')
                                  THEN amount ELSE 0 END), 0) AS total_out,
                COUNT(*) AS total_count
            FROM transactions
            WHERE account_id IN ($placeholders) AND status = 'Success'";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($accountIds);
    $summary = $stmt->fetch();

    $totals['deposits']     = (float) $summary['total_in'];
    $totals['withdrawals']  = (float) $summary['total_out'];
    $totals['transactions'] = (int)   $summary['total_count'];

    /* ----- The eight most recent movements ------------------------ */
    $sql = "SELECT t.*, a.account_number, a.account_type
            FROM transactions t
            INNER JOIN accounts a ON a.id = t.account_id
            WHERE t.account_id IN ($placeholders)
            ORDER BY t.created_at DESC, t.id DESC
            LIMIT 8";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($accountIds);
    $recentTransactions = $stmt->fetchAll();

    /* ----- Last six months of activity, for the chart ------------- */
    $sql = "SELECT
                DATE_FORMAT(created_at, '%Y-%m') AS ym,
                COALESCE(SUM(CASE WHEN transaction_type IN ('Deposit','Transfer Received')
                                  THEN amount ELSE 0 END), 0) AS credits,
                COALESCE(SUM(CASE WHEN transaction_type IN ('Withdrawal','Transfer Sent')
                                  THEN amount ELSE 0 END), 0) AS debits
            FROM transactions
            WHERE account_id IN ($placeholders)
              AND status = 'Success'
              AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY ym
            ORDER BY ym ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($accountIds);
    $flowRows = $stmt->fetchAll();

    // Index the results so missing months can be filled with zero.
    $flowByMonth = [];
    foreach ($flowRows as $row) {
        $flowByMonth[$row['ym']] = $row;
    }

    for ($i = 5; $i >= 0; $i--) {
        $key = date('Y-m', strtotime("-$i months"));
        $monthlyFlow['labels'][]  = date('M', strtotime($key . '-01'));
        $monthlyFlow['credits'][] = round((float) ($flowByMonth[$key]['credits'] ?? 0), 2);
        $monthlyFlow['debits'][]  = round((float) ($flowByMonth[$key]['debits']  ?? 0), 2);
    }
}

require_once __DIR__ . '/../includes/customer_header.php';
?>

<!-- ==================================================================
     WELCOME STRIP
=================================================================== -->
<div class="welcome-strip">
    <div>
        <h2 class="welcome-title">
            <?= e(explode(' ', $user['full_name'])[0]) ?>, here is your money at a glance
        </h2>
        <p class="welcome-sub">
            Last signed in just now &middot; Your session ends automatically after 30 idle minutes.
        </p>
    </div>
    <div class="welcome-actions">
        <a href="<?= url('customer/deposit.php') ?>" class="btn btn-light-soft">
            <i class="bi bi-box-arrow-in-down me-1"></i> Deposit
        </a>
        <a href="<?= url('customer/transfer.php') ?>" class="btn btn-primary-glow">
            <i class="bi bi-send me-1"></i> Transfer money
        </a>
    </div>
</div>

<?php if (!$hasAccounts): ?>

    <!-- No account yet -->
    <div class="empty-panel">
        <span class="empty-icon"><i class="bi bi-wallet2"></i></span>
        <h3>You do not have a bank account yet</h3>
        <p>Open your first savings or current account to start banking. It takes one click.</p>
        <a href="<?= url('customer/accounts.php') ?>" class="btn btn-primary-glow btn-lg mt-2">
            <i class="bi bi-plus-circle me-1"></i> Open an account
        </a>
    </div>

<?php else: ?>

    <!-- ==============================================================
         STAT CARDS
    =============================================================== -->
    <div class="row g-3 g-xl-4 mb-4">

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card stat-primary">
                <div class="stat-top">
                    <span class="stat-icon"><i class="bi bi-wallet2"></i></span>
                    <span class="stat-chip">Live</span>
                </div>
                <span class="stat-label">Total Balance</span>
                <span class="stat-value"><?= money($totals['balance']) ?></span>
                <span class="stat-foot">
                    Across <?= (int) count($accounts) ?> account<?= count($accounts) > 1 ? 's' : '' ?>
                </span>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card stat-success">
                <div class="stat-top">
                    <span class="stat-icon"><i class="bi bi-arrow-down-left"></i></span>
                    <span class="stat-chip">Money in</span>
                </div>
                <span class="stat-label">Total Deposits</span>
                <span class="stat-value"><?= money($totals['deposits']) ?></span>
                <span class="stat-foot">Deposits &amp; transfers received</span>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card stat-danger">
                <div class="stat-top">
                    <span class="stat-icon"><i class="bi bi-arrow-up-right"></i></span>
                    <span class="stat-chip">Money out</span>
                </div>
                <span class="stat-label">Total Withdrawals</span>
                <span class="stat-value"><?= money($totals['withdrawals']) ?></span>
                <span class="stat-foot">Withdrawals &amp; transfers sent</span>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card stat-info">
                <div class="stat-top">
                    <span class="stat-icon"><i class="bi bi-list-check"></i></span>
                    <span class="stat-chip">All time</span>
                </div>
                <span class="stat-label">Total Transactions</span>
                <span class="stat-value"><?= number_format($totals['transactions']) ?></span>
                <span class="stat-foot">Successful movements recorded</span>
            </div>
        </div>
    </div>

    <div class="row g-3 g-xl-4">

        <!-- ==========================================================
             ACCOUNT CARDS
        =========================================================== -->
        <div class="col-xl-5">
            <div class="panel-card h-100">
                <div class="panel-card-head">
                    <h3><i class="bi bi-credit-card-2-front me-2"></i>My Accounts</h3>
                    <a href="<?= url('customer/accounts.php') ?>" class="panel-link">Manage <i class="bi bi-arrow-right"></i></a>
                </div>

                <div class="panel-card-body">
                    <?php foreach ($accounts as $index => $account): ?>
                        <div class="account-chip <?= $index === 0 ? 'account-chip-primary' : '' ?>">
                            <div class="ac-head">
                                <span class="ac-type">
                                    <i class="bi <?= $account['account_type'] === 'Savings Account' ? 'bi-piggy-bank' : 'bi-briefcase' ?>"></i>
                                    <?= e($account['account_type']) ?>
                                </span>
                                <?= status_badge($account['account_status']) ?>
                            </div>

                            <div class="ac-number">
                                <span class="ac-digits"><?= e(chunk_split($account['account_number'], 4, ' ')) ?></span>
                                <button type="button" class="ac-copy" data-copy="<?= e($account['account_number']) ?>"
                                        title="Copy account number">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>

                            <div class="ac-foot">
                                <div>
                                    <span class="ac-label">Available balance</span>
                                    <span class="ac-balance"><?= money($account['balance']) ?></span>
                                </div>
                                <span class="ac-since">Since <?= pretty_date($account['created_at'], false) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <a href="<?= url('customer/accounts.php') ?>" class="add-account-btn">
                        <i class="bi bi-plus-circle"></i> Open another account
                    </a>
                </div>
            </div>
        </div>

        <!-- ==========================================================
             ACTIVITY CHART + QUICK ACTIONS
        =========================================================== -->
        <div class="col-xl-7">
            <div class="panel-card mb-3 mb-xl-4">
                <div class="panel-card-head">
                    <h3><i class="bi bi-bar-chart-line me-2"></i>Last 6 months</h3>
                    <span class="chart-legend">
                        <span><i class="dot dot-success"></i> Money in</span>
                        <span><i class="dot dot-danger"></i> Money out</span>
                    </span>
                </div>
                <div class="panel-card-body">
                    <div class="chart-holder">
                        <canvas id="flowChart"
                                data-labels='<?= e(json_encode($monthlyFlow["labels"])) ?>'
                                data-credits='<?= e(json_encode($monthlyFlow["credits"])) ?>'
                                data-debits='<?= e(json_encode($monthlyFlow["debits"])) ?>'></canvas>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <a href="<?= url('customer/deposit.php') ?>" class="quick-action qa-success">
                        <i class="bi bi-box-arrow-in-down"></i><span>Deposit</span>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="<?= url('customer/withdraw.php') ?>" class="quick-action qa-danger">
                        <i class="bi bi-box-arrow-up"></i><span>Withdraw</span>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="<?= url('customer/transfer.php') ?>" class="quick-action qa-primary">
                        <i class="bi bi-arrow-left-right"></i><span>Transfer</span>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="<?= url('customer/transactions.php') ?>" class="quick-action qa-info">
                        <i class="bi bi-clock-history"></i><span>History</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ==============================================================
         RECENT TRANSACTIONS
    =============================================================== -->
    <div class="panel-card mt-3 mt-xl-4">
        <div class="panel-card-head">
            <h3><i class="bi bi-clock-history me-2"></i>Recent Transactions</h3>
            <a href="<?= url('customer/transactions.php') ?>" class="panel-link">View all <i class="bi bi-arrow-right"></i></a>
        </div>

        <div class="panel-card-body p-0">
            <?php if (!$recentTransactions): ?>
                <div class="empty-inline">
                    <i class="bi bi-inbox"></i>
                    <p>No transactions yet. Make your first deposit to get started.</p>
                    <a href="<?= url('customer/deposit.php') ?>" class="btn btn-primary-glow btn-sm">Deposit money</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table data-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>Type</th>
                                <th>Account</th>
                                <th>Description</th>
                                <th>Date &amp; Time</th>
                                <th class="text-end">Amount</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentTransactions as $txn): ?>
                                <tr>
                                    <td><span class="txn-ref"><?= e($txn['transaction_id']) ?></span></td>
                                    <td><?= transaction_type_badge($txn['transaction_type']) ?></td>
                                    <td><span class="mono-text"><?= e(mask_account($txn['account_number'])) ?></span></td>
                                    <td class="text-muted"><?= e($txn['description'] ?: '-') ?></td>
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
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/customer_footer.php'; ?>
