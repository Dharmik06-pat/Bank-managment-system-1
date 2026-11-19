<?php
/**
 * =====================================================================
 * includes/navbar.php
 * ---------------------------------------------------------------------
 * Public navigation bar. The current page is highlighted automatically
 * by comparing the running script name against each link.
 * =====================================================================
 */

$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');

/** Adds the "active" class to the link for the page we are on. */
function nav_active(string $file): string
{
    global $currentPage;
    return $currentPage === $file ? ' active' : '';
}
?>
<nav class="navbar navbar-expand-lg site-navbar sticky-top" id="siteNavbar">
    <div class="container">

        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= url('index.php') ?>">
            <span class="brand-mark"><i class="bi bi-bank2"></i></span>
            <span class="brand-text">
                <span class="brand-name"><?= e(BANK_NAME) ?></span>
                <span class="brand-tag"><?= e(BANK_TAGLINE) ?></span>
            </span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainNav" aria-controls="mainNav"
                aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <li class="nav-item"><a class="nav-link<?= nav_active('index.php') ?>"    href="<?= url('index.php') ?>">Home</a></li>
                <li class="nav-item"><a class="nav-link<?= nav_active('about.php') ?>"    href="<?= url('about.php') ?>">About</a></li>
                <li class="nav-item"><a class="nav-link<?= nav_active('services.php') ?>" href="<?= url('services.php') ?>">Services</a></li>
                <li class="nav-item"><a class="nav-link<?= nav_active('contact.php') ?>"  href="<?= url('contact.php') ?>">Contact</a></li>

                <?php if (is_customer_logged_in()): ?>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-nav-primary" href="<?= url('customer/dashboard.php') ?>">
                            <i class="bi bi-speedometer2 me-1"></i>My Dashboard
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-nav-ghost<?= nav_active('login.php') ?>" href="<?= url('login.php') ?>">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-nav-primary" href="<?= url('register.php') ?>">Open an Account</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php
/* ---------------------------------------------------------------------
 | Flash messages for public pages.
 |
 | Pages that prefer to place alerts themselves (login.php and
 | register.php show them inside the form card) set
 |     $suppressFlash = true;
 | before including this navbar.
 * ------------------------------------------------------------------ */
if (empty($suppressFlash)) {
    $flashHtml = show_flash();
    if ($flashHtml !== '') {
        echo '<div class="container flash-region">' . $flashHtml . '</div>';
    }
}
?>
