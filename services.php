<?php
/**
 * =====================================================================
 * services.php  -  Banking services offered
 * =====================================================================
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth_check.php';

$pageTitle       = 'Services';
$pageDescription = 'Savings accounts, current accounts, instant transfers and more at ' . BANK_NAME . '.';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- PAGE HERO -->
<section class="page-hero">
    <div class="hero-bg" aria-hidden="true">
        <span class="hero-orb hero-orb-2"></span>
        <span class="hero-grid"></span>
    </div>
    <div class="container position-relative text-center">
        <nav class="page-crumbs" aria-label="breadcrumb">
            <a href="<?= url('index.php') ?>">Home</a> <i class="bi bi-chevron-right"></i> <span>Services</span>
        </nav>
        <h1 class="page-hero-title">Accounts and services</h1>
        <p class="page-hero-sub">
            Two account types, one clear price list, and a set of tools that cover
            almost everything people ask a bank to do.
        </p>
    </div>
</section>

<!-- ACCOUNT TYPES -->
<section class="section">
    <div class="container">
        <div class="section-head text-center">
            <span class="section-eyebrow">Choose your account</span>
            <h2 class="section-title">Which account suits you?</h2>
            <p class="section-sub">Both can be opened online in minutes, and you can hold more than one.</p>
        </div>

        <div class="row g-4 justify-content-center">

            <!-- Savings -->
            <div class="col-lg-5" id="savings">
                <div class="plan-card reveal">
                    <span class="plan-badge">Most popular</span>
                    <span class="service-icon"><i class="bi bi-piggy-bank"></i></span>
                    <h3 class="plan-title">Savings Account</h3>
                    <p class="plan-sub">For everyday money and long-term saving.</p>
                    <div class="plan-rate"><span>4.5%</span><small>per annum interest</small></div>
                    <ul class="plan-features">
                        <li><i class="bi bi-check-circle-fill"></i> No minimum balance requirement</li>
                        <li><i class="bi bi-check-circle-fill"></i> Free internet and mobile banking</li>
                        <li><i class="bi bi-check-circle-fill"></i> Interest paid quarterly</li>
                        <li><i class="bi bi-check-circle-fill"></i> Free debit card for life</li>
                        <li><i class="bi bi-check-circle-fill"></i> Unlimited online transfers</li>
                    </ul>
                    <a href="<?= url('register.php') ?>" class="btn btn-primary-glow w-100">Open a Savings Account</a>
                </div>
            </div>

            <!-- Current -->
            <div class="col-lg-5" id="current">
                <div class="plan-card reveal">
                    <span class="service-icon"><i class="bi bi-briefcase"></i></span>
                    <h3 class="plan-title">Current Account</h3>
                    <p class="plan-sub">For businesses, freelancers and professionals.</p>
                    <div class="plan-rate"><span><?= CURRENCY_SYMBOL ?>0</span><small>monthly maintenance fee</small></div>
                    <ul class="plan-features">
                        <li><i class="bi bi-check-circle-fill"></i> Higher daily transaction limits</li>
                        <li><i class="bi bi-check-circle-fill"></i> Overdraft facility on request</li>
                        <li><i class="bi bi-check-circle-fill"></i> Detailed downloadable statements</li>
                        <li><i class="bi bi-check-circle-fill"></i> Bulk transfer support</li>
                        <li><i class="bi bi-check-circle-fill"></i> Dedicated relationship manager</li>
                    </ul>
                    <a href="<?= url('register.php') ?>" class="btn btn-outline-navy w-100">Open a Current Account</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- SERVICE GRID -->
<section class="section section-tinted">
    <div class="container">
        <div class="section-head text-center">
            <span class="section-eyebrow">Included with every account</span>
            <h2 class="section-title">Tools you will actually use</h2>
        </div>

        <div class="row g-4">
            <?php
            $services = [
                ['bi-box-arrow-in-down', 'Deposits',            'Add money to any of your accounts and see the new balance immediately, with a receipt reference for your records.'],
                ['bi-box-arrow-up',      'Withdrawals',         'Take money out with a live balance check that makes an accidental overdraft impossible.'],
                ['bi-arrow-left-right',  'Instant transfers',   'Move money to any account at the bank in real time. Both sides get a matching reference number.'],
                ['bi-clock-history',     'Transaction history', 'Every movement ever recorded, searchable by reference and filterable by type and date.'],
                ['bi-person-gear',       'Profile management',  'Keep your mobile number and address current yourself, without a branch visit or a phone call.'],
                ['bi-shield-lock',       'Password control',    'Change your password whenever you like, verified against your current one first.'],
                ['bi-wallet2',           'Multiple accounts',   'Hold a savings and a current account side by side and switch between them in one click.'],
                ['bi-phone',             'Mobile ready',        'Everything works on a phone browser, with a sidebar that folds away when you do not need it.'],
                ['bi-headset',           '24x7 support',        'Phone, email or secure message &mdash; whichever suits you, staffed around the clock.'],
            ];
            foreach ($services as $service):
                [$icon, $title, $text] = $service;
            ?>
                <div class="col-md-6 col-lg-4">
                    <article class="service-card reveal">
                        <span class="service-icon"><i class="bi <?= e($icon) ?>"></i></span>
                        <h3 class="service-title"><?= e($title) ?></h3>
                        <p class="service-text"><?= $text ?></p>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- FAQ -->
<section class="section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="section-head text-center">
                    <span class="section-eyebrow">Questions</span>
                    <h2 class="section-title">Frequently asked</h2>
                </div>

                <div class="accordion faq-accordion" id="faqAccordion">
                    <?php
                    $faqs = [
                        ['How long does it take to open an account?', 'Registration takes about three minutes. Your savings account number is generated the moment you finish, and you can start using it straight away.'],
                        ['Is there a minimum balance?',               'No. Neither the savings nor the current account carries a minimum balance requirement or a penalty for falling below one.'],
                        ['Can I hold more than one account?',         'Yes. From "My Accounts" in your dashboard you can open additional savings or current accounts, each with its own account number and balance.'],
                        ['How are transfers protected?',              'Each transfer runs inside a MySQL transaction with row-level locking. Either both sides of the transfer complete, or neither does &mdash; money can never go missing in between.'],
                        ['What happens if I forget my password?',     'Contact support on 1800 200 4000 and an administrator will help you regain access after verifying your identity.'],
                    ];
                    foreach ($faqs as $i => $faq):
                        [$question, $answer] = $faq;
                        $collapseId = 'faq' . $i;
                    ?>
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button <?= $i === 0 ? '' : 'collapsed' ?>" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>"
                                        aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="<?= $collapseId ?>">
                                    <?= e($question) ?>
                                </button>
                            </h3>
                            <div id="<?= $collapseId ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>"
                                 data-bs-parent="#faqAccordion">
                                <div class="accordion-body"><?= $answer ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
