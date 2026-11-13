# Unique Tailoring Dubai — private customer register

Prepared for review, **not activated on the live WordPress site**. GitHub Pages supports the public preview but cannot run PHP or store private records. No customer data or credentials belong in this repository.

## Delivered behavior

- Required full name, mobile/WhatsApp and area; corporate uniform enquiries also require company. Email optional.
- Common-area dropdown plus required free text for Other. Unique customers use a normalised international mobile number. Repeat enquiries keep their own dated history and contact snapshot.
- Owner sign-in; customer search, area and enquiry-status filters; private notes and New / Contacted / Quoted / Completed status.
- Customer counts by area and Business / Individual segment, with CSV exports for Excel. Business means a company is recorded. Reports count customer records, not enquiries. A shared family/company phone counts as one record; staff can correct customer areas for consistent reporting.
- Manual enquiries from Phone, WhatsApp, Walk-in, Email and Other. Direct calls and WhatsApp conversations are not captured automatically.
- Separate optional offers checkbox, initially unchecked. Each enquiry records the choice and UTC submission time. Latest customer preference is editable; contact snapshots retain the earlier choice.
- CSRF protection on staff actions, private sessions, login throttling, output escaping and authenticated CSV export with spreadsheet formula protection. Public endpoint is write-only with origin checks, input limits, honeypot and rate limiting.

## Hosting installation brief (for Jaleel / hosting administrator)

Target: existing HTTPS PHP/MySQL hosting. PHP 8.2+ and PDO MySQL required. No DNS or email changes are needed. Do not change nameservers, MX records, WordPress files or the current website as part of preparation.

1. Create a separate MySQL database and user through cPanel. Grant that user access only to the CRM database. Import `private/schema.sql` once into that new empty database. Do not import it into the WordPress database.
2. Create `/home1/uniqueun/.unique-tailoring-crm` **outside** `public_html`. Copy `private/app.php` there and copy `private/config.example.php` as `config.php`. Give only the hosting account access (directory 0700; files 0600). This directory will also hold server-side sessions. Never upload `private/`, a database dump or config.php into a public web directory.
3. Fill database credentials, a chosen owner username, a password hash and a fresh secret in that private config. Generate the hash interactively using a trusted PHP tool on the host; do not put the password in a URL, public file, shell history or chat. Hash generation in PHP: `password_hash($password, PASSWORD_DEFAULT)`. Secret generation: `bin2hex(random_bytes(32))`. Keep `secure_cookies` true and set exact HTTPS website origins. The example contains placeholders and will not run until a real hash is supplied.
4. After owner deployment approval, upload only `public/api/enquiries.php` to `public_html/api/enquiries.php` and `public/customer-register/` to `public_html/customer-register/`. Check those directories do not already hold other application files. The loader defaults to the private directory one level above DOCUMENT_ROOT; if hosting uses another layout, set `UNIQUE_CRM_PRIVATE_DIR` to its absolute path in server configuration. Never expose this setting in a public config file.
5. Sign in at `https://uniqueuniformsme.com/customer-register/` over HTTPS. Verify no customer data appears when signed out. Use a synthetic enquiry to verify saving, search, deduplication, history, status updates, area counts and CSV. Delete synthetic test records using the database administrator after review, retaining real records.
6. Connect the approved website form only when ready: on the PHP-hosted site, set `window.UNIQUE_CRM_ENDPOINT = '/api/enquiries.php';` in `customer-config.js` and deploy the matching contact.html, quote-form.js and quote-form.css together. Preview config deliberately leaves this value empty and clearly says records are not saved. Do not enable it on GitHub Pages: cross-origin access is intentionally not enabled. Do not replace the current WordPress website with these preview files without a separate approved launch.
7. Confirm the website request appears in the private register, reports the saved reference and can optionally continue to WhatsApp. Saving already sends the enquiry to the business; WhatsApp is a separate step. On failure the form shows an error and retains details for retry; the same submission ID prevents duplicate retry records. No messages are automatically sent.

## Operations

Back up the private CRM database separately and protect exported files. The current hosting has approximately 114 MB free following cache cleanup, so storage monitoring still matters. Set an appropriate customer-record retention policy before launch and review the short contact-data notice against actual business practices. Offer consent does not itself subscribe customers to a mailing service. No marketing sender or automated integration is included.

One owner account is supported in this version; no public registration. Staff should not share the owner password. Changing the password hash does not invalidate existing sessions immediately; remove the private sessions after a credential rotation if needed. Thirty minutes of inactivity expires a login.

## Validation

Run `python tests/integration.py` with PHP 8.2+ on PATH and PDO SQLite enabled (or set PHP_BINARY to an equivalent wrapper). Tests create an isolated temporary database and synthetic records and remove them afterward. Production uses MySQL; its schema still needs the hosting smoke test described above. Run `php -l` on each PHP file and `node --check public/quote-form.js`.
