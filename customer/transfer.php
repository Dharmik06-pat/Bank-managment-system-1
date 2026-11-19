<?php
/**
 * =====================================================================
 * customer/transfer.php  -  Move money between accounts
 * ---------------------------------------------------------------------
 * This is the most safety-critical page in the project. Four rules:
 *
 *   1. Everything runs inside ONE MySQL transaction. Either both sides
 *      of the transfer happen, or neither does.
 *   2. Both account rows are locked with FOR UPDATE before any balance
 *      is read, so concurrent transfers cannot corrupt a balance.
 *   3. The two rows are always locked in ascending id order. If two
 *      transfers ran in opposite directions at the same moment and
 *      each locked one row first, they would wait on each other
 *      forever - a deadlock. Consistent ordering prevents that.
 *   4. Two ledger rows are written (Transfer Sent and Transfer
 *      Received) so both customers see the movement on their statement.
 * =====================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user      = require_customer();
$pageTitle = 'Transfer Money';

/** Largest single transfer allowed. */
define('MAX_TRANSFER', 500000.00);

/* =====================================================================
 | FORM HANDLING
 * ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_csrf('customer/transfer.php');

    $senderAccountId = (int) post('sender_account_id');
    $receiverNumber  = preg_replace('/\s+/', '', post('receiver_account_number'));
    $amountRaw       = post('amount');
    $description     = post('description');

    $errors = [];

    /* ----- 1. Validate the sending account ------------------------ */
    $senderAccount = get_owned_account($pdo, $senderAccountId, (int) $user['id']);

    if (!$senderAccount) {
        $errors[] = 'Please choose one of your own accounts to send from.';
    } elseif ($senderAccount['account_status'] !== 'Active') {
        $errors[] = 'The sending account is deactivated and cannot transfer money.';
    }

    /* ----- 2. Validate the receiving account ---------------------- */
    $receiverAccount = null;

    if ($receiverNumber === '' || !preg_match('/^[0-9]{12}$/', $receiverNumber)) {
        $errors[] = 'Enter a valid 12-digit receiver account number.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT a.*, u.full_name, u.status AS owner_status
             FROM accounts a
             INNER JOIN users u ON u.id = a.user_id
             WHERE a.account_number = ? LIMIT 1'
        );
        $stmt->execute([$receiverNumber]);
        $receiverAccount = $stmt->fetch();

        if (!$receiverAccount) {
            $errors[] = 'No account was found with that number. Please check and try again.';
        } elseif ($receiverAccount['account_status'] !== 'Active'
                  || $receiverAccount['owner_status'] !== 'Active') {
            $errors[] = 'The receiving account is not active and cannot accept transfers.';
        } elseif ($senderAccount && (int) $receiverAccount['id'] === (int) $senderAccount['id']) {
            // Sending to yourself achieves nothing and would double-lock one row.
            $errors[] = 'You cannot transfer money to the same account. Choose a different receiver.';
        }
    }

    /* ----- 3. Validate the amount --------------------------------- */
    $amount = parse_amount($amountRaw, MAX_TRANSFER);

    if ($amount === null) {
        $errors[] = 'Enter an amount greater than ' . money(0) . ' and no more than ' . money(MAX_TRANSFER) . '.';
    }

    /* ----- 4. Validate the description ---------------------------- */
    if (mb_strlen($description) > 255) {
        $errors[] = 'Description must be 255 characters or fewer.';
    }
    if ($description === '') {
        $description = 'Fund transfer';
    }

    /* ----- 5. Perform the transfer -------------------------------- */
    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $senderId   = (int) $senderAccount['id'];
            $receiverId = (int) $receiverAccount['id'];

            /* Lock BOTH rows in ascending id order (see rule 3 above).
               A single query with IN (...) ORDER BY id locks them in a
               predictable sequence. */
            $lockStmt = $pdo->prepare(
                'SELECT * FROM accounts WHERE id IN (?, ?) ORDER BY id ASC FOR UPDATE'
            );
            $lockStmt->execute([
                min($senderId, $receiverId),
                max($senderId, $receiverId),
            ]);

            // Index the locked rows by id so we can pick each one out.
            $lockedRows = [];
            foreach ($lockStmt->fetchAll() as $row) {
                $lockedRows[(int) $row['id']] = $row;
            }

            $lockedSender   = $lockedRows[$senderId]   ?? null;
            $lockedReceiver = $lockedRows[$receiverId] ?? null;

            if (!$lockedSender || !$lockedReceiver) {
                throw new RuntimeException('One of the accounts is no longer available.');
            }

            if ($lockedSender['account_status'] !== 'Active'
                || $lockedReceiver['account_status'] !== 'Active') {
                throw new RuntimeException('One of the accounts is not active.');
            }

            $senderBalance = (float) $lockedSender['balance'];

            /* Balance check AFTER the lock. */
            if ($amount > $senderBalance) {
                $pdo->rollBack();
                set_flash('danger',
                    'Insufficient balance. You tried to send ' . money($amount) .
                    ' but only ' . money($senderBalance) . ' is available.'
                );
                set_old($_POST);
                redirect('customer/transfer.php');
            }

            $newSenderBalance   = $senderBalance - $amount;
            $newReceiverBalance = (float) $lockedReceiver['balance'] + $amount;

            /* ----- Move the money ----- */
            $update = $pdo->prepare('UPDATE accounts SET balance = ? WHERE id = ?');
            $update->execute([$newSenderBalance,   $senderId]);
            $update->execute([$newReceiverBalance, $receiverId]);

            /* ----- Write both ledger rows ----- */
            // Both sides share a reference suffix so the pair can be
            // matched up later, but each row keeps a unique id.
            $sentReference     = generate_transaction_id($pdo);
            $receivedReference = generate_transaction_id($pdo);

            $insert = $pdo->prepare(
                'INSERT INTO transactions
                    (transaction_id, account_id, related_account_number,
                     transaction_type, amount, description, status)
                 VALUES (?, ?, ?, ?, ?, ?, "Success")'
            );

            // Debit row on the sender's statement
            $insert->execute([
                $sentReference,
                $senderId,
                $lockedReceiver['account_number'],
                'Transfer Sent',
                $amount,
                $description,
            ]);

            // Credit row on the receiver's statement
            $insert->execute([
                $receivedReference,
                $receiverId,
                $lockedSender['account_number'],
                'Transfer Received',
                $amount,
                $description,
            ]);

            $pdo->commit();

            set_flash('success',
                money($amount) . ' sent to ' . $receiverAccount['full_name'] .
                ' (' . mask_account($lockedReceiver['account_number']) . ').' .
                ' Reference ' . $sentReference . '. New balance: ' . money($newSenderBalance) . '.'
            );
            clear_old();
            redirect('customer/transfer.php');

        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[BMS] Transfer failed: ' . $ex->getMessage());
            $errors[] = 'The transfer could not be completed. No money has left your account. Please try again.';
        }
    }

    set_old($_POST);
    foreach ($errors as $error) {
        set_flash('danger', $error);
    }
    redirect('customer/transfer.php');
}

/* =====================================================================
 | DATA
 * ================================================================== */
$accounts = get_user_accounts($pdo, (int) $user['id']);
$validIds = array_map('intval', array_column($accounts, 'id'));

$selectedId      = $validIds[0] ?? 0;
$selectedBalance = $accounts ? (float) $accounts[0]['balance'] : 0.0;

/* Five most recent transfers sent. */
$recentTransfers = [];
if ($accounts) {
    $placeholders = implode(',', array_fill(0, count($validIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT t.*, a.account_number
         FROM transactions t
         INNER JOIN accounts a ON a.id = t.account_id
         WHERE t.account_id IN ($placeholders) AND t.transaction_type = 'Transfer Sent'
         ORDER BY t.created_at DESC, t.id DESC
         LIMIT 5"
    );
    $stmt->execute($validIds);
    $recentTransfers = $stmt->fetchAll();
}

require_once __DIR__ . '/../includes/customer_header.php';
?>

<?php if (!$accounts): ?>

    <div class="empty-panel">
        <span class="empty-icon"><i class="bi bi-wallet2"></i></span>
        <h3>You need an account first</h3>
        <p>Open a savings or current account before transferring money.</p>
        <a href="<?= url('customer/accounts.php') ?>" class="btn btn-primary-glow btn-lg mt-2">
            <i class="bi bi-plus-circle me-1"></i> Open an account
        </a>
    </div>

<?php else: ?>

<div class="row g-3 g-xl-4">

    <!-- ==============================================================
         TRANSFER FORM
    =============================================================== -->
    <div class="col-lg-7">
        <div class="panel-card">
            <div class="panel-card-head">
                <h3><i class="bi bi-arrow-left-right me-2"></i>Transfer money</h3>
            </div>

            <div class="panel-card-body">

                <div class="balance-banner" id="balanceBanner">
                    <span class="bb-label">Available to send</span>
                    <span class="bb-value" id="balanceValue"><?= money($selectedBalance) ?></span>
                </div>

                <form method="post" action="<?= url('customer/transfer.php') ?>" novalidate id="transferForm">
                    <?= csrf_field() ?>

                    <!-- Sender -->
                    <div class="mb-3">
                        <label for="sender_account_id" class="form-label">From account <span class="req">*</span></label>
                        <select class="form-select form-select-lg" id="sender_account_id" name="sender_account_id"
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

                    <div class="transfer-arrow" aria-hidden="true">
                        <span><i class="bi bi-arrow-down"></i></span>
                    </div>

                    <!-- Receiver -->
                    <div class="mb-3">
                        <label for="receiver_account_number" class="form-label">
                            To account number <span class="req">*</span>
                        </label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text"><i class="bi bi-bank"></i></span>
                            <input type="text" class="form-control" id="receiver_account_number"
                                   name="receiver_account_number" value="<?= old('receiver_account_number') ?>"
                                   maxlength="12" inputmode="numeric" pattern="[0-9]{12}" required
                                   placeholder="12-digit account number" data-digits-only
                                   data-verify-account="<?= url('customer/verify_account.php') ?>">
                        </div>
                        <!-- Filled in by JavaScript once the number is 12 digits -->
                        <div class="form-text" id="receiverStatus">
                            The receiver's name appears here once you enter a valid account number.
                        </div>
                    </div>

                    <!-- Amount -->
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount <span class="req">*</span></label>
                        <div class="input-group input-group-lg amount-group">
                            <span class="input-group-text"><?= CURRENCY_SYMBOL ?></span>
                            <input type="number" class="form-control" id="amount" name="amount"
                                   value="<?= old('amount') ?>" step="0.01" min="0.01"
                                   max="<?= MAX_TRANSFER ?>" required placeholder="0.00"
                                   data-check-balance="#sender_account_id">
                        </div>
                        <div class="form-text">Maximum <?= money(MAX_TRANSFER) ?> per transfer.</div>

                        <div class="quick-amounts mt-2">
                            <?php foreach ([500, 1000, 5000, 10000] as $quick): ?>
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
                               placeholder="e.g. Rent, shared dinner, loan repayment">
                        <div class="form-text">Optional. Shown on both statements.</div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary-glow btn-lg">
                            <i class="bi bi-send me-1"></i> Send money
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
        <div class="info-note mb-3">
            <i class="bi bi-shield-lock-fill"></i>
            <div>
                <strong>All or nothing</strong>
                <p class="mb-0">
                    The debit, the credit and both statement entries are written in a
                    single database transaction. If any step fails, the whole transfer
                    is rolled back and your balance is untouched.
                </p>
            </div>
        </div>

        <div class="info-note info-note-warning mb-3">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <strong>Check before you send</strong>
                <p class="mb-0">
                    Transfers are instant and cannot be reversed from your dashboard.
                    Always confirm the receiver's name shown under the account number
                    before pressing send.
                </p>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-card-head">
                <h3><i class="bi bi-clock-history me-2"></i>Recent transfers sent</h3>
            </div>
            <div class="panel-card-body p-0">
                <?php if (!$recentTransfers): ?>
                    <div class="empty-inline">
                        <i class="bi bi-inbox"></i>
                        <p>No transfers sent yet.</p>
                    </div>
                <?php else: ?>
                    <ul class="mini-list">
                        <?php foreach ($recentTransfers as $transfer): ?>
                            <li>
                                <span class="mini-icon debit"><i class="bi bi-arrow-up-right"></i></span>
                                <div class="mini-meta">
                                    <strong>To <?= e(mask_account($transfer['related_account_number'] ?? '')) ?></strong>
                                    <small>
                                        <?= e($transfer['description'] ?: 'Fund transfer') ?> &middot;
                                        <?= e(pretty_date($transfer['created_at'])) ?>
                                    </small>
                                </div>
                                <span class="mini-amt debit">- <?= money($transfer['amount']) ?></span>
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
