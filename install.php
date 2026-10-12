<?php
declare(strict_types=1);
/* Nerasky one-time installer — creates marketplace tables and the first admin. */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$errors  = [];
$done    = false;
$already = false;
$adminExists = false;
$adminCreated = false;
$connErr = '';

/* ------------------------- database schema ------------------------- */
$schema = [
    'CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM("user","admin") NOT NULL DEFAULT "user",
        account_status ENUM("pending","verified","rejected") NOT NULL DEFAULT "pending",
        verification_file VARCHAR(255) NULL,
        is_minor TINYINT(1) NOT NULL DEFAULT 0,
        consent_file VARCHAR(255) NULL,
        consent_status ENUM("none","submitted","approved","rejected") NOT NULL DEFAULT "none",
        bio TEXT NULL,
        wallet DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        premium_until DATETIME NULL,
        avatar_file VARCHAR(255) NULL,
        date_of_birth DATE NULL,
        id_type VARCHAR(20) NOT NULL DEFAULT "school",
        email_verified TINYINT(1) NOT NULL DEFAULT 0,
        email_verified_at DATETIME NULL,
        firebase_uid VARCHAR(128) NULL,
        supabase_uid VARCHAR(128) NULL,
        google_verified TINYINT(1) NOT NULL DEFAULT 0,
        profile_frame VARCHAR(30) NOT NULL DEFAULT "none",
        profile_accent VARCHAR(20) NOT NULL DEFAULT "indigo",
        profile_layout VARCHAR(20) NOT NULL DEFAULT "classic",
        profile_progress_style VARCHAR(20) NOT NULL DEFAULT "gradient",
        profile_stat_visibility TEXT NULL,
        profile_status VARCHAR(20) NOT NULL DEFAULT "online",
        profile_note VARCHAR(160) NULL,
        profile_note_theme VARCHAR(20) NOT NULL DEFAULT "indigo",
        chat_theme VARCHAR(20) NOT NULL DEFAULT "indigo",
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

    'CREATE TABLE IF NOT EXISTS admin_staff (
        user_id INT PRIMARY KEY,
        staff_role VARCHAR(30) NOT NULL DEFAULT "moderator",
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    'CREATE TABLE IF NOT EXISTS admin_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL,
        contact_email VARCHAR(190) NOT NULL,
        date_of_birth DATE NULL,
        password_hash VARCHAR(255) NULL,
        phone VARCHAR(40) NOT NULL,
        location VARCHAR(120) NOT NULL,
        education TEXT NOT NULL,
        work_history TEXT NOT NULL,
        skills TEXT NOT NULL,
        availability VARCHAR(30) NOT NULL,
        reference_name VARCHAR(120) NOT NULL,
        reference_contact VARCHAR(190) NOT NULL,
        resume_file VARCHAR(255) NOT NULL,
        cover_letter TEXT NOT NULL,
        requested_role VARCHAR(30) NOT NULL,
        experience TEXT NOT NULL,
        motivation TEXT NOT NULL,
        verification_file VARCHAR(255) NOT NULL,
        applicant_declaration TINYINT(1) NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT "pending",
        reviewer_notes TEXT NULL,
        reviewed_by INT NULL,
        reviewed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY application_status (status),
        KEY application_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

    'CREATE TABLE IF NOT EXISTS services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        seller_id INT NOT NULL,
        category VARCHAR(40) NOT NULL,
        title VARCHAR(140) NOT NULL,
        description TEXT NOT NULL,
        price DECIMAL(12,2) NOT NULL,
        delivery_days INT NOT NULL DEFAULT 3,
        status ENUM("pending","active","rejected") NOT NULL DEFAULT "pending",
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY seller (seller_id),
        KEY category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

    'CREATE TABLE IF NOT EXISTS service_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY service_images_order (service_id, sort_order, id),
        CONSTRAINT service_images_service_fk FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

    'CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_id INT NOT NULL,
        buyer_id INT NOT NULL,
        seller_id INT NOT NULL,
        price DECIMAL(12,2) NOT NULL,
        platform_fee DECIMAL(12,2) NOT NULL,
        seller_payout DECIMAL(12,2) NOT NULL,
        notes TEXT NULL,
        status ENUM("awaiting_payment","in_progress","delivered","completed","cancelled") NOT NULL DEFAULT "awaiting_payment",
        deliverable_file VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME NULL,
        KEY buyer (buyer_id),
        KEY seller (seller_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

    'CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        payer_id INT NOT NULL,
        provider ENUM("GCash","Maya") NOT NULL,
        reference_number VARCHAR(60) NOT NULL,
        screenshot_file VARCHAR(255) NOT NULL,
        status ENUM("pending","verified","rejected") NOT NULL DEFAULT "pending",
        reviewed_by INT NULL,
        reviewed_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY order_id (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

    'CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        sender_id INT NOT NULL,
        body TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY order_id (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

    'CREATE TABLE IF NOT EXISTS withdrawals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        method ENUM("GCash","Maya") NOT NULL,
        account_name VARCHAR(120) NOT NULL,
        account_number VARCHAR(60) NOT NULL,
        status ENUM("pending","paid","rejected") NOT NULL DEFAULT "pending",
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

    'CREATE TABLE IF NOT EXISTS wallet_transactions (
       id INT AUTO_INCREMENT PRIMARY KEY,
       user_id INT NOT NULL,
       type VARCHAR(30) NOT NULL,
       amount DECIMAL(12,2) NOT NULL,
       direction ENUM("credit","debit") NOT NULL,
       description VARCHAR(255) NOT NULL,
       order_id INT NULL,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       KEY user_id (user_id),
       KEY order_id (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

    'CREATE TABLE IF NOT EXISTS cash_in_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        provider ENUM("GCash","Maya") NOT NULL,
        reference_number VARCHAR(60) NOT NULL,
        screenshot_file VARCHAR(255) NOT NULL,
        status ENUM("pending","approved","rejected") NOT NULL DEFAULT "pending",
        reviewed_by INT NULL,
        reviewed_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY user_id (user_id),
        KEY status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

    'CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL UNIQUE,
        reviewer_id INT NOT NULL,
        reviewee_id INT NOT NULL,
        rating TINYINT NOT NULL,
        comment TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY reviewee (reviewee_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

    'CREATE TABLE IF NOT EXISTS subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        plan VARCHAR(30) NOT NULL DEFAULT "pro",
        amount DECIMAL(10,2) NOT NULL,
        source ENUM("wallet","paymongo") NOT NULL DEFAULT "wallet",
        starts_at DATETIME NOT NULL,
        ends_at DATETIME NOT NULL,
        status ENUM("active","cancelled","expired") NOT NULL DEFAULT "active",
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

    'CREATE TABLE IF NOT EXISTS paymongo_checkouts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        purpose ENUM("order","wallet","pro") NOT NULL,
        order_id INT NULL,
        amount_centavos INT UNSIGNED NOT NULL,
        payment_intent_id VARCHAR(80) NULL UNIQUE,
        qr_image MEDIUMTEXT NULL,
        status VARCHAR(24) NOT NULL DEFAULT "pending",
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        paid_at DATETIME NULL,
        KEY checkout_user_status (user_id, status),
        KEY checkout_order (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

    'CREATE TABLE IF NOT EXISTS paymongo_webhook_events (
        event_id VARCHAR(120) PRIMARY KEY,
        event_type VARCHAR(80) NOT NULL,
        received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

    'CREATE TABLE IF NOT EXISTS conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user1_id INT NOT NULL,
        user2_id INT NOT NULL,
        last_message_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY pair (user1_id, user2_id),
        KEY user2 (user2_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

    'CREATE TABLE IF NOT EXISTS conversation_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        sender_id INT NOT NULL,
        body TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY conversation_id (conversation_id),
        KEY sender (sender_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

    'CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        kind VARCHAR(30) NOT NULL,
        text VARCHAR(500) NOT NULL,
        link VARCHAR(190) NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY user (user_id, is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

    'CREATE TABLE IF NOT EXISTS user_email_verifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY verify_user (user_id),
        KEY verify_expiry (expires_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

    'CREATE TABLE IF NOT EXISTS security_throttle (
        key_hash CHAR(64) NOT NULL PRIMARY KEY,
        attempts INT UNSIGNED NOT NULL DEFAULT 0,
        window_expires INT UNSIGNED NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
];

function install_pdo(string $dbname = null): PDO
{
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4'
        . ($dbname !== null ? ';dbname=' . $dbname : '');
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/* InfinityFree databases must be created in the control panel first. */
try {
    $probe = install_pdo(DB_NAME);
    $already = (bool) $probe->query("SHOW TABLES LIKE 'users'")->fetchColumn();
    if ($already) {
        $adminExists = (bool) $probe->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    }
} catch (PDOException $e) {
    $connErr = $e->getMessage();
}

require_once __DIR__ . '/includes/upgrade.php';
/* ------------------------- install action ------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        exit('Invalid session token. Refresh and try again.');
    }
    $adminName = trim((string) ($_POST['admin_name'] ?? ''));
    $adminEmail = strtolower(trim((string) ($_POST['admin_email'] ?? '')));
    $adminPassword = (string) ($_POST['admin_password'] ?? '');
    $adminPasswordConfirm = (string) ($_POST['admin_password_confirm'] ?? '');
    $adminSetupToken = (string) ($_POST['admin_setup_token'] ?? '');

    if (!$adminExists) {
        if (ADMIN_SETUP_TOKEN === 'REPLACE_WITH_A_PRIVATE_RANDOM_TOKEN'
            || mb_strlen(ADMIN_SETUP_TOKEN) < 32
            || !hash_equals(ADMIN_SETUP_TOKEN, $adminSetupToken)) {
            $errors[] = 'Set ADMIN_SETUP_TOKEN to a private random value of at least 32 characters, then enter it here.';
        }
        if (mb_strlen($adminName) < 2 || mb_strlen($adminName) > 120) {
            $errors[] = 'Enter the first administrator name (2–120 characters).';
        }
        if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false || mb_strlen($adminEmail) > 190) {
            $errors[] = 'Enter a valid administrator email address.';
        }
        if (mb_strlen($adminPassword) < 12 || mb_strlen($adminPassword) > 128) {
            $errors[] = 'The administrator password must be between 12 and 128 characters.';
        }
        if (!hash_equals($adminPassword, $adminPasswordConfirm)) {
            $errors[] = 'The administrator password confirmation does not match.';
        }
    }

    if ($errors === []) {
        try {
            $db = install_pdo(DB_NAME);
            foreach ($schema as $sql) {
                $db->exec($sql);
            }
            $adminExists = (bool) $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            if (!$adminExists) {
                $existingAccount = $db->prepare('SELECT id FROM users WHERE email = ?');
                $existingAccount->execute([$adminEmail]);
                if ($existingAccount->fetchColumn()) {
                    throw new RuntimeException('That email already belongs to an account. Enter a different email for the first administrator.');
                }
            }
            upgrade_db($db);
            if (!$adminExists) {
                $db->beginTransaction();
                try {
                    $createAdmin = $db->prepare(
                        "INSERT INTO users (full_name, email, password_hash, role, account_status, email_verified, email_verified_at)
                         VALUES (?, ?, ?, 'admin', 'verified', 1, NOW())"
                    );
                    $createAdmin->execute([$adminName, $adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT)]);
                    $adminId = (int) $db->lastInsertId();
                    $createStaff = $db->prepare(
                        'INSERT INTO admin_staff (user_id, staff_role, active) VALUES (?, ?, 1)'
                    );
                    $createStaff->execute([$adminId, 'super_admin']);
                    $db->commit();
                    $adminCreated = true;
                } catch (Throwable $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    throw $e;
                }
            }
            foreach (['proofs', 'consents', 'works', 'avatars', 'cash-in-proofs', 'admin-resumes', 'admin-verification'] as $d) {
                $directory = APP_ROOT . '/uploads/' . $d;
                if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                    throw new RuntimeException('Could not create upload directory: ' . $d);
                }
                if (file_put_contents($directory . '/index.html', '<!doctype html><title></title>') === false) {
                    throw new RuntimeException('Could not secure upload directory: ' . $d);
                }
            }
            if (file_put_contents(
                APP_ROOT . '/uploads/.htaccess',
                "<FilesMatch \"\\.(php|php5|phtml|phar)$\">\n  Require all denied\n</FilesMatch>\n"
            ) === false) {
                throw new RuntimeException('Could not write upload protection rules.');
            }
            $done = true;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Install · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>">
<style>
  .install-wrap{min-height:100vh;display:grid;place-items:center;padding:3rem 1rem;background:var(--dark);position:relative;overflow:hidden}
  .install-wrap::before{content:"";position:absolute;inset:0;background:radial-gradient(55% 60% at 80% 10%,rgb(124 58 237/.45) 0%,transparent 60%),radial-gradient(40% 50% at 8% 90%,rgb(99 102 241/.4) 0%,transparent 60%)}
  .install-card{position:relative;width:min(620px,100%);background:#fff;border-radius:var(--r-lg);padding:2.6rem;box-shadow:var(--sh-lg)}
  .step-row{display:flex;gap:.55rem;margin:1.6rem 0}
  .step-row .stp{flex:1;font-size:.72rem;font-weight:700;color:var(--muted);display:flex;flex-direction:column;align-items:center;gap:.35rem;text-align:center}
  .step-row .stp i{width:30px;height:30px;border-radius:50%;background:var(--surface-2);border:1.5px solid var(--line);display:flex;align-items:center;justify-content:center;font-style:normal;font-size:.78rem}
  .step-row .stp.on i{background:var(--grad-brand);color:#fff;border:0}
  .code{background:var(--surface-2);border:1px solid var(--line);border-radius:var(--r-sm);padding:.8rem 1rem;font-family:Consolas,monospace;font-size:.82rem;overflow-x:auto}
  .ok-box{display:flex;gap:1rem;align-items:flex-start;background:rgb(16 185 129/.08);border:1px solid rgb(16 185 129/.3);border-radius:var(--r);padding:1.1rem 1.2rem}
  .ok-box > div{min-width:0}
  .ok-box p{overflow-wrap:anywhere}
  @media (max-width:560px){
    .install-wrap{padding:1.25rem .65rem}
    .install-card{padding:1.4rem 1rem}
    .install-card h1{font-size:1.2rem!important;gap:.45rem!important}
    .step-row{gap:.2rem;margin:1.25rem 0}
    .step-row .stp{font-size:.62rem;line-height:1.2}
    .step-row .stp i{width:26px;height:26px;font-size:.7rem}
    .ok-box{padding:.9rem}
    .ok-box .btn{width:100%;text-align:center}
    form > .flex{flex-wrap:wrap;gap:.7rem}
    form > .flex .btn{flex:1 1 100%}
  }
</style>
</head>
<body>
<div class="install-wrap">
  <div class="install-card">
<div class="flex-between mb-1">
      <h1 style="display:flex;align-items:center;gap:.7rem;font-size:1.5rem"><?php echo logo_svg(38); ?> Install <?= e(APP_NAME) ?></h1>
    </div>

    <?php if ($done): ?>
      <div class="step-row">
        <span class="stp on"><i>✓</i>Database</span><span class="stp on"><i>✓</i>Tables</span><span class="stp on"><i>✓</i>Fresh DB</span>
      </div>
      <div class="ok-box">
        <div>
          <b style="color:var(--green-600)"><?= APP_NAME ?> is installed.</b>
          <p class="mt-1" style="margin-bottom:.4rem;color:var(--muted)">
            The database is ready. <?= $adminCreated ? 'Your first admin account is ready at /admin/login.php.' : 'The existing admin account was kept unchanged.' ?>
          </p>
          <?php if ($adminCreated): ?><p style="font-size:.85rem;color:var(--muted)">Admin email: <b><?= e($adminEmail) ?></b>. Use the password you entered during setup and share it with your professor privately.</p><?php endif; ?>
          <p style="font-size:.8rem;color:var(--muted);margin-bottom:1.2rem">For safety, delete this file now that installation is complete.</p>
          <?php if ($adminCreated): ?>
            <a class="btn btn-primary" href="<?= e(base_url('admin/login.php')) ?>">Open Admin portal →</a>
            <a class="btn btn-outline" href="<?= e(base_url('login.php')) ?>">Open marketplace</a>
          <?php else: ?>
            <a class="btn btn-primary" href="<?= e(base_url('login.php')) ?>">Open <?= APP_NAME ?> →</a>
          <?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <?php foreach ($errors as $e): ?>
        <div class="banner banner-bad mb-2"><?= e($e) ?></div>
      <?php endforeach; ?>
      <?php if ($already): ?>
        <div class="banner banner-info mb-2"><?= APP_NAME ?> is already installed. Running again applies safe schema upgrades and never erases existing rows.</div>
      <?php elseif ($connErr !== ''): ?>
        <div class="banner banner-bad mb-2">
          Could not connect to the assigned MySQL database: <span class="code" style="display:inline-block;padding:.15rem .45rem;margin:.2rem 0"><?= e($connErr) ?></span><br>
          Check that the database already exists and that its host, name, username, and password in
          <span class="code" style="display:inline-block;padding:.15rem .45rem">includes/config.php</span>
          match the hosting control panel.
        </div>
      <?php endif; ?>

      <form method="post" action="<?= e(basename($_SERVER['SCRIPT_NAME'])) ?>">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <div class="step-row">
          <span class="stp <?= $connErr === '' ? 'on' : '' ?>"><i><?= $connErr === '' ? '✓' : '·' ?></i>MySQL check</span>
          <span class="stp"><i>2</i>Install tables</span>
          <span class="stp"><i>3</i>Ready</span>
        </div>

        <div class="field">
          <label>What this does</label>
          <div class="code">Use hosting database `<?= e(DB_NAME) ?>`<br>
            + Marketplace, Hiring, and Admin tables<br>
            + hardened uploads/ folder (scripts blocked)</div>
        </div>

        <?php if (!$adminExists): ?>
          <div class="panel card-pad mb-2">
            <h2 style="font-size:1.1rem;margin:0 0 .4rem">Create the first admin</h2>
            <p class="muted" style="font-size:var(--fs-xs)">This account can review users, monitor PayMongo checkouts, and manage the marketplace. Choose a strong password and share it with your professor privately.</p>
            <div class="field"><label for="admin_name">Admin name</label><input class="input" id="admin_name" name="admin_name" required maxlength="120" autocomplete="name" value="<?= e((string) ($_POST['admin_name'] ?? '')) ?>"></div>
            <div class="field"><label for="admin_email">Admin email</label><input class="input" type="email" id="admin_email" name="admin_email" required maxlength="190" autocomplete="username" value="<?= e((string) ($_POST['admin_email'] ?? '')) ?>"></div>
            <div class="field"><label for="admin_password">Admin password</label><input class="input" type="password" id="admin_password" name="admin_password" required minlength="12" maxlength="128" autocomplete="new-password"><small class="hint">At least 12 characters. Do not reuse your hosting or email password.</small></div>
            <div class="field"><label for="admin_password_confirm">Confirm admin password</label><input class="input" type="password" id="admin_password_confirm" name="admin_password_confirm" required minlength="12" maxlength="128" autocomplete="new-password"></div>
            <div class="field"><label for="admin_setup_token">Private setup token</label><input class="input" type="password" id="admin_setup_token" name="admin_setup_token" required autocomplete="off"><small class="hint">Set ADMIN_SETUP_TOKEN in includes/config.php first. Delete install.php immediately after installation.</small></div>
          </div>
        <?php else: ?>
          <div class="banner banner-good mb-2">An administrator already exists. The installer will not replace the existing account or password.</div>
        <?php endif; ?>

        <div class="flex" style="justify-content:space-between">
          <button class="btn btn-ghost" type="button" onclick="history.back()">← Back</button>
          <button class="btn btn-primary btn-lg" type="submit" name="install" value="1">Install <?= e(APP_NAME) ?></button>
        </div>
      </form>
      <p class="mt-3" style="font-size:.78rem;color:var(--faint)">Create your hosting database first, configure <code>includes/config.php</code>, then run this installer once.</p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>