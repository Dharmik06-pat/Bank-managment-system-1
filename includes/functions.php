<?php
/**
 * =====================================================================
 * includes/functions.php
 * ---------------------------------------------------------------------
 * Small helper functions shared by every page: output escaping,
 * CSRF protection, flash messages, validation and ID generation.
 *
 * Loaded automatically at the bottom of config/database.php.
 * =====================================================================
 */

/* =====================================================================
 | OUTPUT & URLS
 * ================================================================== */

/**
 * Escape a value for safe printing inside HTML (XSS protection).
 * Used as <?= e($row['full_name']) ?> everywhere in this project.
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Build an absolute URL from a path relative to the project root.
 * url('/login.php')  ->  /banking-management-system/login.php
 */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Send the browser to another page inside the project and stop. */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Format a number as currency, e.g. 12500 -> ₹12,500.00 */
function money($amount): string
{
    return CURRENCY_SYMBOL . number_format((float) $amount, 2);
}

/** Pretty date, e.g. 03 Sep 2026, 04:15 PM */
function pretty_date(?string $mysqlDateTime, bool $withTime = true): string
{
    if (empty($mysqlDateTime)) {
        return '-';
    }
    $ts = strtotime($mysqlDateTime);
    if ($ts === false) {
        return '-';
    }
    return date($withTime ? 'd M Y, h:i A' : 'd M Y', $ts);
}

/** First letters of a name, used for the round avatar bubbles. */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) {
        return '?';
    }
    $first = mb_substr($parts[0], 0, 1);
    $last  = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($first . $last);
}

/** Mask an account number for display: 918273645102 -> ****5102 */
function mask_account(string $accountNumber): string
{
    return '****' . substr($accountNumber, -4);
}

/* =====================================================================
 | CSRF PROTECTION
 |   Every form that changes data posts a hidden token which must match
 |   the one stored in the session. This blocks cross-site request
 |   forgery, where another website silently submits a form as you.
 * ================================================================== */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Print the hidden input. Call inside every <form method="post">. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** True when the submitted token matches the session token. */
function csrf_valid(): bool
{
    $submitted = $_POST['csrf_token'] ?? '';
    return is_string($submitted)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submitted);
}

/**
 * Guard for POST handlers. Redirects back with an error when the
 * token is missing or wrong.
 */
function require_csrf(string $redirectTo): void
{
    if (!csrf_valid()) {
        set_flash('danger', 'Your session expired or the form was invalid. Please try again.');
        redirect($redirectTo);
    }
}

/* =====================================================================
 | FLASH MESSAGES  (one-off alerts that survive a redirect)
 * ================================================================== */

/** $type is a Bootstrap colour: success, danger, warning, info */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Render and clear every queued alert. Called by the layout files. */
function show_flash(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }

    $icons = [
        'success' => 'bi-check-circle-fill',
        'danger'  => 'bi-exclamation-octagon-fill',
        'warning' => 'bi-exclamation-triangle-fill',
        'info'    => 'bi-info-circle-fill',
    ];

    $html = '';
    foreach ($_SESSION['flash'] as $flash) {
        $type = $flash['type'];
        $icon = $icons[$type] ?? 'bi-info-circle-fill';
        $html .= '<div class="alert alert-' . e($type) . ' alert-dismissible fade show d-flex align-items-start gap-2 auto-dismiss" role="alert">'
               . '<i class="bi ' . $icon . ' flex-shrink-0 mt-1"></i>'
               . '<div>' . e($flash['message']) . '</div>'
               . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
               . '</div>';
    }

    unset($_SESSION['flash']);   // show each message only once
    return $html;
}

/* =====================================================================
 | "OLD INPUT"  (re-fill a form after a validation failure)
 * ================================================================== */

function set_old(array $data): void
{
    unset($data['password'], $data['confirm_password'], $data['csrf_token']);
    $_SESSION['old'] = $data;
}

function old(string $key, string $default = ''): string
{
    return e($_SESSION['old'][$key] ?? $default);
}

/** True when the stored old value equals $value (for selects/radios). */
function old_is(string $key, string $value): bool
{
    return isset($_SESSION['old'][$key]) && $_SESSION['old'][$key] === $value;
}

function clear_old(): void
{
    unset($_SESSION['old']);
}

/* =====================================================================
 | VALIDATION HELPERS
 * ================================================================== */

/** Trim a POST field safely. */
function post(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

/**
 * Read a POST field WITHOUT trimming it.
 *
 * Passwords must never be trimmed - a leading or trailing space is a
 * perfectly good character, and silently dropping it would lock someone
 * out of the account they just created. The value still has to be a
 * string though: a request like `password[]=x` arrives as an array, and
 * handing an array to password_verify() raises an uncaught TypeError,
 * which the visitor sees as a blank 500 page.
 */
function raw_post(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? $value : '';
}

/** Trim a GET field safely. */
function get(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function is_valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/** Indian mobile format: 10 digits starting 6-9. */
function is_valid_mobile(string $mobile): bool
{
    return (bool) preg_match('/^[6-9][0-9]{9}$/', $mobile);
}

/** Indian PIN code: exactly 6 digits, not starting with 0. */
function is_valid_pin(string $pin): bool
{
    return (bool) preg_match('/^[1-9][0-9]{5}$/', $pin);
}

/** Letters, numbers and underscore, 4-20 characters. */
function is_valid_username(string $username): bool
{
    return (bool) preg_match('/^[A-Za-z0-9_]{4,20}$/', $username);
}

/**
 * Password rules. Returns an array of problems (empty array = OK).
 */
function password_problems(string $password): array
{
    $problems = [];
    if (strlen($password) < 8) {
        $problems[] = 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Za-z]/', $password)) {
        $problems[] = 'Password must contain at least one letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $problems[] = 'Password must contain at least one number.';
    }
    return $problems;
}

/**
 * Validate an amount string. Returns the float value, or null if invalid.
 * Rejects zero, negatives and anything above the single-transaction cap.
 */
function parse_amount(string $raw, float $max = 10000000.00): ?float
{
    if ($raw === '' || !is_numeric($raw)) {
        return null;
    }
    $amount = round((float) $raw, 2);
    if ($amount <= 0 || $amount > $max) {
        return null;
    }
    return $amount;
}

/** Customer must be at least 18 years old to open an account. */
function age_from_dob(string $dob): ?int
{
    $birth = date_create($dob);
    if (!$birth) {
        return null;
    }
    return (int) date_diff($birth, date_create('today'))->y;
}

/* =====================================================================
 | ID GENERATION
 * ================================================================== */

/**
 * Build a 12-digit account number that is not already in use.
 * The loop guarantees uniqueness even if two people register at once
 * (the UNIQUE index on the column is the final safety net).
 */
function generate_account_number(PDO $pdo): string
{
    $stmt = $pdo->prepare('SELECT 1 FROM accounts WHERE account_number = ? LIMIT 1');

    // Twelve digits gives 900 billion possibilities, so a clash is already
    // vanishingly unlikely - but the loop is capped rather than left to run
    // `while ($taken)` forever. An unbounded retry against the database is a
    // hang, and a request that hangs is harder to diagnose than one that
    // fails loudly. If ten independent draws all collide, something is wrong
    // with the table, not with our luck.
    for ($attempt = 0; $attempt < 10; $attempt++) {

        $number = (string) random_int(100000000000, 999999999999);

        $stmt->execute([$number]);

        if (!$stmt->fetchColumn()) {
            return $number;
        }
    }

    throw new RuntimeException('Could not allocate a free account number after 10 attempts.');
}

/**
 * Build a unique transaction reference, e.g. TXN20260903A1B2C3
 */
function generate_transaction_id(PDO $pdo): string
{
    $stmt = $pdo->prepare('SELECT 1 FROM transactions WHERE transaction_id = ? LIMIT 1');

    do {
        $reference = 'TXN' . date('Ymd') . strtoupper(bin2hex(random_bytes(3)));
        $stmt->execute([$reference]);
        $taken = (bool) $stmt->fetchColumn();
    } while ($taken);

    return $reference;
}

/* =====================================================================
 | PRESENTATION HELPERS
 * ================================================================== */

/** Coloured pill for Active / Inactive. */
function status_badge(string $status): string
{
    $class = ($status === 'Active') ? 'bg-success-subtle text-success-emphasis'
                                    : 'bg-secondary-subtle text-secondary-emphasis';
    return '<span class="badge rounded-pill ' . $class . '">' . e($status) . '</span>';
}

/** Coloured pill for a transaction status. */
function transaction_status_badge(string $status): string
{
    $map = [
        'Success' => 'bg-success-subtle text-success-emphasis',
        'Pending' => 'bg-warning-subtle text-warning-emphasis',
        'Failed'  => 'bg-danger-subtle text-danger-emphasis',
    ];
    $class = $map[$status] ?? 'bg-secondary-subtle text-secondary-emphasis';
    return '<span class="badge rounded-pill ' . $class . '">' . e($status) . '</span>';
}

/** True when this transaction type increases the balance. */
function is_credit(string $type): bool
{
    return in_array($type, ['Deposit', 'Transfer Received'], true);
}

/** Icon + colour pill describing a transaction type. */
function transaction_type_badge(string $type): string
{
    $map = [
        'Deposit'           => ['bi-arrow-down-left', 'txn-credit'],
        'Transfer Received' => ['bi-arrow-down-left', 'txn-credit'],
        'Withdrawal'        => ['bi-arrow-up-right', 'txn-debit'],
        'Transfer Sent'     => ['bi-arrow-up-right', 'txn-debit'],
    ];
    [$icon, $class] = $map[$type] ?? ['bi-dot', 'txn-debit'];

    return '<span class="txn-type ' . $class . '"><i class="bi ' . $icon . '"></i>' . e($type) . '</span>';
}

/** Signed amount, green for money in and red for money out. */
function signed_amount(string $type, $amount): string
{
    $credit = is_credit($type);
    $sign   = $credit ? '+' : '-';
    $class  = $credit ? 'text-success' : 'text-danger';

    return '<span class="fw-semibold ' . $class . '">' . $sign . ' ' . money($amount) . '</span>';
}

/**
 * Rebuild the current query string with one value changed.
 * Used by pagination links so filters are not lost between pages.
 */
function query_with(array $overrides): string
{
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, static fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($params);
}
