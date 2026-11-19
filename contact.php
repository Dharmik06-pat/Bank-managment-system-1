<?php
/**
 * =====================================================================
 * contact.php  -  Contact page with a feedback form
 * ---------------------------------------------------------------------
 * Messages are validated server-side and stored in a session flash
 * (no message table; a real deployment would persist these).
 * =====================================================================
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth_check.php';

$pageTitle       = 'Contact Us';
$pageDescription = 'Reach ' . BANK_NAME . ' by phone, email or the contact form.';

/* ----- Contact form handling ------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('contact.php');

    $senderName  = post('sender_name');
    $senderEmail = post('sender_email');
    $subject     = post('subject');
    $message     = post('message');

    $errors = [];

    if ($senderName === '' || mb_strlen($senderName) > 100) {
        $errors[] = 'Please enter your name (max 100 characters).';
    }
    if (!is_valid_email($senderEmail)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($subject === '' || mb_strlen($subject) > 150) {
        $errors[] = 'Please enter a subject (max 150 characters).';
    }
    if ($message === '' || mb_strlen($message) > 2000) {
        $errors[] = 'Please write a message of up to 2000 characters.';
    }

    if ($errors) {
        set_old($_POST);
        foreach ($errors as $error) {
            set_flash('danger', $error);
        }
    } else {
        // Nothing to persist in this demo build - the message is
        // "sent". A production version would email support and/or
        // insert into a messages table.
        set_flash('success', 'Thank you, ' . $senderName . '! Your message has been received. Our team replies within four working hours.');
        clear_old();
    }

    redirect('contact.php');
}

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
            <a href="<?= url('index.php') ?>">Home</a> <i class="bi bi-chevron-right"></i> <span>Contact</span>
        </nav>
        <h1 class="page-hero-title">Talk to a real person</h1>
        <p class="page-hero-sub">
            Phone, email or the form below &mdash; whichever is easiest. We answer fast.
        </p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="row g-5">

            <!-- Contact details -->
            <div class="col-lg-4">
                <h2 class="section-title fs-3 mb-4">Reach us directly</h2>

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
                    <li>
                        <span class="contact-icon"><i class="bi bi-clock-fill"></i></span>
                        <div><strong>Mon &ndash; Sat, 9 AM &ndash; 6 PM</strong><small>Phone support always on</small></div>
                    </li>
                </ul>

                <div class="branch-card">
                    <h5 class="mb-1"><i class="bi bi-building me-1"></i>Head Office</h5>
                    <p class="mb-0 small text-muted">
                        <?= e(BANK_NAME) ?>, Level 12, Prestige Tower, MG Road,
                        Bengaluru, Karnataka 560001. Nearest metro: MG Road.
                    </p>
                </div>
            </div>

            <!-- Contact form -->
            <div class="col-lg-8">
                <div class="form-card reveal">
                    <h3 class="form-card-title">Send us a message</h3>
                    <p class="form-card-sub">
                        Tell us what you need. The more specific, the faster we can help.
                    </p>

                    <form method="post" action="<?= url('contact.php') ?>" novalidate>
                        <?= csrf_field() ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="sender_name" class="form-label">Your name <span class="req">*</span></label>
                                <input type="text" class="form-control" id="sender_name" name="sender_name"
                                       value="<?= old('sender_name') ?>" maxlength="100" required
                                       placeholder="Full name">
                            </div>
                            <div class="col-md-6">
                                <label for="sender_email" class="form-label">Email address <span class="req">*</span></label>
                                <input type="email" class="form-control" id="sender_email" name="sender_email"
                                       value="<?= old('sender_email') ?>" maxlength="150" required
                                       placeholder="you@example.com">
                            </div>
                            <div class="col-12">
                                <label for="subject" class="form-label">Subject <span class="req">*</span></label>
                                <input type="text" class="form-control" id="subject" name="subject"
                                       value="<?= old('subject') ?>" maxlength="150" required
                                       placeholder="How can we help?">
                            </div>
                            <div class="col-12">
                                <label for="message" class="form-label">Message <span class="req">*</span></label>
                                <textarea class="form-control" id="message" name="message" rows="6"
                                          maxlength="2000" required
                                          placeholder="Write your message here..."><?= old('message') ?></textarea>
                            </div>
                            <div class="col-12 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <p class="small text-muted mb-0"><span class="req">*</span> Required fields</p>
                                <button type="submit" class="btn btn-primary-glow">
                                    <i class="bi bi-send me-1"></i> Send message
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
