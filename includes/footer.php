<?php
/**
 * =====================================================================
 * includes/footer.php
 * ---------------------------------------------------------------------
 * Closing markup for every PUBLIC page: site footer plus the shared
 * JavaScript bundles.
 * =====================================================================
 */
?>
<footer class="site-footer">
    <div class="container">
        <div class="row g-4 gy-5">

            <!-- Brand column -->
            <div class="col-lg-4">
                <a class="footer-brand d-inline-flex align-items-center gap-2 mb-3" href="<?= url('index.php') ?>">
                    <span class="brand-mark"><i class="bi bi-bank2"></i></span>
                    <span class="brand-name text-white"><?= e(BANK_NAME) ?></span>
                </a>
                <p class="footer-text">
                    A modern banking platform built for people who expect their money
                    to move quickly, quietly and safely. Every transfer is protected by
                    bank-grade encryption and monitored around the clock.
                </p>
                <div class="footer-socials">
                    <a href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                    <a href="#" aria-label="Twitter"><i class="bi bi-twitter-x"></i></a>
                    <a href="#" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
                    <a href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                </div>
            </div>

            <!-- Link columns -->
            <div class="col-6 col-lg-2">
                <h6 class="footer-heading">Bank</h6>
                <ul class="footer-links">
                    <li><a href="<?= url('index.php') ?>">Home</a></li>
                    <li><a href="<?= url('about.php') ?>">About Us</a></li>
                    <li><a href="<?= url('services.php') ?>">Services</a></li>
                    <li><a href="<?= url('contact.php') ?>">Contact</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h6 class="footer-heading">Banking</h6>
                <ul class="footer-links">
                    <li><a href="<?= url('register.php') ?>">Open an Account</a></li>
                    <li><a href="<?= url('login.php') ?>">Internet Banking</a></li>
                    <li><a href="<?= url('services.php') ?>#savings">Savings Account</a></li>
                    <li><a href="<?= url('services.php') ?>#current">Current Account</a></li>
                </ul>
            </div>

            <div class="col-lg-4">
                <h6 class="footer-heading">Get in touch</h6>
                <ul class="footer-contact">
                    <li><i class="bi bi-geo-alt"></i><span>Level 12, Prestige Tower, MG Road, Bengaluru 560001</span></li>
                    <li><i class="bi bi-telephone"></i><span>1800 200 4000 (toll free, 24&times;7)</span></li>
                    <li><i class="bi bi-envelope"></i><span>support@apextrustbank.example</span></li>
                </ul>
                <a class="btn btn-nav-primary mt-2" href="<?= url('admin/login.php') ?>">
                    <i class="bi bi-shield-lock me-1"></i>Staff Login
                </a>
            </div>
        </div>

        <hr class="footer-divider">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <p class="footer-copy mb-0">
                &copy; <?= date('Y') ?> <?= e(BANK_NAME) ?>. All rights reserved.
            </p>
            <p class="footer-copy mb-0">
                Academic demonstration project &middot; Built with Core PHP &amp; MySQL
            </p>
        </div>
    </div>
</footer>

<!-- Back to top -->
<button class="back-to-top" id="backToTop" type="button" aria-label="Back to top">
    <i class="bi bi-arrow-up"></i>
</button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/js/script.js') ?>"></script>
</body>
</html>
