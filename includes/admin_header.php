<?php
/**
 * =====================================================================
 * includes/admin_header.php
 * ---------------------------------------------------------------------
 * Dashboard shell for the ADMIN panel.
 *
 * Expects:
 *     $admin     - the signed-in admin row (from require_admin())
 *     $pageTitle - text shown in the browser tab and the top bar
 * =====================================================================
 */

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/database.php';
}

$pageTitle   = $pageTitle ?? 'Dashboard';
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

if (!function_exists('panel_active')) {
    function panel_active(string $file): string
    {
        global $currentPage;
        return $currentPage === $file ? ' active' : '';
    }
}

$adminMenu = [
    'dashboard.php'    => ['bi-grid-1x2',          'Dashboard'],
    'customers.php'    => ['bi-people',            'Manage Customers'],
    'accounts.php'     => ['bi-wallet2',           'Manage Accounts'],
    'transactions.php' => ['bi-arrow-left-right',  'Manage Transactions'],
    'deposits.php'     => ['bi-box-arrow-in-down', 'Deposit Management'],
    'withdrawals.php'  => ['bi-box-arrow-up',      'Withdrawal Management'],
    'reports.php'      => ['bi-bar-chart-line',    'Reports'],
    'profile.php'      => ['bi-person-badge',      'Admin Profile'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> &middot; Admin &middot; <?= e(BANK_NAME) ?></title>

    <link rel="icon" type="image/svg+xml" href="<?= url('assets/images/logo.svg') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="panel-body panel-body-admin">

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ================= SIDEBAR ================= -->
<aside class="panel-sidebar" id="panelSidebar">

    <div class="sidebar-brand">
        <a href="<?= url('admin/dashboard.php') ?>" class="d-flex align-items-center gap-2">
            <span class="brand-mark brand-mark-admin"><i class="bi bi-shield-lock"></i></span>
            <span class="brand-text">
                <span class="brand-name"><?= e(BANK_NAME) ?></span>
                <span class="brand-tag">Admin Console</span>
            </span>
        </a>
        <button class="sidebar-close d-lg-none" id="sidebarClose" type="button" aria-label="Close menu">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div class="sidebar-user">
        <span class="avatar-circle avatar-admin"><?= e(initials($admin['full_name'])) ?></span>
        <div class="sidebar-user-meta">
            <span class="sidebar-user-name"><?= e($admin['full_name']) ?></span>
            <span class="sidebar-user-role">Administrator</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <p class="sidebar-label">Management</p>
        <ul>
            <?php foreach ($adminMenu as $file => $item): ?>
                <li>
                    <a class="sidebar-link<?= panel_active($file) ?>" href="<?= url('admin/' . $file) ?>">
                        <i class="bi <?= e($item[0]) ?>"></i><span><?= e($item[1]) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <p class="sidebar-label mt-3">Session</p>
        <ul>
            <li>
                <a class="sidebar-link sidebar-link-danger" href="<?= url('admin/logout.php') ?>">
                    <i class="bi bi-box-arrow-right"></i><span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>
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
            <span class="badge-admin d-none d-sm-inline-flex"><i class="bi bi-shield-check me-1"></i>Admin Mode</span>

            <div class="dropdown">
                <button class="topbar-user dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="avatar-circle avatar-sm avatar-admin"><?= e(initials($admin['full_name'])) ?></span>
                    <span class="d-none d-md-inline"><?= e($admin['full_name']) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><h6 class="dropdown-header">@<?= e($admin['username']) ?></h6></li>
                    <li><a class="dropdown-item" href="<?= url('admin/profile.php') ?>"><i class="bi bi-person-badge me-2"></i>Admin Profile</a></li>
                    <li><a class="dropdown-item" href="<?= url('index.php') ?>"><i class="bi bi-globe2 me-2"></i>View Website</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= url('admin/logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

    <main class="panel-content">
        <?= show_flash() ?>
