<?php
/**
 * =====================================================================
 * includes/header.php
 * ---------------------------------------------------------------------
 * Opening markup for every PUBLIC page (home, about, services, contact,
 * login, register).
 *
 * Set $pageTitle before including this file:
 *     $pageTitle = 'About Us';
 *     require_once __DIR__ . '/includes/header.php';
 * =====================================================================
 */

// Safety net: allow this partial to bootstrap itself if included directly.
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/database.php';
}

$pageTitle       = $pageTitle       ?? BANK_NAME;
$pageDescription = $pageDescription ?? BANK_NAME . ' - secure online banking for everyday life.';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <title><?= e($pageTitle) ?> &middot; <?= e(BANK_NAME) ?></title>

    <link rel="icon" type="image/svg+xml" href="<?= url('assets/images/logo.svg') ?>">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Project stylesheet -->
    <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="site-body">
