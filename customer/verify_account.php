<?php
/**
 * =====================================================================
 * customer/verify_account.php  -  Small JSON endpoint
 * ---------------------------------------------------------------------
 * The transfer form calls this with an account number and shows the
 * receiver's name, so the customer can confirm they are paying the
 * right person before sending.
 *
 * Privacy note: this returns only a MASKED name (e.g. "Ravi K.") and
 * never the email, mobile number or balance. Anything more would let
 * someone enumerate account numbers to harvest customer details.
 * =====================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/* Only a signed-in customer may use this endpoint. */
if (!is_customer_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Not signed in.']);
    exit;
}

/* Re-read the caller's own status on every request.
   Every other customer page goes through require_customer(), which
   refuses a deactivated account. That helper redirects to a HTML page
   though, which is no use to a JSON endpoint, so the same check is
   repeated here by hand. Without it a customer deactivated part way
   through a session would keep a working account-lookup. */
$meStmt = $pdo->prepare('SELECT status FROM users WHERE id = ? LIMIT 1');
$meStmt->execute([(int) ($_SESSION['customer_id'] ?? 0)]);

if ($meStmt->fetchColumn() !== 'Active') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Your account is not active.']);
    exit;
}

$accountNumber = preg_replace('/\s+/', '', get('account_number'));

/* Basic shape check before touching the database. */
if (!preg_match('/^[0-9]{12}$/', $accountNumber)) {
    echo json_encode(['ok' => false, 'message' => 'Enter a 12-digit account number.']);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT a.id, a.account_type, a.account_status, a.user_id,
            u.full_name, u.status AS owner_status
     FROM accounts a
     INNER JOIN users u ON u.id = a.user_id
     WHERE a.account_number = ?
     LIMIT 1'
);
$stmt->execute([$accountNumber]);
$account = $stmt->fetch();

if (!$account) {
    echo json_encode(['ok' => false, 'message' => 'No account found with that number.']);
    exit;
}

if ($account['account_status'] !== 'Active' || $account['owner_status'] !== 'Active') {
    echo json_encode(['ok' => false, 'message' => 'That account is not active and cannot receive transfers.']);
    exit;
}

/* Sending to yourself is not allowed. */
$isOwnAccount = (int) $account['user_id'] === (int) $_SESSION['customer_id'];

/* Mask the name: "Ravi Kumar Sharma" becomes "Ravi K. S." */
$nameParts = preg_split('/\s+/', trim($account['full_name']), -1, PREG_SPLIT_NO_EMPTY);
$maskedName = array_shift($nameParts);
foreach ($nameParts as $part) {
    $maskedName .= ' ' . mb_strtoupper(mb_substr($part, 0, 1)) . '.';
}

echo json_encode([
    'ok'            => true,
    'name'          => $maskedName,
    'account_type'  => $account['account_type'],
    'is_own'        => $isOwnAccount,
    'message'       => $isOwnAccount
        ? 'This is your own account. Choose a different receiver.'
        : 'Receiver: ' . $maskedName . ' (' . $account['account_type'] . ')',
]);
