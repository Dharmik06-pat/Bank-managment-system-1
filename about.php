<?php
/**
 * =====================================================================
 * about.php  -  About the bank
 * =====================================================================
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth_check.php';

$pageTitle       = 'About Us';
$pageDescription = 'Learn about ' . BANK_NAME . ', our history, values and leadership.';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- PAGE HERO -->
<section class="page-hero">
    <div class="hero-bg" aria-hidden="true">
        <span class="hero-orb hero-orb-1"></span>
        <span class="hero-grid"></span>
    </div>
    <div class="container position-relative text-center">
        <nav class="page-crumbs" aria-label="breadcrumb">
            <a href="<?= url('index.php') ?>">Home</a> <i class="bi bi-chevron-right"></i> <span>About</span>
        </nav>
        <h1 class="page-hero-title">Built on trust, run with care</h1>
        <p class="page-hero-sub">
            Four decades of looking after other people's money has taught us that
            banking is far less about technology than it is about keeping promises.
        </p>
    </div>
</section>

<!-- STORY -->
<section class="section">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <span class="section-eyebrow">Our story</span>
                <h2 class="section-title">From one counter to two million customers</h2>
                <p class="section-sub">
                    <?= e(BANK_NAME) ?> opened in 1986 in a two-room office with a single
                    teller counter and a hand-written ledger. The founding idea was
                    modest: serve the neighbourhood honestly, and let word of mouth do
                    the marketing.
                </p>
                <p class="section-sub">
                    That approach worked. We added branches slowly and deliberately,
                    refusing to grow faster than we could train people. By the time
                    computers arrived in our back offices in the late nineties, we had
                    120 branches and a reputation for never losing a rupee.
                </p>
                <p class="section-sub">
                    Today most of our banking happens on screens rather than at counters.
                    The tools changed completely; the standard did not.
                </p>
            </div>

            <div class="col-lg-6">
                <div class="timeline reveal">
                    <div class="timeline-item">
                        <span class="timeline-year">1986</span>
                        <div class="timeline-body">
                            <h4>The first branch</h4>
                            <p>Opened in Bengaluru with four employees and a hand-written ledger.</p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <span class="timeline-year">1998</span>
                        <div class="timeline-body">
                            <h4>Core banking goes digital</h4>
                            <p>Every branch moved onto a shared computer system, cutting transfer times from days to minutes.</p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <span class="timeline-year">2010</span>
                        <div class="timeline-body">
                            <h4>Internet banking launched</h4>
                            <p>Customers could finally check balances and move money without visiting a branch.</p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <span class="timeline-year">2026</span>
                        <div class="timeline-body">
                            <h4>A platform rebuilt from scratch</h4>
                            <p>The dashboard you are looking at &mdash; faster, clearer and secure by design.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- VALUES -->
<section class="section section-tinted">
    <div class="container">
        <div class="section-head text-center">
            <span class="section-eyebrow">What we stand for</span>
            <h2 class="section-title">Four values we do not negotiate on</h2>
            <p class="section-sub">These are the tests we apply before shipping anything that touches a customer's money.</p>
        </div>

        <div class="row g-4">
            <?php
            $values = [
                ['bi-shield-check', 'Safety before speed',    'If a feature is fast but leaves any doubt about where money sits, it does not ship. Correctness always wins.'],
                ['bi-eye',          'No hidden anything',     'Fees, limits and processing times are stated plainly. If we would be embarrassed to explain it, we do not do it.'],
                ['bi-hand-thumbs-up','Respect for your time', 'Every screen exists to answer a question or complete a task. Nothing is there to fill space.'],
                ['bi-people',       'People over process',    'A real person answers the phone. Rules exist to serve customers, not to shield us from them.'],
            ];
            foreach ($values as $value):
                [$icon, $title, $text] = $value;
            ?>
                <div class="col-md-6 col-lg-3">
                    <div class="value-card reveal">
                        <span class="service-icon"><i class="bi <?= e($icon) ?>"></i></span>
                        <h3><?= e($title) ?></h3>
                        <p><?= e($text) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- NUMBERS -->
<section class="section">
    <div class="container">
        <div class="row g-4 text-center">
            <div class="col-md-3 col-6">
                <div class="about-stat reveal">
                    <i class="bi bi-people-fill"></i>
                    <span class="as-value" data-count="2400000" data-suffix="+">0</span>
                    <span class="as-label">Customers served</span>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="about-stat reveal">
                    <i class="bi bi-building"></i>
                    <span class="as-value" data-count="1250" data-suffix="+">0</span>
                    <span class="as-label">Branches</span>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="about-stat reveal">
                    <i class="bi bi-calendar-check"></i>
                    <span class="as-value" data-count="40" data-suffix="+">0</span>
                    <span class="as-label">Years of service</span>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="about-stat reveal">
                    <i class="bi bi-graph-up"></i>
                    <span class="as-value">99.98%</span>
                    <span class="as-label">Platform uptime</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="section pt-0">
    <div class="container">
        <div class="cta-panel text-center reveal">
            <h3>Join two million people who bank with us</h3>
            <p>Opening an account takes about three minutes and costs nothing.</p>
            <div class="d-flex flex-wrap gap-3 justify-content-center mt-4">
                <a href="<?= url('register.php') ?>" class="btn btn-primary-glow btn-lg">Open an Account <i class="bi bi-arrow-right ms-1"></i></a>
                <a href="<?= url('contact.php') ?>" class="btn btn-outline-navy btn-lg">Talk to us first</a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
