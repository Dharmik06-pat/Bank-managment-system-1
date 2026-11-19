<?php
/**
 * =====================================================================
 * customer/accounts.php  -  View and open bank accounts
 * ---------------------------------------------------------------------
 * Lists every account the customer holds and lets them open another
 * one. Account numbers are generated automatically and start at a
 * balance of zero.
 * =====================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user      = require_customer();
$pageTitle = 'My Accounts';

/** A customer may hold at most this many accounts. */
define('MAX_ACCOUNTS_PER_CUSTOMER', 5);

/* =====================================================================
 | OPEN A NEW ACCOUNT
 * ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'open_account') {

    require_csrf('customer/accounts.php');

    $accountType = post('account_type');

    if (!in_array($accountType, ['Savings Account', 'Current Account'], true)) {
        set_flash('danger', 'Please choose a valid account type.');
        redirect('customer/accounts.php');
    }

    // Enforce the per-customer cap.
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM accounts WHERE user_id = ?');
    $stmt->execute([$user['id']]);

    if ((int) $stmt->fetchColumn() >= MAX_ACCOUNTS_PER_CUSTOMER) {
        set_flash('warning', 'You already hold the maximum of ' . MAX_ACCOUNTS_PER_CUSTOMER . ' accounts. Please contact the bank if you need another.');
        redirect('customer/accounts.php');
    }

    try {
        $accountNumber = generate_account_number($pdo);

        $stmt = $pdo->prepare(
            'INSERT INTO accounts (user_id, account_number, account_type, balance, account_status)
             VALUES (?, ?, ?, 0.00, "Active")'
        );
        $stmt->execute([$user['id'], $accountNumber, $accountType]);

        set_flash('success', 'Your new ' . $accountType . ' is ready. Account number: ' . $accountNumber);

    } catch (Throwable $ex) {
        // Throwable rather than PDOException, because the failure here need
        // not come from the driver: generate_account_number() throws a
        // RuntimeException if every one of its attempts collides. Either way
        // the customer gets a readable message and the detail goes to the log.
        error_log('[BMS] Account creation failed: ' . $ex->getMessage());
        set_flash('danger', 'We could not open the account right now. Please try again.');
    }

    redirect('customer/accounts.php');
}

/* =====================================================================
 | DATA
 * ================================================================== */
$accounts   = get_user_accounts($pdo, (int) $user['id']);
$canOpenNew = count($accounts) < MAX_ACCOUNTS_PER_CUSTOMER;

/* Per-account movement counts, shown on each card. */
$txnCounts = [];
if ($accounts) {
    $ids          = array_column($accounts, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare(
        "SELECT account_id, COUNT(*) AS c
         FROM transactions
         WHERE account_id IN ($placeholders)
         GROUP BY account_id"
    );
    $stmt->execute($ids);

    foreach ($stmt->fetchAll() as $row) {
        $txnCounts[(int) $row['account_id']] = (int) $row['c'];
    }
}

require_once __DIR__ . '/../includes/customer_header.php';
?>

<div class="row g-3 g-xl-4">

    <!-- ==============================================================
         ACCOUNT LIST
    =============================================================== -->
    <div class="col-lg-7">
        <div class="panel-card h-100">
            <div class="panel-card-head">
                <h3><i class="bi bi-wallet2 me-2"></i>Your accounts</h3>
                <span class="text-muted small"><?= count($accounts) ?> of <?= MAX_ACCOUNTS_PER_CUSTOMER ?> used</span>
            </div>

            <div class="panel-card-body">
                <?php if (!$accounts): ?>
                    <div class="empty-inline">
                        <i class="bi bi-wallet2"></i>
                        <p>You have no accounts yet. Open one using the form beside this panel.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($accounts as $index => $account): ?>
                        <div class="account-chip <?= $index === 0 ? 'account-chip-primary' : '' ?> mb-3">
                            <div class="ac-head">
                                <span class="ac-type">
                                    <i class="bi <?= $account['account_type'] === 'Savings Account' ? 'bi-piggy-bank' : 'bi-briefcase' ?>"></i>
                                    <?= e($account['account_type']) ?>
                                    <?php if ($index === 0): ?>
                                        <span class="badge-primary-soft ms-1">Primary</span>
                                    <?php endif; ?>
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
                                <div class="text-end">
                                    <span class="ac-since d-block">
                                        <?= (int) ($txnCounts[$account['id']] ?? 0) ?> transaction(s)
                                    </span>
                                    <span class="ac-since">Opened <?= pretty_date($account['created_at'], false) ?></span>
                                </div>
                            </div>

                            <?php if ($account['account_status'] === 'Active'): ?>
                                <div class="ac-actions">
                                    <a href="<?= url('customer/deposit.php?account=' . (int) $account['id']) ?>" class="btn btn-sm btn-light-soft">
                                        <i class="bi bi-box-arrow-in-down me-1"></i>Deposit
                                    </a>
                                    <a href="<?= url('customer/withdraw.php?account=' . (int) $account['id']) ?>" class="btn btn-sm btn-light-soft">
                                        <i class="bi bi-box-arrow-up me-1"></i>Withdraw
                                    </a>
                                    <a href="<?= url('customer/transactions.php?account=' . (int) $account['id']) ?>" class="btn btn-sm btn-light-soft">
                                        <i class="bi bi-clock-history me-1"></i>History
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning py-2 px-3 small mt-3 mb-0">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    This account is deactivated. Contact the bank on 1800 200 4000.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ==============================================================
         OPEN A NEW ACCOUNT
    =============================================================== -->
    <div class="col-lg-5">
        <div class="panel-card">
            <div class="panel-card-head">
                <h3><i class="bi bi-plus-circle me-2"></i>Open a new account</h3>
            </div>

            <div class="panel-card-body">
                <?php if (!$canOpenNew): ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        You have reached the maximum of <?= MAX_ACCOUNTS_PER_CUSTOMER ?> accounts.
                        Please call 1800 200 4000 if you need an additional account.
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-3">
                        Your new account number is generated instantly and starts with a
                        balance of <?= money(0) ?>.
                    </p>

                    <form method="post" action="<?= url('customer/accounts.php') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="open_account">

                        <div class="row g-3 mb-3">
                            <?php
                            $accountChoices = [
                                'Savings Account' => ['bi-piggy-bank', '4.5% interest. No minimum balance.'],
                                'Current Account' => ['bi-briefcase',  'Higher limits. Built for business.'],
                            ];
                            foreach ($accountChoices as $type => $meta):
                                [$icon, $blurb] = $meta;
                            ?>
                                <div class="col-12">
                                    <label class="choice-card">
                                        <input type="radio" name="account_type" value="<?= e($type) ?>"
                                               <?= $type === 'Savings Account' ? 'checked' : '' ?>>
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

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary-glow">
                                <i class="bi bi-plus-circle me-1"></i> Open account
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="info-note mt-3">
            <i class="bi bi-shield-check"></i>
            <div>
                <strong>Good to know</strong>
                <p class="mb-0">
                    Account numbers are 12 digits and unique across the whole bank.
                    You can hold several accounts and move money between them instantly
                    using the transfer page.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/customer_footer.php'; ?>
