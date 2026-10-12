<?php
declare(strict_types=1);

/**
 * Idempotent schema upgrades for already-installed databases.
 * run after the installer creates tables (install.php) so existing
 * installs grow new columns/tables without dropping data.
 */

function db_column_exists(PDO $db, string $table, string $col): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = ? AND table_name = ? AND column_name = ?'
    );
    $stmt->execute([DB_NAME, $table, $col]);
    return (bool) $stmt->fetchColumn();
}

function upgrade_db(PDO $db): void
{
    $db->exec('CREATE TABLE IF NOT EXISTS service_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY service_images_order (service_id, sort_order, id),
        CONSTRAINT service_images_service_fk FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // Future-ready admin staff roles. Existing admins remain super admins until assigned.
    $db->exec("CREATE TABLE IF NOT EXISTS admin_staff (
        user_id INT PRIMARY KEY,
        staff_role VARCHAR(30) NOT NULL DEFAULT 'moderator',
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE IF NOT EXISTS admin_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL,
        requested_role VARCHAR(30) NOT NULL,
        experience TEXT NOT NULL,
        motivation TEXT NOT NULL,
        verification_file VARCHAR(255) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        reviewed_by INT NULL,
        reviewed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY application_status (status),
        KEY application_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (!db_column_exists($db, 'admin_applications', 'contact_email')) {
        $db->exec("ALTER TABLE admin_applications ADD COLUMN contact_email VARCHAR(190) NOT NULL DEFAULT '' AFTER email");
    }
    if (!db_column_exists($db, 'admin_applications', 'date_of_birth')) {
        $db->exec("ALTER TABLE admin_applications ADD COLUMN date_of_birth DATE NULL AFTER contact_email");
    }
    if (!db_column_exists($db, 'admin_applications', 'password_hash')) {
        $db->exec("ALTER TABLE admin_applications ADD COLUMN password_hash VARCHAR(255) NULL AFTER date_of_birth");
    }
    if (!db_column_exists($db, 'admin_applications', 'reviewer_notes')) {
        $db->exec("ALTER TABLE admin_applications ADD COLUMN reviewer_notes TEXT NULL AFTER status");
    }
    foreach ([
        'tracking_reference' => "VARCHAR(20) NULL",
        'tracking_token_hash' => "CHAR(64) NULL",
        'interview_at' => "DATETIME NULL",
        'interview_location' => "VARCHAR(190) NULL",
        'withdrawn_at' => "DATETIME NULL",
    ] as $column => $definition) {
        if (!db_column_exists($db, 'admin_applications', $column)) {
            $db->exec("ALTER TABLE admin_applications ADD COLUMN {$column} {$definition}");
        }
    }
    foreach ([
        'phone' => "VARCHAR(40) NOT NULL DEFAULT ''",
        'location' => "VARCHAR(120) NOT NULL DEFAULT ''",
        'education' => "TEXT NULL",
        'work_history' => "TEXT NULL",
        'skills' => "TEXT NULL",
        'availability' => "VARCHAR(30) NOT NULL DEFAULT 'part_time'",
        'reference_name' => "VARCHAR(120) NOT NULL DEFAULT ''",
        'reference_contact' => "VARCHAR(190) NOT NULL DEFAULT ''",
        'resume_file' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'cover_letter' => "TEXT NULL",
        'applicant_declaration' => "TINYINT(1) NOT NULL DEFAULT 0",
    ] as $column => $definition) {
        if (!db_column_exists($db, 'admin_applications', $column)) {
            $db->exec("ALTER TABLE admin_applications ADD COLUMN {$column} {$definition}");
        }
    }

    // Nerasky Pro — premium_until on users
    if (!db_column_exists($db, 'users', 'premium_until')) {
        $db->exec('ALTER TABLE users ADD COLUMN premium_until DATETIME NULL AFTER wallet');
    }

    // Profile customization — frame + accent colour
    if (!db_column_exists($db, 'users', 'profile_frame')) {
        $db->exec("ALTER TABLE users ADD COLUMN profile_frame VARCHAR(30) NOT NULL DEFAULT 'none' AFTER premium_until");
    }
    if (!db_column_exists($db, 'users', 'profile_accent')) {
        $db->exec("ALTER TABLE users ADD COLUMN profile_accent VARCHAR(20) NOT NULL DEFAULT 'indigo' AFTER profile_frame");
    }
    if (!db_column_exists($db, 'users', 'profile_layout')) {
        $db->exec("ALTER TABLE users ADD COLUMN profile_layout VARCHAR(20) NOT NULL DEFAULT 'classic' AFTER profile_accent");
    }
    if (!db_column_exists($db, 'users', 'profile_progress_style')) {
        $db->exec("ALTER TABLE users ADD COLUMN profile_progress_style VARCHAR(20) NOT NULL DEFAULT 'gradient' AFTER profile_layout");
    }
    if (!db_column_exists($db, 'users', 'profile_stat_visibility')) {
        $db->exec('ALTER TABLE users ADD COLUMN profile_stat_visibility TEXT NULL AFTER profile_progress_style');
    }
    if (!db_column_exists($db, 'users', 'profile_status')) {
        $db->exec("ALTER TABLE users ADD COLUMN profile_status VARCHAR(20) NOT NULL DEFAULT 'online' AFTER profile_stat_visibility");
    }
    if (!db_column_exists($db, 'users', 'profile_note')) {
        $db->exec('ALTER TABLE users ADD COLUMN profile_note VARCHAR(160) NULL AFTER profile_status');
    }
    if (!db_column_exists($db, 'users', 'profile_note_theme')) {
        $db->exec("ALTER TABLE users ADD COLUMN profile_note_theme VARCHAR(20) NOT NULL DEFAULT 'indigo' AFTER profile_note");
    }
    if (!db_column_exists($db, 'users', 'chat_theme')) {
        $db->exec("ALTER TABLE users ADD COLUMN chat_theme VARCHAR(20) NOT NULL DEFAULT 'indigo' AFTER profile_note_theme");
    }
    // Profile discovery preferences (stored as JSON for flexible student tags/schedules).
    if (!db_column_exists($db, 'users', 'skills_tags')) {
        $db->exec('ALTER TABLE users ADD COLUMN skills_tags TEXT NULL AFTER bio');
    }
    if (!db_column_exists($db, 'users', 'availability_schedule')) {
        $db->exec('ALTER TABLE users ADD COLUMN availability_schedule TEXT NULL AFTER skills_tags');
    }
    if (!db_column_exists($db, 'users', 'payment_methods')) {
        $db->exec('ALTER TABLE users ADD COLUMN payment_methods TEXT NULL AFTER availability_schedule');
    }
    if (!db_column_exists($db, 'users', 'avatar_file')) {
        $db->exec('ALTER TABLE users ADD COLUMN avatar_file VARCHAR(255) NULL AFTER profile_layout');
    }

    // Age verification — date of birth + the ID type presented at signup
    if (!db_column_exists($db, 'users', 'date_of_birth')) {
        $db->exec('ALTER TABLE users ADD COLUMN date_of_birth DATE NULL AFTER avatar_file');
    }
    if (!db_column_exists($db, 'users', 'id_type')) {
        $db->exec("ALTER TABLE users ADD COLUMN id_type VARCHAR(20) NOT NULL DEFAULT 'school' AFTER date_of_birth");
    }

    // E-mail verification — the address must be confirmed before sign-in.
    // New accounts default to unverified (0); admins and existing rows are
    // treated as already-verified so nothing breaks for the demo data.
    if (!db_column_exists($db, 'users', 'email_verified')) {
        $db->exec('ALTER TABLE users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER id_type');
        // Backfill ONCE, only when the column is first added: everyone who
        // already had an account before this feature is grandfathered in as
        // verified (otherwise their logins would all break).
        // NOTE: this MUST stay inside this if-block — a backfill here outside
        // would auto-verify every new signup on every request.
        $backfillAdded = true;
    }
    if (!db_column_exists($db, 'users', 'email_verified_at')) {
        $db->exec('ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL AFTER email_verified');
    }
    if (!empty($backfillAdded)) {
        $db->exec('UPDATE users SET email_verified = 1, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE email_verified = 0');
    }

    // Preserve Firebase IDs from older installs and add the Supabase identity
    // link used for current authentication.
    if (!db_column_exists($db, 'users', 'firebase_uid')) {
        $db->exec("ALTER TABLE users ADD COLUMN firebase_uid VARCHAR(128) NULL AFTER email_verified_at");
    }
    if (!db_column_exists($db, 'users', 'supabase_uid')) {
        $db->exec("ALTER TABLE users ADD COLUMN supabase_uid VARCHAR(128) NULL AFTER firebase_uid");
    }
    if (!db_column_exists($db, 'users', 'google_verified')) {
        $db->exec("ALTER TABLE users ADD COLUMN google_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER supabase_uid");
    }

    // Subscription ledger
    $db->exec('CREATE TABLE IF NOT EXISTS subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        plan VARCHAR(30) NOT NULL DEFAULT "pro",
        amount DECIMAL(10,2) NOT NULL,
        source ENUM("wallet") NOT NULL DEFAULT "wallet",
        starts_at DATETIME NOT NULL,
        ends_at DATETIME NOT NULL,
        status ENUM("active","cancelled") NOT NULL DEFAULT "active",
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $subscriptionSource = $db->query(
        "SELECT column_type FROM information_schema.columns
         WHERE table_schema = " . $db->quote(DB_NAME) . "
           AND table_name = 'subscriptions' AND column_name = 'source'"
    )->fetchColumn();
    if (is_string($subscriptionSource) && !str_contains($subscriptionSource, "'paymongo'")) {
        $db->exec("ALTER TABLE subscriptions MODIFY source ENUM('wallet','paymongo') NOT NULL DEFAULT 'wallet'");
    }
    $subscriptionStatus = $db->query(
        "SELECT column_type FROM information_schema.columns
         WHERE table_schema = " . $db->quote(DB_NAME) . "
           AND table_name = 'subscriptions' AND column_name = 'status'"
    )->fetchColumn();
    if (is_string($subscriptionStatus) && !str_contains($subscriptionStatus, "'expired'")) {
        $db->exec("ALTER TABLE subscriptions MODIFY status ENUM('active','cancelled','expired') NOT NULL DEFAULT 'active'");
    }
    $db->exec('CREATE TABLE IF NOT EXISTS paymongo_checkouts (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $db->exec('CREATE TABLE IF NOT EXISTS paymongo_webhook_events (
        event_id VARCHAR(120) PRIMARY KEY,
        event_type VARCHAR(80) NOT NULL,
        received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // Direct messaging
    $db->exec('CREATE TABLE IF NOT EXISTS conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user1_id INT NOT NULL,
        user2_id INT NOT NULL,
        last_message_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY pair (user1_id, user2_id),
        KEY user2 (user2_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $db->exec('CREATE TABLE IF NOT EXISTS conversation_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        sender_id INT NOT NULL,
        body TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY conversation_id (conversation_id),
        KEY sender (sender_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    foreach (['attachment_path' => 'VARCHAR(255) NULL', 'attachment_name' => 'VARCHAR(180) NULL', 'attachment_mime' => 'VARCHAR(80) NULL', 'attachment_size' => 'INT UNSIGNED NULL'] as $column => $definition) {
        if (!db_column_exists($db, 'conversation_messages', $column)) {
            $db->exec('ALTER TABLE conversation_messages ADD COLUMN ' . $column . ' ' . $definition . ' AFTER body');
        }
    }
    foreach (['archived_user1' => 'TINYINT(1) NOT NULL DEFAULT 0', 'archived_user2' => 'TINYINT(1) NOT NULL DEFAULT 0'] as $column => $definition) {
        if (!db_column_exists($db, 'conversations', $column)) {
            $db->exec('ALTER TABLE conversations ADD COLUMN ' . $column . ' ' . $definition . ' AFTER last_message_at');
        }
    }

    // Notifications
    $db->exec('CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        kind VARCHAR(30) NOT NULL,
        text VARCHAR(500) NOT NULL,
        link VARCHAR(190) NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY user (user_id, is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // Saved marketplace services (one saved service per member).
    $db->exec('CREATE TABLE IF NOT EXISTS favorites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        service_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY user_service (user_id, service_id),
        KEY service_id (service_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $db->exec('CREATE TABLE IF NOT EXISTS wallet_transactions (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // Manual GCash cash-in requests stay pending until an admin verifies the payment.
    $db->exec('CREATE TABLE IF NOT EXISTS cash_in_requests (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // Older installs created pending withdrawals before wallet reserves existed.
    $db->exec('UPDATE users u
        JOIN (
            SELECT w.user_id, SUM(w.amount) AS total
            FROM withdrawals w
            WHERE w.status = "pending"
              AND NOT EXISTS (
                  SELECT 1 FROM wallet_transactions t
                  WHERE t.user_id = w.user_id
                    AND t.type = "withdrawal_reserve"
                    AND t.description = CONCAT("Withdrawal reserved for ", w.method)
              )
            GROUP BY w.user_id
        ) pending ON pending.user_id = u.id
        SET u.wallet = u.wallet - pending.total
        WHERE u.wallet >= pending.total');
    $db->exec('INSERT INTO wallet_transactions (user_id, type, amount, direction, description)
        SELECT w.user_id, "withdrawal_reserve", w.amount, "debit", CONCAT("Withdrawal reserved for ", w.method)
        FROM withdrawals w
        JOIN users u ON u.id = w.user_id
        WHERE w.status = "pending"
          AND u.wallet >= w.amount
              AND NOT EXISTS (
              SELECT 1 FROM wallet_transactions t
              WHERE t.user_id = w.user_id
                AND t.type = "withdrawal_reserve"
                AND t.description = CONCAT("Withdrawal reserved for ", w.method)
          )');

    // Admin audit logging — track all moderation and financial actions
    $db->exec('CREATE TABLE IF NOT EXISTS admin_audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        action VARCHAR(40) NOT NULL,
        entity_type VARCHAR(40) NOT NULL,
        entity_id INT NOT NULL,
        details JSON NULL,
        ip_address VARCHAR(45) NOT NULL,
        user_agent VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY admin (admin_id),
        KEY action (action),
        KEY entity (entity_type, entity_id),
        KEY created (created_at),
        FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $db->exec('CREATE TABLE IF NOT EXISTS admin_2fa (
        user_id INT PRIMARY KEY,
        secret VARCHAR(64) NOT NULL,
        recovery_codes TEXT NULL,
        enabled TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        enabled_at DATETIME NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    if (!db_column_exists($db, 'admin_2fa', 'recovery_codes')) {
        $db->exec('ALTER TABLE admin_2fa ADD COLUMN recovery_codes TEXT NULL AFTER secret');
    }

    $db->exec('CREATE TABLE IF NOT EXISTS admin_invites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY invite_user (user_id),
        KEY invite_expiry (expires_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $db->exec('CREATE TABLE IF NOT EXISTS admin_password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY reset_user (user_id),
        KEY reset_expiry (expires_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $db->exec('CREATE TABLE IF NOT EXISTS user_email_verifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY verify_user (user_id),
        KEY verify_expiry (expires_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $db->exec('CREATE TABLE IF NOT EXISTS admin_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(190) NOT NULL,
        details TEXT NULL,
        priority ENUM("low","normal","high","urgent") NOT NULL DEFAULT "normal",
        status ENUM("open","in_progress","done") NOT NULL DEFAULT "open",
        assigned_to INT NULL,
        due_at DATETIME NULL,
        created_by INT NOT NULL,
        completed_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY task_status (status, priority),
        KEY task_assignee (assigned_to),
        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $db->exec('CREATE TABLE IF NOT EXISTS support_tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        subject VARCHAR(160) NOT NULL,
        message TEXT NOT NULL,
        status ENUM("open","pending","closed") NOT NULL DEFAULT "open",
        priority ENUM("low","normal","high","urgent") NOT NULL DEFAULT "normal",
        admin_reply TEXT NULL,
        assigned_to INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY ticket_user (user_id, status),
        KEY ticket_status (status),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    foreach ([
        'priority' => 'ENUM("low","normal","high","urgent") NOT NULL DEFAULT "normal"',
        'admin_reply' => 'TEXT NULL',
        'assigned_to' => 'INT NULL',
    ] as $column => $definition) {
        if (!db_column_exists($db, 'support_tickets', $column)) {
            $db->exec('ALTER TABLE support_tickets ADD COLUMN ' . $column . ' ' . $definition);
        }
    }
    $db->exec('CREATE TABLE IF NOT EXISTS platform_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reporter_id INT NOT NULL,
        target_type VARCHAR(30) NOT NULL,
        target_id INT NOT NULL,
        reason VARCHAR(80) NOT NULL,
        details TEXT NOT NULL,
        status ENUM("open","reviewing","resolved","dismissed") NOT NULL DEFAULT "open",
        reviewed_by INT NULL,
        resolution TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY report_status (status), KEY report_target (target_type,target_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // Rate-limiter ledger (brute-force protection). Expires-driven cleanup
    // keeps the table small.
    $db->exec('CREATE TABLE IF NOT EXISTS security_throttle (
        key_hash CHAR(64) NOT NULL PRIMARY KEY,
        attempts INT UNSIGNED NOT NULL DEFAULT 0,
        window_expires INT UNSIGNED NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}