<?php
/**
 * =====================================================================
 * includes/customer_header.php
 * ---------------------------------------------------------------------
 * Dashboard shell for the CUSTOMER panel: <head>, fixed sidebar, top
 * bar and the opening tags of the content area.
 *
 * Expects two variables to be set before it is included:
 *     $user      - the signed-in customer row (from require_customer())
 *     $pageTitle - text shown in the browser tab and the top bar
 * =====================================================================
 */

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/database.php';
}

$pageTitle   = $pageTitle ?? 'Dashboard';
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

/** Marks the sidebar link belonging to the page we are on. */
if (!function_exists('panel_active')) {
    function panel_active(string $file): string
    {
        global $currentPage;
        return $currentPage === $file ? ' active' : '';
    }
}

// Menu definition: file => [icon, label]
$customerMenu = [
    'dashboard.php'       => ['bi-grid-1x2',        'Dashboard'],
    'profile.php'         => ['bi-person',          'My Profile'],
    'accounts.php'        => ['bi-wallet2',         'My Accounts'],
    'deposit.php'         => ['bi-box-arrow-in-down','Deposit Money'],
    'withdraw.php'        => ['bi-box-arrow-up',    'Withdraw Money'],
    'transfer.php'        => ['bi-arrow-left-right','Transfer Money'],
    'transactions.php'    => ['bi-clock-history',   'Transaction History'],
    'change_password.php' => ['bi-shield-lock',     'Change Password'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> &middot; <?= e(BANK_NAME) ?></title>

    <link rel="icon" type="image/svg+xml" href="<?= url('assets/images/logo.svg') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="panel-body">

<!-- Dark overlay shown behind the sidebar on small screens -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ================= SIDEBAR ================= -->
<aside class="panel-sidebar" id="panelSidebar">

    <div class="sidebar-brand">
        <a href="<?= url('index.php') ?>" class="d-flex align-items-center gap-2">
            <span class="brand-mark"><i class="bi bi-bank2"></i></span>
            <span class="brand-text">
                <span class="brand-name"><?= e(BANK_NAME) ?></span>
                <span class="brand-tag">Internet Banking</span>
            </span>
        </a>
        <button class="sidebar-close d-lg-none" id="sidebarClose" type="button" aria-label="Close menu">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div class="sidebar-user">
        <span class="avatar-circle"><?= e(initials($user['full_name'])) ?></span>
        <div class="sidebar-user-meta">
            <span class="sidebar-user-name"><?= e($user['full_name']) ?></span>
            <span class="sidebar-user-role">Personal Banking</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <p class="sidebar-label">Menu</p>
        <ul>
            <?php foreach ($customerMenu as $file => $item): ?>
                <li>
                    <a class="sidebar-link<?= panel_active($file) ?>" href="<?= url('customer/' . $file) ?>">
                        <i class="bi <?= e($item[0]) ?>"></i><span><?= e($item[1]) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <p class="sidebar-label mt-3">Session</p>
        <ul>
            <li>
                <a class="sidebar-link sidebar-link-danger" href="<?= url('customer/logout.php') ?>">
                    <i class="bi bi-box-arrow-right"></i><span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-help">
        <i class="bi bi-headset"></i>
        <div>
            <strong>Need help?</strong>
            <span>1800 200 4000</span>
        </div>
    </div>
</aside>

<!-- ================= MAIN ================= -->
<div class="panel-main">

    <header class="panel-topbar">
        <button class="topbar-toggle d-lg-none" id="sidebarToggle" type="button" aria-label="Open menu">
            <i class="bi bi-list"></i>
        </button>

        <div class="topbar-title">
            <h1><?= e($pageTitle) ?></h1>
            <p><?= e(date('l, d F Y')) ?></p>
        </div>

        <div class="topbar-actions">
            <a href="<?= url('index.php') ?>" class="topbar-icon-btn d-none d-sm-inline-flex" title="Visit website">
                <i class="bi bi-globe2"></i>
            </a>

            <div class="dropdown">
                <button class="topbar-user dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="avatar-circle avatar-sm"><?= e(initials($user['full_name'])) ?></span>
                    <span class="d-none d-md-inline"><?= e($user['full_name']) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><h6 class="dropdown-header"><?= e($user['email']) ?></h6></li>
                    <li><a class="dropdown-item" href="<?= url('customer/profile.php') ?>"><i class="bi bi-person me-2"></i>My Profile</a></li>
                    <li><a class="dropdown-item" href="<?= url('customer/change_password.php') ?>"><i class="bi bi-shield-lock me-2"></i>Change Password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= url('customer/logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

    <main class="panel-content">
        <?= show_flash() ?>
