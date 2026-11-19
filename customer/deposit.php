<?php
/**
 * =====================================================================
 * customer/deposit.php  -  Pay money into an account
 * ---------------------------------------------------------------------
 * The balance update and the transaction record are written inside one
 * database transaction, so the ledger can never disagree with the
 * balance. SELECT ... FOR UPDATE locks the row while we work on it.
 * =====================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user      = require_customer();
$pageTitle = 'Deposit Money';

/** Largest single deposit allowed. */
define('MAX_DEPOSIT', 1000000.00);

/* =====================================================================
 | FORM HANDLING
 * ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_csrf('customer/deposit.php');

    $accountId   = (int) post('account_id');
    $amountRaw   = post('amount');
    $description = post('description');

    $errors = [];

    /* ----- Validate the account ----------------------------------- */
    // get_owned_account() returns null unless this account really
    // belongs to the signed-in customer.
    $account = get_owned_account($pdo, $accountId, (int) $user['id']);

    if (!$account) {
        $errors[] = 'Please choose one of your own accounts.';
    } elseif ($account['account_status'] !== 'Active') {
        $errors[] = 'That account is deactivated and cannot receive deposits.';
    }

    /* ----- Validate the amount ------------------------------------ */
    $amount = parse_amount($amountRaw, MAX_DEPOSIT);

    if ($amount === null) {
        $errors[] = 'Enter an amount greater than ' . money(0) . ' and no more than ' . money(MAX_DEPOSIT) . '.';
    }

    /* ----- Validate the description ------------------------------- */
    if (mb_strlen($description) > 255) {
        $errors[] = 'Description must be 255 characters or fewer.';
    }
    if ($description === '') {
        $description = 'Cash deposit';
    }

    /* ----- Perform the deposit ------------------------------------ */
    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Lock this account row until the transaction finishes, so
            // two simultaneous deposits cannot overwrite each other.
            $stmt = $pdo->prepare('SELECT * FROM accounts WHERE id = ? AND user_id = ? FOR UPDATE');
            $stmt->execute([$accountId, $user['id']]);
            $locked = $stmt->fetch();

            if (!$locked || $locked['account_status'] !== 'Active') {
                throw new RuntimeException('Account unavailable.');
            }

            $newBalance = (float) $locked['balance'] + $amount;

            // 1. Update the balance
            $stmt = $pdo->prepare('UPDATE accounts SET balance = ? WHERE id = ?');
            $stmt->execute([$newBalance, $accountId]);

            // 2. Record the movement in the ledger
            $reference = generate_transaction_id($pdo);

            $stmt = $pdo->prepare(
                'INSERT INTO transactions
                    (transaction_id, account_id, related_account_number,
                     transaction_type, amount, description, status)
                 VALUES (?, ?, NULL, "Deposit", ?, ?, "Success")'
            );
            $stmt->execute([$reference, $accountId, $amount, $description]);

            $pdo->commit();

            set_flash('success',
                money($amount) . ' deposited successfully. Reference ' . $reference .
                '. New balance: ' . money($newBalance) . '.'
            );
            redirect('customer/deposit.php?account=' . $accountId);

        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[BMS] Deposit failed: ' . $ex->getMessage());
            $errors[] = 'The deposit could not be completed. No money has been moved. Please try again.';
        }
    }

    set_old($_POST);
    foreach ($errors as $error) {
        set_flash('danger', $error);
    }
    redirect('customer/deposit.php');
}

/* =====================================================================
 | DATA
 * ================================================================== */
$accounts = get_user_accounts($pdo, (int) $user['id']);

// Pre-select the account passed in the URL, if it is one of theirs.
$selectedId = (int) get('account');
$validIds   = array_map('intval', array_column($accounts, 'id'));
if (!in_array($selectedId, $validIds, true)) {
    $selectedId = $validIds[0] ?? 0;
}

/* Five most recent deposits, for the side panel. */
$recentDeposits = [];
if ($accounts) {
    $placeholders = implode(',', array_fill(0, count($validIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT t.*, a.account_number
         FROM transactions t
         INNER JOIN accounts a ON a.id = t.account_id
         WHERE t.account_id IN ($placeholders) AND t.transaction_type = 'Deposit'
         ORDER BY t.created_at DESC, t.id DESC
         LIMIT 5"
    );
    $stmt->execute($validIds);
    $recentDeposits = $stmt->fetchAll();
}

require_once __DIR__ . '/../includes/customer_header.php';
?>

<?php if (!$accounts): ?>

    <div class="empty-panel">
        <span class="empty-icon"><i class="bi bi-wallet2"></i></span>
        <h3>You need an account first</h3>
        <p>Open a savings or current account before making a deposit.</p>
        <a href="<?= url('customer/accounts.php') ?>" class="btn btn-primary-glow btn-lg mt-2">
            <i class="bi bi-plus-circle me-1"></i> Open an account
        </a>
    </div>

<?php else: ?>

<div class="row g-3 g-xl-4">

    <!-- ==============================================================
         DEPOSIT FORM
    =============================================================== -->
    <div class="col-lg-7">
        <div class="panel-card">
            <div class="panel-card-head">
                <h3><i class="bi bi-box-arrow-in-down me-2"></i>Deposit money</h3>
            </div>

            <div class="panel-card-body">
                <p class="text-muted small mb-4">
                    Choose the account to credit and enter the amount. The new balance
                    appears immediately and a receipt reference is generated for your records.
                </p>

                <form method="post" action="<?= url('customer/deposit.php') ?>" novalidate id="depositForm">
                    <?= csrf_field() ?>

                    <!-- Account -->
                    <div class="mb-3">
                        <label for="account_id" class="form-label">Deposit into <span class="req">*</span></label>
                        <select class="form-select form-select-lg" id="account_id" name="account_id" required>
                            <?php foreach ($accounts as $account): ?>
                                <option value="<?= (int) $account['id'] ?>"
                                        data-balance="<?= e($account['balance']) ?>"
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
                                   max="<?= MAX_DEPOSIT ?>" required placeholder="0.00" autofocus>
                        </div>
                        <div class="form-text">
                            Maximum <?= money(MAX_DEPOSIT) ?> per deposit.
                        </div>

                        <!-- Quick amount buttons -->
                        <div class="quick-amounts mt-2">
                            <?php foreach ([500, 1000, 5000, 10000, 25000] as $quick): ?>
                                <button type="button" class="quick-amt" data-amount="<?= $quick ?>" data-target="#amount">
                                    +<?= CURRENCY_SYMBOL ?><?= number_format($quick) ?>
                                </button>
                            <?php endforeach; ?>
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
                               placeholder="e.g. Salary, cash deposit, cheque">
                        <div class="form-text">Optional. Defaults to "Cash deposit".</div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary-glow btn-lg">
                            <i class="bi bi-check2-circle me-1"></i> Confirm deposit
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
        <div class="info-note info-note-success mb-3">
            <i class="bi bi-lightning-charge-fill"></i>
            <div>
                <strong>Instant credit</strong>
                <p class="mb-0">
                    Deposits appear in your balance straight away. Every deposit is
                    written to the ledger inside a database transaction, so your
                    balance and your statement can never fall out of step.
                </p>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-card-head">
                <h3><i class="bi bi-clock-history me-2"></i>Recent deposits</h3>
            </div>
            <div class="panel-card-body p-0">
                <?php if (!$recentDeposits): ?>
                    <div class="empty-inline">
                        <i class="bi bi-inbox"></i>
                        <p>No deposits recorded yet.</p>
                    </div>
                <?php else: ?>
                    <ul class="mini-list">
                        <?php foreach ($recentDeposits as $deposit): ?>
                            <li>
                                <span class="mini-icon credit"><i class="bi bi-arrow-down-left"></i></span>
                                <div class="mini-meta">
                                    <strong><?= e($deposit['description'] ?: 'Cash deposit') ?></strong>
                                    <small>
                                        <?= e(mask_account($deposit['account_number'])) ?> &middot;
                                        <?= e(pretty_date($deposit['created_at'])) ?>
                                    </small>
                                </div>
                                <span class="mini-amt credit">+ <?= money($deposit['amount']) ?></span>
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
