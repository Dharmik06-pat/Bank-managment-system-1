<?php
/**
 * =====================================================================
 * customer/withdraw.php  -  Take money out of an account
 * ---------------------------------------------------------------------
 * The balance check happens INSIDE the database transaction, after the
 * row has been locked with FOR UPDATE. Checking before the lock would
 * allow two withdrawals submitted at the same moment to both pass the
 * check and overdraw the account.
 * =====================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user      = require_customer();
$pageTitle = 'Withdraw Money';

/** Largest single withdrawal allowed. */
define('MAX_WITHDRAWAL', 500000.00);

/* =====================================================================
 | FORM HANDLING
 * ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_csrf('customer/withdraw.php');

    $accountId   = (int) post('account_id');
    $amountRaw   = post('amount');
    $description = post('description');

    $errors = [];

    /* ----- Validate the account ----------------------------------- */
    $account = get_owned_account($pdo, $accountId, (int) $user['id']);

    if (!$account) {
        $errors[] = 'Please choose one of your own accounts.';
    } elseif ($account['account_status'] !== 'Active') {
        $errors[] = 'That account is deactivated. Withdrawals are not permitted.';
    }

    /* ----- Validate the amount ------------------------------------ */
    $amount = parse_amount($amountRaw, MAX_WITHDRAWAL);

    if ($amount === null) {
        $errors[] = 'Enter an amount greater than ' . money(0) . ' and no more than ' . money(MAX_WITHDRAWAL) . '.';
    }

    /* ----- Validate the description ------------------------------- */
    if (mb_strlen($description) > 255) {
        $errors[] = 'Description must be 255 characters or fewer.';
    }
    if ($description === '') {
        $description = 'Cash withdrawal';
    }

    /* ----- Perform the withdrawal --------------------------------- */
    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Lock the row, THEN read the balance. Nothing else can
            // change this account until we commit or roll back.
            $stmt = $pdo->prepare('SELECT * FROM accounts WHERE id = ? AND user_id = ? FOR UPDATE');
            $stmt->execute([$accountId, $user['id']]);
            $locked = $stmt->fetch();

            if (!$locked || $locked['account_status'] !== 'Active') {
                throw new RuntimeException('Account unavailable.');
            }

            $currentBalance = (float) $locked['balance'];

            // THE critical rule: never allow a negative balance.
            if ($amount > $currentBalance) {
                $pdo->rollBack();
                set_flash('danger',
                    'Insufficient balance. You tried to withdraw ' . money($amount) .
                    ' but only ' . money($currentBalance) . ' is available.'
                );
                set_old($_POST);
                redirect('customer/withdraw.php?account=' . $accountId);
            }

            $newBalance = $currentBalance - $amount;

            // 1. Update the balance
            $stmt = $pdo->prepare('UPDATE accounts SET balance = ? WHERE id = ?');
            $stmt->execute([$newBalance, $accountId]);

            // 2. Record the movement
            $reference = generate_transaction_id($pdo);

            $stmt = $pdo->prepare(
                'INSERT INTO transactions
                    (transaction_id, account_id, related_account_number,
                     transaction_type, amount, description, status)
                 VALUES (?, ?, NULL, "Withdrawal", ?, ?, "Success")'
            );
            $stmt->execute([$reference, $accountId, $amount, $description]);

            $pdo->commit();

            set_flash('success',
                money($amount) . ' withdrawn successfully. Reference ' . $reference .
                '. Remaining balance: ' . money($newBalance) . '.'
            );
            redirect('customer/withdraw.php?account=' . $accountId);

        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[BMS] Withdrawal failed: ' . $ex->getMessage());
            $errors[] = 'The withdrawal could not be completed. No money has been moved. Please try again.';
        }
    }

    set_old($_POST);
    foreach ($errors as $error) {
        set_flash('danger', $error);
    }
    redirect('customer/withdraw.php');
}

/* =====================================================================
 | DATA
 * ================================================================== */
$accounts = get_user_accounts($pdo, (int) $user['id']);

$selectedId = (int) get('account');
$validIds   = array_map('intval', array_column($accounts, 'id'));
if (!in_array($selectedId, $validIds, true)) {
    $selectedId = $validIds[0] ?? 0;
}

// The balance of the pre-selected account, shown in the banner.
$selectedBalance = 0.0;
foreach ($accounts as $account) {
    if ((int) $account['id'] === $selectedId) {
        $selectedBalance = (float) $account['balance'];
        break;
    }
}

/* Five most recent withdrawals. */
$recentWithdrawals = [];
if ($accounts) {
    $placeholders = implode(',', array_fill(0, count($validIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT t.*, a.account_number
         FROM transactions t
         INNER JOIN accounts a ON a.id = t.account_id
         WHERE t.account_id IN ($placeholders) AND t.transaction_type = 'Withdrawal'
         ORDER BY t.created_at DESC, t.id DESC
         LIMIT 5"
    );
    $stmt->execute($validIds);
    $recentWithdrawals = $stmt->fetchAll();
}

require_once __DIR__ . '/../includes/customer_header.php';
?>

<?php if (!$accounts): ?>

    <div class="empty-panel">
        <span class="empty-icon"><i class="bi bi-wallet2"></i></span>
        <h3>You need an account first</h3>
        <p>Open a savings or current account before making a withdrawal.</p>
        <a href="<?= url('customer/accounts.php') ?>" class="btn btn-primary-glow btn-lg mt-2">
            <i class="bi bi-plus-circle me-1"></i> Open an account
        </a>
    </div>

<?php else: ?>

<div class="row g-3 g-xl-4">

    <!-- ==============================================================
         WITHDRAWAL FORM
    =============================================================== -->
    <div class="col-lg-7">
        <div class="panel-card">
            <div class="panel-card-head">
                <h3><i class="bi bi-box-arrow-up me-2"></i>Withdraw money</h3>
            </div>

            <div class="panel-card-body">

                <!-- Live available balance -->
                <div class="balance-banner" id="balanceBanner">
                    <span class="bb-label">Available balance</span>
                    <span class="bb-value" id="balanceValue"><?= money($selectedBalance) ?></span>
                </div>

                <form method="post" action="<?= url('customer/withdraw.php') ?>" novalidate>
                    <?= csrf_field() ?>

                    <!-- Account -->
                    <div class="mb-3">
                        <label for="account_id" class="form-label">Withdraw from <span class="req">*</span></label>
                        <select class="form-select form-select-lg" id="account_id" name="account_id"
                                required data-balance-display="#balanceValue">
                            <?php foreach ($accounts as $account): ?>
                                <option value="<?= (int) $account['id'] ?>"
                                        data-balance="<?= e(number_format((float) $account['balance'], 2, '.', '')) ?>"
                                        data-balance-text="<?= e(strip_tags(money($account['balance']))) ?>"
                                        <?= (int) $account['id'] === $selectedId ? 'selected' : '' ?>
                                        <?= $account['account_status'] !== 'Active' ? 'disabled' : '' ?>>
                                    <?= e($account['account_number']) ?> &mdash; <?= e($account['account_type']) ?>
                                    (<?= e(strip_tags(money($account['balance']))) ?>)
                                    <?= $account['account_status'] !== 'Active' ? ' [Deactivated]' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Amount -->
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount <span class="req">*</span></label>
                        <div class="input-group input-group-lg amount-group">
                            <span class="input-group-text"><?= CURRENCY_SYMBOL ?></span>
                            <input type="number" class="form-control" id="amount" name="amount"
                                   value="<?= old('amount') ?>" step="0.01" min="0.01"
                                   max="<?= MAX_WITHDRAWAL ?>" required placeholder="0.00"
                                   data-check-balance="#account_id" autofocus>
                        </div>
                        <div class="form-text" id="amountHelp">
                            Maximum <?= money(MAX_WITHDRAWAL) ?> per withdrawal. You cannot withdraw more than your balance.
                        </div>

                        <div class="quick-amounts mt-2">
                            <?php foreach ([500, 1000, 2000, 5000] as $quick): ?>
                                <button type="button" class="quick-amt" data-amount="<?= $quick ?>" data-target="#amount">
                                    +<?= CURRENCY_SYMBOL ?><?= number_format($quick) ?>
                                </button>
                            <?php endforeach; ?>
                            <button type="button" class="quick-amt" data-amount="max" data-target="#amount"
                                    data-source="#account_id">
                                Full balance
                            </button>
                            <button type="button" class="quick-amt quick-amt-clear" data-amount="clear" data-target="#amount">
                                Clear
                            </button>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-4">
                        <label for="description" class="form-label">Description</label>
                        <input type="text" class="form-control" id="description" name="description"
                               value="<?= old('description') ?>" maxlength="255"
                               placeholder="e.g. ATM withdrawal, rent, groceries">
                        <div class="form-text">Optional. Defaults to "Cash withdrawal".</div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-danger-glow btn-lg">
                            <i class="bi bi-check2-circle me-1"></i> Confirm withdrawal
                        </button>
                        <a href="<?= url('customer/dashboard.php') ?>" class="btn btn-light-soft btn-lg">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==============================================================
         SIDE PANEL
    =============================================================== -->
    <div class="col-lg-5">
        <div class="info-note info-note-warning mb-3">
            <i class="bi bi-shield-exclamation"></i>
            <div>
                <strong>Overdrafts are blocked</strong>
                <p class="mb-0">
                    Your balance is checked on the server after the account row is
                    locked, so a withdrawal larger than your balance is rejected even
                    if two requests arrive at exactly the same moment.
                </p>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-card-head">
                <h3><i class="bi bi-clock-history me-2"></i>Recent withdrawals</h3>
            </div>
            <div class="panel-card-body p-0">
                <?php if (!$recentWithdrawals): ?>
                    <div class="empty-inline">
                        <i class="bi bi-inbox"></i>
                        <p>No withdrawals recorded yet.</p>
                    </div>
                <?php else: ?>
                    <ul class="mini-list">
                        <?php foreach ($recentWithdrawals as $withdrawal): ?>
                            <li>
                                <span class="mini-icon debit"><i class="bi bi-arrow-up-right"></i></span>
                                <div class="mini-meta">
                                    <strong><?= e($withdrawal['description'] ?: 'Cash withdrawal') ?></strong>
                                    <small>
                                        <?= e(mask_account($withdrawal['account_number'])) ?> &middot;
                                        <?= e(pretty_date($withdrawal['created_at'])) ?>
                                    </small>
                                </div>
                                <span class="mini-amt debit">- <?= money($withdrawal['amount']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php
clear_old();
require_once __DIR__ . '/../includes/customer_footer.php';
?>
