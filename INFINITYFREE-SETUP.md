# Nerasky InfinityFree trial setup

This package combines the Nerasky marketing landing page, marketplace, Hiring
portal, and private Nerasky Admin portal on one domain and one MySQL database.
The Admin login is at `/admin/login.php`; it is not linked in public navigation.

## 1. Create the hosting site and database

1. Create your InfinityFree hosting account and choose the domain or free
   subdomain you want to use.
2. The chosen domain is `nerasky.wuaze.com`. If InfinityFree says activation
   or propagation may take up to 72 hours, wait until the control panel marks it
   active and the domain opens over HTTPS before continuing with Supabase or
   enabling signups.
3. In the hosting control panel, create a MySQL database. Copy its exact host,
   database name, username, and password; the database name and username
   normally include the account prefix.
4. Select PHP 8.1 or newer if the control panel offers a PHP-version selector,
   and confirm `PDO MySQL`, `mbstring`, and `fileinfo` are enabled.

## 2. Upload the package

Upload the **contents** of this package to the domain's web root (usually
`htdocs`), not to a nested `nerasky-infinityfree` folder. The package ZIP can be
uploaded in File Manager and extracted there. Make sure the hidden `.htaccess`
files are uploaded too.

The landing page opens at the domain root. The marketplace is available at
`/index.php`, Hiring is at `/hiring/` (also `/hiring/careers.php`), and Admin is
at `/admin/login.php`.

The ZIP contains application code and public brand assets only. It intentionally
does not include existing uploads, local database backups, or demo accounts;
the upload folder starts empty.

## 3. Configure MySQL, Supabase, and PayMongo

Edit `includes/config.php` in File Manager before opening the installer:

- The package uses blank placeholders for the database password and PayMongo
  credentials. Set them in the private server copy using the values from your
  hosting panel and PayMongo dashboard. Never publish or re-upload a copy that
  contains real credentials.
- Replace `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` with the exact MySQL
  values from the hosting control panel. Keep `DB_PORT` as `3306` unless the
  panel says otherwise.
- `SUPABASE_REDIRECT_URL` is preconfigured for
  `https://nerasky.wuaze.com/supabase_callback.php`. Change it if you later use
  a different domain.
- Keep the existing `SUPABASE_URL` and publishable/anon key only if they belong
  to the Supabase project you intend to use. Never put a Supabase secret or
  `service_role` key in this file.
- Confirm `PAYMONGO_SECRET_KEY` and `PAYMONGO_PUBLIC_KEY` are a matching pair
  (both test keys or both live keys), and that `PAYMONGO_WEBHOOK_SECRET` is the
  signing secret for the webhook you create below. These values are server
  configuration; never put the secret key in browser code or share it publicly.
- For a trial, use PayMongo test keys first. Enable QR Ph for the PayMongo
  account and create a webhook pointing to
  `https://nerasky.wuaze.com/paymongo_webhook.php`. Select `payment.paid` and
  `payment.failed`, save it, then put that endpoint's signing secret in
  `PAYMONGO_WEBHOOK_SECRET`. The app uses the webhook and checks Payment Intent
  status server-side before crediting a wallet, activating Pro, or starting an
  order.
- PayMongo QR Ph is available for order payments, Wallet top-ups, and Pro
  purchases, with automatic server-side confirmation. Manual GCash is also
  available for order payments and Wallet cash-ins when the GCash account and
  QR image are configured; an admin must verify the actual received transaction
  before an order starts or wallet funds are credited. GCash is not used for Pro.
  PayMongo requires a public HTTPS site and outbound HTTPS requests from PHP
  (including cURL); test those requirements on the actual host before accepting
  payments.
- GCash account details and the QR image are intentionally excluded from source
  control. Configure them only in the private server copy; manual GCash payments
  stay disabled until the account details and QR image are present.
- Replace `ADMIN_SETUP_TOKEN` with a private random value you create locally.
  In Windows PowerShell, one way to create one is:
  `$b = New-Object byte[] 32; $r = [Security.Cryptography.RandomNumberGenerator]::Create(); $r.GetBytes($b); [Convert]::ToBase64String($b); $r.Dispose()`
  Use the generated value (at least 32 characters) both in the config and the
  install form.
  You will enter it once in the installer to authorize creation of the first
  super-admin account. Do not share the token; delete the installer immediately
  after setup.

In Supabase, open **Authentication → URL Configuration**:

1. After the domain is active and HTTPS works, set **Site URL** to
   `https://nerasky.wuaze.com` (no trailing slash).
2. Add
   `https://nerasky.wuaze.com/supabase_callback.php` to **Redirect URLs**.
3. Configure the email provider/SMTP settings you need for signup verification.
   Email delivery is handled by Supabase Auth, not this PHP host.

## 4. Install the database tables

Open `https://nerasky.wuaze.com/install.php` and choose **Install Nerasky**. The
installer connects to the database you created in the control panel; it does
not try to create a database and does not install demo accounts or sample data.
On that page, enter the first admin's name, email, a strong password, and the
private setup token. The password is stored hashed and is not shown afterward.
Share the admin URL, email, and password with your professor privately.

After it reports success, change `ADMIN_SETUP_TOKEN` to an empty string and
delete `install.php` from File Manager immediately.
The installer also creates the required upload folders and protects them from
PHP script execution.

## 5. Test both sites

- Open the domain root and confirm the landing page and favicon load.
- Open `/index.php`, register a test account, and test the Supabase verification
  email before relying on sign-in.
- Open `/hiring/`; check that Hiring styles load, its links stay
  under `/hiring/`, and the marketplace link returns to the same domain.
- Open `/admin/login.php`, sign in with the first-admin credentials, enable 2FA,
  and confirm the payments queue appears.
- Test an application upload with a non-sensitive sample file, then verify the
  private status link.
- Confirm `/includes/config.php`, `/hiring/config.php`, and PHP files inside
  `/uploads/` cannot be downloaded or executed.

## Trial limitations and safety

- This is a prepared package, not a live deployment. The hosting account,
  domain, database, HTTPS, and Supabase allowlist must be configured by you.
- Registration uses identity and age checks. Do not collect real identity
  documents on an unverified free-hosting trial; review the provider and
  document-access settings before enabling public production sign-ups.
- Free hosting limits and policies can affect PHP upload sizes, outbound
  connections, SMTP, and email delivery. Test each flow on the actual account;
  do not assume local XAMPP behavior guarantees hosting support.
- Security-change email alerts remain unconfigured until you add an SMTP
  provider's details in `includes/config.php`. Do not commit or share those
  credentials.
- Incoming payments can use PayMongo QR Ph or manually verified GCash; seller
  withdrawals remain manual. Confirm a complete test-mode PayMongo payment and
  webhook delivery on the hosted site, and verify one GCash proof/cash-in through
  the admin queue, before accepting real payments. A successful config-format
  check alone does not prove that credentials, QR Ph access, webhook, GCash
  receipt verification, or hosting network support is working.
- Keep Admin credentials private. Give your professor a dedicated password
  through a private channel, require 2FA, and rotate credentials after the
  demonstration. The `/admin` path is not a substitute for authentication.
- Do not use real identity documents, parental-consent files, payment proofs,
  or personal applicant data on an unverified free-hosting trial.
- Keep backups of the database and uploaded files. Free trial hosting should not
  be treated as production storage.
