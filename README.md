# Banking Management System

A complete online banking application written in **Core PHP 8 and MySQL** — no Laravel, no
Composer, no framework of any kind. Every line is plain PHP you can read top to bottom, which is
the point: it is meant to be understood, not just run.

It has two sides. Customers register, open accounts, deposit, withdraw, transfer money to each
other and review their statement. Administrators sign in separately to monitor every customer,
account and transaction in the bank, and to export reports.

```
39 PHP files · 10,337 lines of PHP · 2,817 lines of CSS · 982 lines of JavaScript · 4 tables
```

---

## Table of contents

1. [What you need](#1-what-you-need)
2. [Installation](#2-installation)
3. [Signing in](#3-signing-in)
4. [What the application does](#4-what-the-application-does)
5. [How the security works](#5-how-the-security-works)
6. [Database design](#6-database-design)
7. [File structure](#7-file-structure)
8. [Configuration you may want to change](#8-configuration-you-may-want-to-change)
9. [Adding another administrator](#9-adding-another-administrator)
10. [Troubleshooting](#10-troubleshooting)
11. [What has and has not been tested](#11-what-has-and-has-not-been-tested)
12. [Before this goes anywhere public](#12-before-this-goes-anywhere-public)

---

## 1. What you need

XAMPP, and nothing else. It bundles Apache, PHP and MySQL together, which is all this project
uses.

| Requirement | Version | Note |
|---|---|---|
| PHP | 8.0 or newer | The code uses typed properties, `??`, `?:` and `match`-era syntax |
| MySQL / MariaDB | 5.7+ / 10.2+ | InnoDB is required, because the money transfers use real transactions |
| Apache | any | With `mod_rewrite` off; this project does not need it |
| Browser | any modern one | Bootstrap 5 and vanilla JavaScript, no build step |

Download XAMPP from [apachefriends.org](https://www.apachefriends.org/). During installation you
only need **Apache**, **MySQL** and **PHP** ticked — you can leave out FileZilla, Mercury and
Tomcat.

There is nothing to compile and nothing to install with npm or Composer. Bootstrap, Bootstrap
Icons and Chart.js load from a CDN, so the first load of each page needs an internet connection.
Everything else is local.

---

## 2. Installation

### Step 1 — Start Apache and MySQL

Open the **XAMPP Control Panel** and press **Start** next to both *Apache* and *MySQL*. Both
should turn green. If either refuses to start, jump to
[Troubleshooting](#10-troubleshooting) — a blocked port is the usual cause.

### Step 2 — Copy the project into htdocs

Put the whole `banking managment system/1` folder inside XAMPP's web root:

```
C:\xampp\htdocs\banking managment system/1\
```

Afterwards the path `C:\xampp\htdocs\banking managment system/1\index.php` must exist. If you end up
with `htdocs\banking managment system/1\banking managment system/1\index.php` — a folder inside a
folder of the same name — move things up one level.

On macOS the web root is `/Applications/XAMPP/htdocs/`, and on Linux `/opt/lampp/htdocs/`.

### Step 3 — Create the database

Open **<http://localhost/phpmyadmin>** in your browser.

1. Click **New** in the left sidebar.
2. Type `banking_management_system` as the database name.
3. Set the collation to **utf8mb4_unicode_ci**.
4. Click **Create**.

### Step 4 — Import the tables

1. With `banking_management_system` selected in the left sidebar, click the **Import** tab.
2. Click **Choose File** and pick `database/banking_management_system.sql` from the project folder.
3. Leave every other setting alone and click **Import** at the bottom.

You should see *"Import has been successfully finished"*. Click the database name and confirm four
tables are listed: `users`, `accounts`, `transactions` and `admins`. The `admins` table already
holds one row — the default administrator.

The import file is safe to run more than once. It begins with `DROP TABLE IF EXISTS`, so
re-importing gives you a clean, empty bank rather than a pile of duplicate-key errors.

### Step 5 — Check the database settings

Open `config/database.php`. The top of the file holds the connection details:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'banking_management_system');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP default root password is empty
```

On a stock XAMPP install these are already correct and you do not need to touch them. Change
`DB_PASS` only if you have set a MySQL root password yourself.

You do **not** need to set a site URL anywhere. `BASE_URL` is worked out at runtime by comparing
the script's location against Apache's document root, so the project keeps working if you rename
the folder or move it into a subdirectory.

### Step 6 — Open it

```
http://localhost/banking managment system/1/
```

The home page should appear with a navy hero section and animated statistics. If you get a
directory listing instead, you are one level too high; if you get *"Object not found"*, the folder
name in the URL does not match the folder name in `htdocs`.

---

## 3. Signing in

The administrator account is created by the SQL import:

| | Username | Password |
|---|---|---|
| **Administrator** | `admin` | `admin123` |

Sign in at **<http://localhost/banking managment system/1/admin/login.php>**. The customer login
form will not accept these credentials — the two panels are completely separate, with separate
session keys and separate database tables.

**Change this password.** It is printed in this README, in the SQL file and on the admin sign-in
page, so it is not a secret from anybody. The admin panel shows a warning banner until you change
it, under *Profile → Change password*.

There are no customer accounts to begin with. Register one through the site's own
**Register** link, then sign in with it. Registration opens the customer's first bank account
automatically, with a zero balance, so there is something to deposit into straight away.

A quick tour once you are in: register two customers in separate browser tabs, deposit some money
into the first, then use **Transfer** to send it to the second customer's account number. Both
sides will show matching entries in their transaction history, and the admin dashboard will count
the movement immediately.

---

## 4. What the application does

### The public site

A home page with a hero section, service cards, feature highlights, a security explainer and a
contact form, plus separate About, Services and Contact pages. The navigation highlights the
current page automatically. Nothing here needs a login.

### The customer panel

Registration collects full name, email, mobile, date of birth, gender, address, city, state, PIN
code, username and password. Email and username must be unique, and the form tells you which one
is taken rather than failing silently. The password is hashed immediately and the plain text is
never stored or logged.

The dashboard shows the customer's name, their accounts, the total balance across all of them, and
summary cards for total deposits, withdrawals and transaction count, alongside a chart of recent
activity and the last few transactions.

From the sidebar a customer can open an additional account (savings or current, with an
auto-generated twelve-digit number), deposit, withdraw, transfer money to any other account in the
bank, read their full transaction history, edit their profile, and change their password.

Withdrawals check the balance first and refuse to overdraw. Transfers check that the destination
account exists, refuse to send money to the account it came from, and write both sides of the
movement — *Transfer Sent* and *Transfer Received* — inside a single database transaction, so a
failure halfway through cannot create or destroy money.

Transaction history supports free-text search across reference, description and counterparty,
filtering by type, filtering by date range, sorting, and pagination.

The profile page lets a customer change their mobile number, address, city, state and PIN code.
Account number, email and username are shown but deliberately not editable.

### The admin panel

The dashboard totals customers, accounts, balance held, transactions, deposits and withdrawals,
with charts and lists of the newest customers and most recent transactions.

**Customers** lists everyone with search, sorting and pagination, a detail view showing that
customer's accounts and transactions, an edit form, and a button to activate or deactivate them. A
deactivated customer cannot sign in, and is signed out on their next request if they were already
in.

**Accounts** lists every account in the bank with its owner and balance, and can freeze or unfreeze
individual accounts.

**Transactions** is the audit log. It has search, filters and pagination — and it is read-only by
design. There is no edit button and no delete button anywhere in the panel, and no `UPDATE` or
`DELETE` statement against the `transactions` table exists anywhere in the codebase. A ledger you
can quietly edit is not a ledger.

**Deposit management** and **Withdrawal management** let an administrator post a counter
transaction against an account by account number, the way a cashier would.

**Reports** shows totals by period and exports to CSV.

---

## 5. How the security works

Every item below is implemented, not aspirational. Where a specific file and line is useful,
it is named.

**Passwords are hashed with bcrypt**, through `password_hash($password, PASSWORD_DEFAULT)`, and
checked with `password_verify()`. The database never contains a password in a readable form, so a
stolen copy of it does not hand over anyone's credentials. There is no `md5()`, `sha1()` or
`crypt()` anywhere in the project. On a successful sign-in the code also calls
`password_needs_rehash()` and quietly upgrades old hashes, so raising the cost later
upgrades everybody as they log in.

Passwords are never trimmed. A leading or trailing space is a legitimate character, and silently
removing it at registration would lock the person out at login. They are read through a dedicated
`raw_post()` helper that preserves the value exactly but still guarantees a string — because a
crafted request like `password[]=x` would otherwise hand an array to `password_verify()` and
produce a blank 500 page.

**Every database value is bound, never interpolated.** The PDO connection sets
`ATTR_EMULATE_PREPARES => false`, so these are real server-side prepared statements rather than
PHP pasting escaped strings together. The handful of places that build SQL dynamically — the
`WHERE` clauses on the listing pages — assemble only fixed literal fragments and `?` placeholders,
with the values themselves supplied separately. `ORDER BY` cannot be injected because the sort key
is forced into a hard-coded whitelist array before it is used:

```php
if (!isset($sortOptions[$sortBy])) {
    $sortBy = 'newest';          // unknown value falls back, never reaches SQL
}
// ...
ORDER BY {$sortOptions[$sortBy]}   // can only ever be one of five fixed strings
```

`LIMIT` and `OFFSET` are cast with `(int)`, and `IN (...)` lists are built from
`array_fill(0, count($ids), '?')` so the number of placeholders always matches the number of bound
values.

**Output is escaped.** Every value that reaches a page goes through `e()`, a wrapper around
`htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`. That means a customer who
registers with the name `<script>alert(1)</script>` shows up in the admin panel as harmless text.
The CSV export additionally guards against formula injection: a field starting with `=`, `+`, `-`
or `@` is prefixed with an apostrophe so Excel treats it as text rather than executing it.

**Sessions are hardened.** The cookie is `HttpOnly` so JavaScript cannot read it, `SameSite=Lax`,
and `Secure` whenever the request arrives over HTTPS. The session ID is regenerated on sign-in and
on password change, which cuts off any session that was captured earlier. Sessions idle for 30
minutes are terminated.

**Every state-changing request is POST-only and carries a CSRF token.** The token is 32 bytes from
`random_bytes()` and compared with `hash_equals()`. `require_csrf()` is the first statement in
every POST handler in the project, so a form submitted from another site is rejected before it can
touch a balance.

**Access control is checked on the server, on every request.** Customer pages call
`require_customer()` and admin pages call `require_admin()`, both before any output. Each
re-reads the account from the database rather than trusting the session, so deactivating a
customer takes effect on their very next click. Customer and admin sessions use different keys, so
one cannot be used as the other.

**Accounts are looked up by owner, not just by id.** Every customer action that touches an account
goes through `get_owned_account($pdo, $accountId, $userId)`, which binds the user id into the
`WHERE` clause. Editing the hidden account id in a form to point at somebody else's account
returns nothing and the action is refused.

**Money cannot be created, destroyed or overdrawn.** Every balance change runs inside an explicit
transaction, and the account row is locked with `SELECT ... FOR UPDATE` *before* the balance is
read, so two simultaneous withdrawals cannot both see the old balance and both succeed. Transfers
lock both accounts in ascending id order, which is what stops two opposite transfers from
deadlocking each other. Every failure path calls `rollBack()`, and the `catch` blocks are on
`Throwable` rather than `PDOException` so a non-database error still releases the locks. Balances
are `DECIMAL(15,2)`, never `FLOAT` — floating point cannot represent 0.10 exactly, and money
columns are the last place you want rounding drift.

**Validation happens server-side.** The JavaScript in `assets/js/script.js` provides live feedback
— password strength, matching confirmation, a balance warning before you submit a withdrawal —
but its opening comment says plainly that none of it is a security control. The browser belongs to
the visitor. Every check is repeated in PHP, and PHP is the one that decides.

**Errors are handled, not displayed.** `DEBUG_MODE` is `false`, so visitors get a readable message
while the detail goes to Apache's error log. Sign-in failures are deliberately vague — "invalid
username or password", never "no such user" — so the form cannot be used to discover which
accounts exist. Repeated failures are throttled: five attempts locks a customer login for five
minutes, and an admin login for ten.

---

## 6. Database design

Four tables, `utf8mb4_unicode_ci`, InnoDB throughout.

**`users`** — one row per customer: `id`, `full_name`, `email`, `mobile`, `date_of_birth`,
`gender`, `address`, `city`, `state`, `pin_code`, `username`, `password`, `status`, `created_at`.
`email` and `username` are unique. `password` holds a 60-character bcrypt hash. `status` is
`Active` or `Inactive`.

**`accounts`** — one row per bank account: `id`, `user_id`, `account_number`, `account_type`,
`balance`, `account_status`, `created_at`. `account_number` is a unique twelve digits.
`account_type` is `Savings` or `Current`. `balance` is `DECIMAL(15,2)` and defaults to `0.00`.

**`transactions`** — the ledger: `id`, `transaction_id`, `account_id`,
`related_account_number`, `transaction_type`, `amount`, `description`, `status`, `created_at`.
`transaction_id` is the unique human-readable reference. `transaction_type` is one of `Deposit`,
`Withdrawal`, `Transfer Sent` or `Transfer Received`. A transfer writes two rows, one from each
side's point of view, so each account's own history reads correctly on its own.

**`admins`** — `id`, `username`, `password`, `full_name`, `created_at`.

`accounts.user_id` and `transactions.account_id` are foreign keys with `ON DELETE CASCADE`, so
deleting a customer removes their accounts and history together instead of leaving orphaned rows
behind.

---

## 7. File structure

```
banking managment system/1/
├── index.php                     Home page
├── about.php                     About the bank
├── services.php                  Services and account types
├── contact.php                   Contact form
├── register.php                  Customer registration
├── login.php                     Customer sign-in
├── logout.php                    Customer sign-out
│
├── config/
│   └── database.php              PDO connection, constants, session settings
│
├── includes/
│   ├── functions.php             Shared helpers: e(), money(), post(), CSRF, flash messages
│   ├── auth_check.php            require_customer(), require_admin(), get_owned_account()
│   ├── header.php                Public <head> and opening markup
│   ├── navbar.php                Public navigation
│   ├── footer.php                Public footer
│   ├── customer_header.php       Customer panel shell: sidebar and top bar
│   ├── customer_footer.php
│   ├── admin_header.php          Admin panel shell
│   └── admin_footer.php
│
├── customer/
│   ├── dashboard.php             Balance, summary cards, chart, recent activity
│   ├── accounts.php              View accounts and open a new one
│   ├── deposit.php               Pay money in
│   ├── withdraw.php              Take money out, with a balance check
│   ├── transfer.php              Send money to another account
│   ├── transactions.php          History with search, filters and pagination
│   ├── profile.php               Edit contact details
│   ├── change_password.php       Change password
│   ├── verify_account.php        JSON endpoint: confirm a payee before transferring
│   └── logout.php
│
├── admin/
│   ├── login.php                 Administrator sign-in
│   ├── dashboard.php             Bank-wide statistics and charts
│   ├── customers.php             List, search, view, edit, activate or deactivate
│   ├── accounts.php              Every account in the bank
│   ├── transactions.php          Read-only audit log
│   ├── deposits.php              Counter deposit
│   ├── withdrawals.php           Counter withdrawal
│   ├── reports.php               Totals by period, CSV export
│   ├── profile.php               Admin name and password
│   ├── verify_account.php        JSON endpoint: look up an account number
│   └── logout.php
│
├── assets/
│   ├── css/style.css             All styling, in 18 commented sections
│   ├── js/script.js              All JavaScript, no build step
│   └── images/logo.svg           Logo and favicon
│
└── database/
    ├── banking_management_system.sql   Schema and the default admin row
    └── generate_hash.php               Setup utility: turn a password into a bcrypt hash
```

---

## 8. Configuration you may want to change

All of these live near the top of `config/database.php`.

| Constant | Default | What it does |
|---|---|---|
| `DB_HOST` `DB_NAME` `DB_USER` `DB_PASS` | `localhost`, `banking_management_system`, `root`, empty | Connection details |
| `BANK_NAME` | `Apex Trust Bank` | Shown in the navigation, footer and page titles |
| `BANK_TAGLINE` | `Banking, refined.` | Shown under the name |
| `CURRENCY_SYMBOL` | `₹` | Prefixes every amount. Must be a real character, not an HTML entity |
| `RECORDS_PER_PAGE` | `10` | Rows per page in every listing |
| `DEBUG_MODE` | `false` | Set `true` while developing to see real error messages |

If you change `BANK_NAME`, every page picks it up — it is never hard-coded in markup.

Turn `DEBUG_MODE` on if something is not working. It is the difference between "Something went
wrong" and the actual SQL error, which is usually the whole answer.

---

## 9. Adding another administrator

There is no "create admin" button in the panel. That is deliberate — it would be the single most
dangerous form in the project, and a college assignment does not need it. Add one directly instead.

Open **<http://localhost/banking managment system/1/database/generate_hash.php>**, type the
password you want, and it gives you both the bcrypt hash and a ready-to-paste SQL statement. Run
that in phpMyAdmin under the `banking_management_system` database.

The statement looks like this:

```sql
INSERT INTO `admins` (`username`, `password`, `full_name`)
VALUES ('newadmin', '$2y$10$...the generated hash...', 'New Administrator');
```

If you would rather do it in code, the hash is one line:

```php
<?php
// Save as hash.php in the project folder, open it, copy the output, then delete the file.
echo password_hash('YourNewPassword', PASSWORD_DEFAULT);
```

The important part either way: **the `password` column must contain a hash, never the password
itself.** Typing a plain password into that column does not create a working login — it creates an
account nobody can ever sign in to, because `password_verify()` will compare the typed password
against a string that is not a hash and correctly return false.

The same page will generate an `UPDATE` statement if you have forgotten the admin password and
need to reset it. That is the intended recovery route: the password cannot be read back out of the
database by anyone, including you, which is exactly the property you want.

`generate_hash.php` only answers requests from the machine it runs on, and it should be deleted
before the project goes anywhere public.

---

## 10. Troubleshooting

**Apache will not start, or stops immediately.** Something else is using port 80 — usually IIS,
Skype, or in a lot of cases VMware. In the XAMPP Control Panel click *Config* next to Apache,
open `httpd.conf`, change `Listen 80` to `Listen 8080` and `ServerName localhost:80` to
`localhost:8080`, then restart. The site then lives at
`http://localhost:8080/banking managment system/1/`, and `BASE_URL` adjusts on its own.

**MySQL will not start.** Port 3306 is taken, usually by another MySQL or MariaDB service
installed separately. Stop that service in Windows *Services*, or change XAMPP's port in
`my.ini` and set the same port in `config/database.php` as `DB_HOST` = `localhost:3307`.

**"Database connection failed".** MySQL is not running, or the database name is misspelled, or you
have set a root password that `DB_PASS` does not know about. Confirm the database exists in
phpMyAdmin first, then check the four constants in `config/database.php`.

**"Table 'banking_management_system.users' doesn't exist".** The database was created but the SQL
file was never imported, or it was imported while a different database was selected. Redo
[Step 4](#step-4--import-the-tables) and make sure `banking_management_system` is highlighted in
the left sidebar before you import.

**The page loads but has no styling.** The browser cannot reach `assets/css/style.css`. Nearly
always this means the folder is nested one level too deep in `htdocs`. Open the page source, click
the stylesheet link, and see what path it is actually asking for.

**Everything works but there are no fonts, icons or charts.** Bootstrap, Bootstrap Icons and
Chart.js come from a CDN, so those need an internet connection. The application itself works
offline; it just looks plainer.

**I cannot sign in as admin.** Use `admin` / `admin123` on `admin/login.php`, not on the customer
login page. If it still fails, five wrong attempts triggers a ten-minute lockout — wait it out, or
clear it by restarting Apache. If you have changed the password and forgotten it, reset it with
`database/generate_hash.php` as described above.

**A blank white page.** A PHP error with display turned off. Set `DEBUG_MODE` to `true` in
`config/database.php` and reload; the real message will appear. Failing that, read
`C:\xampp\apache\logs\error.log` from the bottom.

**Amounts show as `&#8377;12,500.00`.** `CURRENCY_SYMBOL` has been changed to an HTML entity. It
must be the literal character `₹`, saved as UTF-8, because the value is HTML-escaped in some
places and read by JavaScript as text in others.

**"Insufficient balance" on a withdrawal that looks affordable.** The check is against the account
you selected, not the total across all your accounts. Check the account dropdown at the top of the
form.

---

## 11. What has and has not been tested

Being straight about this, because it affects what you should check first.

**Verified statically, in detail.** All 39 PHP files parse as balanced code — braces, parentheses,
brackets and every `if:`/`endif` pair — checked with a tokeniser that tracks strings, heredocs and
comments so that a brace inside a string is not miscounted. All 112 `require`/`include` targets
resolve to files that exist. Every internal link built with `url()` points at a real file. Every
custom CSS class used in the markup has a rule in `style.css`, and every `data-` hook in the markup
has a handler in `script.js`. Every dynamically built SQL fragment was traced back to its source
and confirmed to contain only fixed literals and `?` placeholders. No `UPDATE` or `DELETE` against
the `transactions` table exists anywhere. The four tables match the specified column lists exactly.
The bcrypt hash shipped in the SQL file was tested against a validated bcrypt implementation and
does verify against `admin123`, so the default login will work.

**Not tested.** The application has never been executed. The environment this was written in has
no PHP binary and no MySQL server, and neither could be installed — the package mirrors are
unreachable from it. So `php -l` was never run, no page has ever been rendered, no query has ever
touched a real database, and the concurrency behaviour of the transfer locking has never been
exercised by two simultaneous requests. Static analysis catches structural mistakes; it cannot
catch a logic error that only shows up at runtime.

**So start here.** Register a customer, deposit, withdraw more than the balance and confirm it is
refused, transfer between two customers and confirm both histories agree and the totals still add
up, then check the admin dashboard reflects all of it. That sequence exercises the parts where a
runtime bug would matter most. If something breaks, set `DEBUG_MODE` to `true` first — it turns a
blank page into an answer.

---

## 12. Before this goes anywhere public

This is a learning project. It is careful about the things it does — the SQL is properly
parameterised, the passwords are properly hashed, the money moves inside real transactions — but a
real bank needs a great deal that is deliberately out of scope here: HTTPS everywhere,
two-factor authentication, an append-only audit trail on a separate system, rate limiting at the
network edge, key management, backups, monitoring, and an accounting model built on double-entry
journals rather than a mutable balance column.

If you do put it on a server, at minimum: delete `database/generate_hash.php`, change the admin
password, create a MySQL user with only the privileges this application needs instead of using
`root`, put the whole thing behind HTTPS, keep `DEBUG_MODE` off, and move `config/database.php`
outside the web root.

Use it to learn how the pieces fit together. Do not use it to hold anybody's actual money.

