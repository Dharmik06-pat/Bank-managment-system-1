<?php
/**
 * includes/customer_footer.php
 * Closes the customer dashboard shell opened in customer_header.php.
 */
?>
    </main>

    <footer class="panel-footer">
        <span>&copy; <?= date('Y') ?> <?= e(BANK_NAME) ?>. All rights reserved.</span>
        <span class="d-none d-sm-inline">Secured with 256-bit encryption</span>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= url('assets/js/script.js') ?>"></script>
</body>
</html>
