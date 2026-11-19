<?php
/**
 * =====================================================================
 * index.php  -  Home page
 * ---------------------------------------------------------------------
 * The public landing page of the bank: hero, services, features,
 * security, about and contact sections.
 * =====================================================================
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth_check.php';

$pageTitle       = 'Home';
$pageDescription = BANK_NAME . ' - open a savings or current account online and bank securely from anywhere.';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- ==================================================================
     HERO
=================================================================== -->
<section class="hero">
    <div class="hero-bg" aria-hidden="true">
        <span class="hero-orb hero-orb-1"></span>
        <span class="hero-orb hero-orb-2"></span>
        <span class="hero-grid"></span>
    </div>

    <div class="container position-relative">
        <div class="row align-items-center g-5">

            <div class="col-lg-6">
                <span class="hero-badge reveal">
                    <i class="bi bi-patch-check-fill"></i> Trusted by 2.4 million customers
                </span>

                <h1 class="hero-title reveal">
                    Banking that keeps up<br>
                    with <span class="text-gradient">your ambition</span>
                </h1>

                <p class="hero-sub reveal">
                    Open a savings or current account in minutes, move money instantly,
                    and watch every rupee in real time. No branch queues, no paperwork,
                    no hidden charges &mdash; just clean, secure, modern banking.
                </p>

                <div class="hero-actions reveal">
                    <a href="<?= url('register.php') ?>" class="btn btn-primary-glow btn-lg">
                        Open an Account <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                    <a href="<?= url('login.php') ?>" class="btn btn-outline-light-strong btn-lg">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Internet Banking
                    </a>
                </div>

                <div class="hero-stats reveal">
                    <div class="hero-stat">
                        <span class="hero-stat-value" data-count="2400000" data-suffix="+">0</span>
                        <span class="hero-stat-label">Happy customers</span>
                    </div>
                    <div class="hero-stat">
                        <span class="hero-stat-value" data-count="1250" data-suffix="+">0</span>
                        <span class="hero-stat-label">Branches nationwide</span>
                    </div>
                    <div class="hero-stat">
                        <span class="hero-stat-value">99.98%</span>
                        <span class="hero-stat-label">Platform uptime</span>
                    </div>
                </div>
            </div>

            <!-- Floating debit-card mock-up -->
            <div class="col-lg-6">
                <div class="hero-visual reveal">

                    <div class="bank-card">
                        <div class="bank-card-shine" aria-hidden="true"></div>
                        <div class="bank-card-top">
                            <span class="bank-card-brand"><i class="bi bi-bank2"></i> <?= e(BANK_NAME) ?></span>
                            <i class="bi bi-broadcast bank-card-nfc"></i>
                        </div>
                        <div class="bank-card-chip" aria-hidden="true"></div>
                        <div class="bank-card-number">5412&nbsp; 7534&nbsp; 8901&nbsp; 2468</div>
                        <div class="bank-card-bottom">
                            <div>
                                <span class="bank-card-label">Card Holder</span>
                                <span class="bank-card-value">YOUR NAME HERE</span>
                            </div>
                            <div>
                                <span class="bank-card-label">Valid Thru</span>
                                <span class="bank-card-value">09/31</span>
                            </div>
                        </div>
                    </div>

                    <!-- Floating balance widget -->
                    <div class="float-widget float-widget-balance">
                        <span class="fw-icon bg-success-soft"><i class="bi bi-graph-up-arrow"></i></span>
                        <div>
                            <span class="fw-label">Available balance</span>
                            <span class="fw-value"><?= CURRENCY_SYMBOL ?>1,42,860.00</span>
                        </div>
                    </div>

                    <!-- Floating transfer widget -->
                    <div class="float-widget float-widget-transfer">
                        <span class="fw-icon bg-primary-soft"><i class="bi bi-lightning-charge-fill"></i></span>
                        <div>
                            <span class="fw-label">Transfer completed</span>
                            <span class="fw-value">in 1.2 seconds</span>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==================================================================
     TRUST STRIP
=================================================================== -->
<section class="trust-strip">
    <div class="container">
        <div class="row g-4 text-center text-md-start">
            <div class="col-md-3 col-6">
                <div class="trust-item">
                    <i class="bi bi-shield-check"></i>
                    <div><strong>RBI Compliant</strong><span>Fully regulated</span></div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="trust-item">
                    <i class="bi bi-lock-fill"></i>
                    <div><strong>256-bit SSL</strong><span>Bank-grade security</span></div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="trust-item">
                    <i class="bi bi-clock-history"></i>
                    <div><strong>24&times;7 Support</strong><span>Always available</span></div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="trust-item">
                    <i class="bi bi-cash-coin"></i>
                    <div><strong>Zero Charges</strong><span>On online transfers</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==================================================================
     SERVICES
=================================================================== -->
<section class="section" id="services">
    <div class="container">
        <div class="section-head text-center">
            <span class="section-eyebrow">What we offer</span>
            <h2 class="section-title">Everything you need, in one account</h2>
            <p class="section-sub">
                From day-to-day spending to long-term savings, our services are built
                to be simple to use and transparent about cost.
            </p>
        </div>

        <div class="row g-4">
            <?php
            /* Service cards are defined as data, then rendered in a loop
               so the markup stays short and easy to change. */
            $services = [
                ['bi-piggy-bank',      'Savings Account',   'Earn attractive interest on your idle balance with zero minimum-balance penalties and free digital banking for life.'],
                ['bi-briefcase',       'Current Account',   'Built for businesses and professionals: higher transaction limits, overdraft options and detailed statements.'],
                ['bi-arrow-left-right','Instant Transfers', 'Send money to any account within the bank in real time, with an instant confirmation and a permanent receipt.'],
                ['bi-phone',           'Mobile Banking',    'A responsive dashboard that works beautifully on your phone, tablet and desktop without installing anything.'],
                ['bi-receipt',         'Smart Statements',  'Search, filter and review every transaction you have ever made, complete with reference IDs for easy tracking.'],
                ['bi-headset',         'Priority Support',  'Reach a real person any hour of the day through phone, email or the secure message centre.'],
            ];

            foreach ($services as $service):
                [$icon, $title, $text] = $service;
            ?>
                <div class="col-md-6 col-lg-4">
                    <article class="service-card reveal">
                        <span class="service-icon"><i class="bi <?= e($icon) ?>"></i></span>
                        <h3 class="service-title"><?= e($title) ?></h3>
                        <p class="service-text"><?= e($text) ?></p>
                        <a href="<?= url('services.php') ?>" class="service-link">
                            Learn more <i class="bi bi-arrow-right"></i>
                        </a>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ==================================================================
     FEATURES
=================================================================== -->
<section class="section section-tinted" id="features">
    <div class="container">
        <div class="row align-items-center g-5">

            <div class="col-lg-6">
                <span class="section-eyebrow">Why choose us</span>
                <h2 class="section-title">A dashboard that respects your time</h2>
                <p class="section-sub mb-4">
                    We removed the clutter that makes most banking portals painful.
                    What is left is a clear view of your money and the four or five
                    things you actually came to do.
                </p>

                <div class="feature-list">
                    <div class="feature-item">
                        <span class="feature-icon"><i class="bi bi-speedometer2"></i></span>
                        <div>
                            <h4>Live balance at a glance</h4>
                            <p>Your balance, deposits, withdrawals and transaction count update the moment anything changes.</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <span class="feature-icon"><i class="bi bi-funnel"></i></span>
                        <div>
                            <h4>Find any transaction fast</h4>
                            <p>Search by reference, filter by type or narrow down to a date range &mdash; results appear instantly.</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <span class="feature-icon"><i class="bi bi-arrow-repeat"></i></span>
                        <div>
                            <h4>Transfers that never half-complete</h4>
                            <p>Every transfer runs inside a database transaction, so money is either fully moved or not moved at all.</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <span class="feature-icon"><i class="bi bi-universal-access"></i></span>
                        <div>
                            <h4>Works on every screen</h4>
                            <p>The same experience on a phone during your commute and on a desktop at your office.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard preview mock-up -->
            <div class="col-lg-6">
                <div class="preview-window reveal">
                    <div class="preview-bar">
                        <span></span><span></span><span></span>
                        <em>secure &middot; <?= e(strtolower(str_replace(' ', '', BANK_NAME))) ?>.example</em>
                    </div>
                    <div class="preview-body">
                        <div class="preview-stat-row">
                            <div class="preview-stat">
                                <span class="ps-label">Total Balance</span>
                                <span class="ps-value"><?= CURRENCY_SYMBOL ?>1,42,860</span>
                                <span class="ps-trend up"><i class="bi bi-arrow-up-short"></i>12.4%</span>
                            </div>
                            <div class="preview-stat">
                                <span class="ps-label">Deposits</span>
                                <span class="ps-value"><?= CURRENCY_SYMBOL ?>2,10,000</span>
                                <span class="ps-trend up"><i class="bi bi-arrow-up-short"></i>4.1%</span>
                            </div>
                        </div>

                        <div class="preview-chart" aria-hidden="true">
                            <span style="height:38%"></span><span style="height:62%"></span>
                            <span style="height:45%"></span><span style="height:78%"></span>
                            <span style="height:55%"></span><span style="height:90%"></span>
                            <span style="height:70%"></span>
                        </div>

                        <div class="preview-list">
                            <div class="preview-row">
                                <span class="pr-icon credit"><i class="bi bi-arrow-down-left"></i></span>
                                <div class="pr-meta"><strong>Salary credited</strong><small>03 Sep, 09:14 AM</small></div>
                                <span class="pr-amt credit">+ <?= CURRENCY_SYMBOL ?>68,000</span>
                            </div>
                            <div class="preview-row">
                                <span class="pr-icon debit"><i class="bi bi-arrow-up-right"></i></span>
                                <div class="pr-meta"><strong>Transfer to Meera</strong><small>02 Sep, 06:40 PM</small></div>
                                <span class="pr-amt debit">- <?= CURRENCY_SYMBOL ?>4,500</span>
                            </div>
                            <div class="preview-row">
                                <span class="pr-icon credit"><i class="bi bi-arrow-down-left"></i></span>
                                <div class="pr-meta"><strong>Cash deposit</strong><small>01 Sep, 11:05 AM</small></div>
                                <span class="pr-amt credit">+ <?= CURRENCY_SYMBOL ?>12,000</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==================================================================
     SECURITY
=================================================================== -->
<section class="security-section" id="security">
    <div class="container">
        <div class="section-head text-center">
            <span class="section-eyebrow light">Your money is safe</span>
            <h2 class="section-title text-white">Security built into every layer</h2>
            <p class="section-sub light">
                Protecting your account is not a feature we added later &mdash; it is how
                the system was designed from the first line of code.
            </p>
        </div>

        <div class="row g-4">
            <?php
            $securityPoints = [
                ['bi-fingerprint',   'Hashed passwords',     'Passwords are never stored as text. They are hashed with bcrypt, so even we cannot read them.'],
                ['bi-shield-lock',   'Injection proof',      'Every database query uses prepared statements, which makes SQL injection attacks impossible.'],
                ['bi-code-slash',    'XSS protection',       'All content shown on screen is escaped before it is printed, so injected scripts simply cannot run.'],
                ['bi-hourglass-split','Session timeouts',    'Idle sessions end automatically after 30 minutes, and your session ID is rotated at every sign-in.'],
                ['bi-arrow-repeat',  'Atomic transfers',     'Transfers use MySQL transactions with row locking, so a failure can never leave money in limbo.'],
                ['bi-eye-slash',     'Least-privilege access','Customers can only ever reach their own accounts, checked on the server for every single request.'],
            ];

            foreach ($securityPoints as $point):
                [$icon, $title, $text] = $point;
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="security-card reveal">
                        <span class="security-icon"><i class="bi <?= e($icon) ?>"></i></span>
                        <h3><?= e($title) ?></h3>
                        <p><?= e($text) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ==================================================================
     ABOUT
=================================================================== -->
<section class="section" id="about">
    <div class="container">
        <div class="row align-items-center g-5">

            <div class="col-lg-6 order-lg-2">
                <span class="section-eyebrow">About the bank</span>
                <h2 class="section-title">Forty years of looking after people's money</h2>
                <p class="section-sub">
                    <?= e(BANK_NAME) ?> opened its first branch in 1986 with a single
                    counter and a simple promise: treat every deposit as if it were our
                    own. Four decades later we serve millions of customers across the
                    country, and that promise has not changed.
                </p>
                <p class="section-sub">
                    Today most of our customers never visit a branch at all. This
                    platform is where they open accounts, move money, and keep track of
                    their finances &mdash; which is why we hold it to the same standard
                    as any counter we have ever run.
                </p>

                <div class="about-points">
                    <div><i class="bi bi-check-circle-fill"></i> Deposits insured up to <?= CURRENCY_SYMBOL ?>5,00,000</div>
                    <div><i class="bi bi-check-circle-fill"></i> No hidden fees, ever</div>
                    <div><i class="bi bi-check-circle-fill"></i> Accounts opened in under five minutes</div>
                    <div><i class="bi bi-check-circle-fill"></i> Independently audited every quarter</div>
                </div>

                <a href="<?= url('about.php') ?>" class="btn btn-primary-glow mt-4">
                    Read our story <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>

            <div class="col-lg-6 order-lg-1">
                <div class="row g-3 about-stats">
                    <div class="col-6">
                        <div class="about-stat reveal">
                            <i class="bi bi-people-fill"></i>
                            <span class="as-value" data-count="2400000" data-suffix="+">0</span>
                            <span class="as-label">Customers served</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="about-stat reveal">
                            <i class="bi bi-building"></i>
                            <span class="as-value" data-count="1250" data-suffix="+">0</span>
                            <span class="as-label">Branches</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="about-stat reveal">
                            <i class="bi bi-calendar-check"></i>
                            <span class="as-value" data-count="40" data-suffix="+">0</span>
                            <span class="as-label">Years of service</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="about-stat reveal">
                            <i class="bi bi-award"></i>
                            <span class="as-value" data-count="36" data-suffix="">0</span>
                            <span class="as-label">Industry awards</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==================================================================
     CONTACT
=================================================================== -->
<section class="section section-tinted" id="contact">
    <div class="container">
        <div class="row g-5 align-items-center">

            <div class="col-lg-5">
                <span class="section-eyebrow">Contact us</span>
                <h2 class="section-title">We are here whenever you need us</h2>
                <p class="section-sub">
                    Call us, write to us, or drop by a branch. Whichever you choose,
                    a real person will get back to you.
                </p>

                <ul class="contact-list">
                    <li>
                        <span class="contact-icon"><i class="bi bi-telephone-fill"></i></span>
                        <div><strong>1800 200 4000</strong><small>Toll free, 24&times;7</small></div>
                    </li>
                    <li>
                        <span class="contact-icon"><i class="bi bi-envelope-fill"></i></span>
                        <div><strong>support@apextrustbank.example</strong><small>Replies within 4 hours</small></div>
                    </li>
                    <li>
                        <span class="contact-icon"><i class="bi bi-geo-alt-fill"></i></span>
                        <div><strong>Level 12, Prestige Tower</strong><small>MG Road, Bengaluru 560001</small></div>
                    </li>
                </ul>
            </div>

            <div class="col-lg-7">
                <div class="cta-panel reveal">
                    <h3>Ready to open your account?</h3>
                    <p>
                        Registration takes about three minutes. You will need your date of
                        birth and an address &mdash; that is all. Your savings account is
                        created and ready to use the moment you finish.
                    </p>
                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <a href="<?= url('register.php') ?>" class="btn btn-primary-glow btn-lg">
                            Open an Account <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                        <a href="<?= url('contact.php') ?>" class="btn btn-outline-navy btn-lg">
                            Send us a message
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
