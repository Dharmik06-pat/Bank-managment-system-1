<?php
/**
 * =====================================================================
 * database/generate_hash.php  -  bcrypt hash generator (setup utility)
 * ---------------------------------------------------------------------
 * A small maintenance page. Type a password, get back the bcrypt hash
 * that belongs in the `password` column, plus a ready-to-paste SQL
 * statement for phpMyAdmin.
 *
 * Two things you need it for:
 *   1. Adding a second administrator. There is deliberately no
 *      "create admin" button in the admin panel, so a new row goes in
 *      through phpMyAdmin - and the password has to be a real hash,
 *      not plain text, or password_verify() will never match it.
 *   2. Resetting a forgotten admin password.
 *
 * WHY THIS FILE IS NOT PART OF THE APPLICATION
 * ---------------------------------------------------------------------
 * It is deliberately standalone: no config/database.php, no session,
 * no database connection. That matters, because the moment you actually
 * need this page is the moment you are locked out of the admin panel or
 * the database is not set up yet. A tool for fixing a broken install
 * must not depend on the install working.
 *
 * DELETE THIS FILE BEFORE PUTTING THE PROJECT ANYWHERE PUBLIC.
 * It is fine on your own machine, which is why it only answers requests
 * coming from localhost (see the guard below). But it has no login of
 * its own, and a page that will grind through bcrypt on demand for any
 * visitor is a free way to tie up your CPU.
 * =====================================================================
 */

declare(strict_types=1);

/* =====================================================================
 | LOCALHOST ONLY
 |
 | bcrypt is slow on purpose - that is the entire point of it - which
 | makes an open, unauthenticated hashing endpoint a gift to anyone who
 | wants to keep your server busy. There is no legitimate reason to
 | reach this page from another machine, so refuse everything else.
 * ================================================================== */
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal  = in_array($clientIp, ['127.0.0.1', '::1', '0:0:0:0:0:0:0:1'], true);

if (!$isLocal) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Forbidden.\n\n";
    echo "This setup utility only answers requests from the same machine.\n";
    echo "If you are seeing this on a live site, delete database/generate_hash.php.\n";
    exit;
}

/* =====================================================================
 | GENERATE
 * ================================================================== */
$password = '';
$hash     = '';
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // No trim: a space is a legitimate password character, and quietly
    // removing it here would produce a hash that never matches what the
    // person types on the sign-in page.
    $raw      = $_POST['password'] ?? '';
    $password = is_string($raw) ? $raw : '';

    if ($password === '') {
        $error = 'Type a password first.';
    } elseif (strlen($password) > 72) {
        // bcrypt only reads the first 72 bytes. Rather than silently
        // truncating - which would let two different passwords share a
        // hash - say so plainly.
        $error = 'bcrypt only uses the first 72 bytes of a password. Choose a shorter one.';
    } else {
        // PASSWORD_DEFAULT, exactly as the rest of the project uses it.
        // The salt is generated for you, so the same password gives a
        // different hash every time you reload - all of them valid.
        $hash = password_hash($password, PASSWORD_DEFAULT);
    }
}

/** Escape for HTML output. */
function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Password Hash Generator &mdash; Banking Management System</title>
<style>
    /* Inlined on purpose: this page must render even if assets, the
       database or the rest of the project are broken. */
    :root { color-scheme: light; }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        padding: 40px 20px;
        background: #f4f6fb;
        color: #0f172a;
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        line-height: 1.6;
    }

    .wrap { max-width: 760px; margin: 0 auto; }

    .card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 28px;
        margin-bottom: 20px;
        box-shadow: 0 4px 24px rgba(15, 23, 42, .05);
    }

    h1 {
        margin: 0 0 6px;
        font-size: 1.5rem;
        letter-spacing: -.02em;
    }

    h2 {
        margin: 0 0 12px;
        font-size: 1.05rem;
        color: #1d4ed8;
    }

    .sub { margin: 0 0 24px; color: #475569; }

    label {
        display: block;
        font-weight: 600;
        font-size: .9rem;
        margin-bottom: 6px;
    }

    input[type="text"] {
        width: 100%;
        padding: 12px 14px;
        font: inherit;
        font-family: ui-monospace, 'Consolas', monospace;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        background: #f8fafc;
    }

    input[type="text"]:focus {
        outline: none;
        border-color: #1d4ed8;
        background: #fff;
        box-shadow: 0 0 0 4px rgba(29, 78, 216, .12);
    }

    button {
        margin-top: 16px;
        padding: 12px 22px;
        font: inherit;
        font-weight: 600;
        color: #fff;
        background: linear-gradient(135deg, #1d4ed8, #0ea5e9);
        border: 0;
        border-radius: 10px;
        cursor: pointer;
    }

    button:hover { filter: brightness(1.08); }

    .out {
        display: block;
        width: 100%;
        margin: 0;
        padding: 14px;
        font-family: ui-monospace, 'Consolas', monospace;
        font-size: .85rem;
        word-break: break-all;
        white-space: pre-wrap;
        background: #0b1c3d;
        color: #cfe0ff;
        border: 0;
        border-radius: 10px;
    }

    .hint { margin: 10px 0 0; font-size: .85rem; color: #475569; }

    .note {
        padding: 16px 18px;
        border-radius: 12px;
        border-left: 4px solid;
        margin-bottom: 20px;
        font-size: .92rem;
    }

    .note p:last-child { margin-bottom: 0; }
    .note-warn  { background: #fffbeb; border-color: #f59e0b; }
    .note-error { background: #fef2f2; border-color: #ef4444; color: #991b1b; }
    .note-info  { background: #eff6ff; border-color: #1d4ed8; }

    code {
        padding: 2px 6px;
        background: #eef2f7;
        border-radius: 5px;
        font-family: ui-monospace, 'Consolas', monospace;
        font-size: .88em;
    }

    ol { padding-left: 22px; }
    li { margin-bottom: 8px; }

    a { color: #1d4ed8; }

    .foot {
        text-align: center;
        font-size: .85rem;
        color: #94a3b8;
    }
</style>
</head>
<body>
<div class="wrap">

    <div class="note note-warn">
        <p><strong>Setup utility &mdash; delete this file before going live.</strong></p>
        <p>
            It has no login of its own. It only answers requests from this machine,
            but on a real server <code>database/generate_hash.php</code> should not
            exist at all.
        </p>
    </div>

    <div class="card">
        <h1>Password hash generator</h1>
        <p class="sub">
            Turns a password into the bcrypt hash that goes in the
            <code>password</code> column. Same function the application itself
            uses: <code>password_hash($password, PASSWORD_DEFAULT)</code>.
        </p>

        <?php if ($error !== ''): ?>
            <div class="note note-error"><p><?= h($error) ?></p></div>
        <?php endif; ?>

        <form method="post" action="">
            <label for="password">Password to hash</label>
            <input type="text" id="password" name="password" autocomplete="off"
                   spellcheck="false" placeholder="e.g. MyStrongPass123"
                   value="<?= h($password) ?>" autofocus>
            <p class="hint">
                Shown as plain text on purpose &mdash; you need to be able to read
                back exactly what you typed.
            </p>
            <button type="submit">Generate hash</button>
        </form>
    </div>

    <?php if ($hash !== ''): ?>
        <div class="card">
            <h2>Your hash</h2>
            <textarea class="out" rows="2" readonly onclick="this.select()"><?= h($hash) ?></textarea>
            <p class="hint">
                60 characters, starting <code>$2y$</code>. Click to select all.
                Reload and you will get a different hash for the same password &mdash;
                that is the random salt doing its job, and every one of them verifies.
            </p>
        </div>

        <div class="card">
            <h2>Add a new administrator</h2>
            <p class="hint" style="margin-top:0">
                Paste this into phpMyAdmin &rarr; <code>banking_management_system</code>
                &rarr; SQL. Change the username and full name first.
            </p>
            <textarea class="out" rows="4" readonly onclick="this.select()">INSERT INTO `admins` (`username`, `password`, `full_name`)
VALUES ('newadmin', '<?= h($hash) ?>', 'New Administrator');</textarea>
        </div>

        <div class="card">
            <h2>Reset the existing admin password</h2>
            <textarea class="out" rows="3" readonly onclick="this.select()">UPDATE `admins` SET `password` = '<?= h($hash) ?>'
WHERE `username` = 'admin';</textarea>
        </div>

        <div class="card">
            <h2>Reset a customer's password</h2>
            <textarea class="out" rows="3" readonly onclick="this.select()">UPDATE `users` SET `password` = '<?= h($hash) ?>'
WHERE `email` = 'customer@example.com';</textarea>
            <p class="hint">
                Customers can change their own password from
                <code>customer/change_password.php</code>, so you should rarely
                need this &mdash; it is here for when someone is locked out.
            </p>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Why hashes and not passwords</h2>
        <p class="hint" style="margin-top:0">
            The database never stores what anyone typed. It stores a bcrypt hash,
            which cannot be reversed, and <code>password_verify()</code> checks a
            typed password against it. So a stolen copy of the database does not
            hand over anyone's password &mdash; and it also means nobody, including
            you, can look up what a password was. Resetting it is the only option.
        </p>
        <p class="hint">
            The hash carries its own cost setting, so
            <code>password_needs_rehash()</code> can spot old hashes and the
            application quietly upgrades them at the next successful sign-in.
        </p>
    </div>

    <p class="foot">
        Banking Management System &mdash; setup utility.
        <a href="../index.php">Back to the site</a>
    </p>

</div>
</body>
</html>
