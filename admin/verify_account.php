<?php
/**
 * =====================================================================
 * admin/verify_account.php  -  Account lookup for the counter forms
 * ---------------------------------------------------------------------
 * The deposit and withdrawal forms call this so the teller can confirm
 * they have the right account before posting anything.
 *
 * Unlike the customer version of this endpoint, an administrator is
 * allowed to see the full name and the current balance - a teller must
 * be able to tell the customer what is in their account. Access is
 * still restricted to signed-in admins only.
 * =====================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/* Admin session required. A customer session must not open this door. */
if (!is_admin_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Administrator sign-in required.']);
    exit;
}

$accountNumber = preg_replace('/\s+/', '', get('account_number'));

if (!preg_match('/^[0-9]{12}$/', $accountNumber)) {
    echo json_encode(['ok' => false, 'message' => 'Enter a 12-digit account number.']);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT a.account_number, a.account_type, a.account_status, a.balance,
            u.id AS user_id, u.full_name, u.username, u.status AS owner_status
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

/* Frozen account or deactivated customer: report it, but still show who
   it belongs to so the teller can explain the problem at the counter. */
if ($account['account_status'] !== 'Active') {
    echo json_encode([
        'ok'      => false,
        'message' => $account['full_name'] . ' - this account is frozen. Unfreeze it before posting a transaction.',
    ]);
    exit;
}

if ($account['owner_status'] !== 'Active') {
    echo json_encode([
        'ok'      => false,
        'message' => $account['full_name'] . ' is deactivated. Reactivate the customer before posting a transaction.',
    ]);
    exit;
}

echo json_encode([
    'ok'           => true,
    'name'         => $account['full_name'],
    'username'     => $account['username'],
    'account_type' => $account['account_type'],
    'balance'      => (float) $account['balance'],
    'is_own'       => false,
    'message'      => $account['full_name'] . ' (' . $account['account_type']
                      . ') - balance ' . money($account['balance']),
]);
