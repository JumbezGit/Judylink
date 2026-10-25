<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/Database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function admin_secure_path(): string
{
    return '/backend/secure-operation-4f2a/';
}

function admin_url(string $page = ''): string
{
    $page = trim(preg_replace('/\.php$/', '', $page) ?? '', '/');
    return admin_secure_path() . ($page !== '' && $page !== 'index' ? $page : '');
}

function admin_redirect_to_secure_path(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (!str_starts_with($script, '/backend/admin/')) {
        return;
    }

    $page = preg_replace('/\.php$/', '', basename($script)) ?: '';
    $secureScript = admin_url($page);
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    header('Location: ' . $secureScript . ($query !== '' ? '?' . $query : ''));
    exit;
}

admin_redirect_to_secure_path();

function admin_is_logged_in(): bool
{
    return !empty($_SESSION['hyena_admin_logged_in']);
}

function admin_ensure_users_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo = Database::pdo();
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admin_users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            theme ENUM('light','dark') NOT NULL DEFAULT 'light',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    admin_ensure_column('admin_users', 'role', "ALTER TABLE admin_users ADD COLUMN role ENUM('super_admin','admin') NOT NULL DEFAULT 'admin' AFTER theme");
    admin_ensure_column('admin_users', 'permissions', "ALTER TABLE admin_users ADD COLUMN permissions JSON NULL AFTER role");
    admin_ensure_column('admin_users', 'site_id', "ALTER TABLE admin_users ADD COLUMN site_id INT UNSIGNED NULL AFTER permissions");
    admin_ensure_index('admin_users', 'uq_admin_users_site', 'ALTER TABLE admin_users ADD UNIQUE INDEX uq_admin_users_site (site_id)');

    $stmt = $pdo->prepare('SELECT id FROM admin_users WHERE username = ? LIMIT 1');
    $stmt->execute([ADMIN_USERNAME]);
    if (!$stmt->fetchColumn()) {
        $insert = $pdo->prepare('INSERT INTO admin_users (username, password_hash, theme, role, permissions) VALUES (?, ?, ?, ?, ?)');
        $insert->execute([ADMIN_USERNAME, password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT), 'light', 'super_admin', json_encode(admin_default_permissions())]);
    } else {
        $stmt = $pdo->prepare('UPDATE admin_users SET role = ? WHERE username = ?');
        $stmt->execute(['super_admin', ADMIN_USERNAME]);
    }
    $stmt = $pdo->prepare("UPDATE admin_users SET role = 'admin' WHERE username <> ? AND role = 'super_admin'");
    $stmt->execute([ADMIN_USERNAME]);

    admin_ensure_settings_table();
    admin_ensure_navigation_table();
    $done = true;
}

function admin_ensure_column(string $table, string $column, string $sql): void
{
    $stmt = Database::pdo()->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    if ((int) $stmt->fetchColumn() === 0) {
        Database::pdo()->exec($sql);
    }
}

function admin_ensure_index(string $table, string $index, string $sql): void
{
    $stmt = Database::pdo()->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);
    if ((int) $stmt->fetchColumn() === 0) {
        Database::pdo()->exec($sql);
    }
}

function admin_table_exists(string $table): bool
{
    $stmt = Database::pdo()->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function admin_ensure_site_data_isolation(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo = Database::pdo();
    $tables = [
        'tours' => 'idx_tours_site',
        'gallery_items' => 'idx_gallery_site',
        'bookings' => 'idx_bookings_site',
        'staff_invites' => 'idx_staff_invites_site',
        'staffs' => 'idx_staffs_site',
        'staff_uploads' => 'idx_staff_uploads_site',
        'staff_tasks' => 'idx_staff_tasks_site',
    ];
    foreach ($tables as $table => $index) {
        if (!admin_table_exists($table)) {
            continue;
        }
        admin_ensure_column($table, 'site_id', "ALTER TABLE `{$table}` ADD COLUMN site_id INT UNSIGNED NULL AFTER id");
        admin_ensure_index($table, $index, "ALTER TABLE `{$table}` ADD INDEX `{$index}` (site_id)");
    }
    if (admin_table_exists('tours')) {
        $legacySlugIndex = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tours' AND INDEX_NAME = 'slug'"
        );
        $legacySlugIndex->execute();
        if ((int) $legacySlugIndex->fetchColumn() > 0) {
            $pdo->exec('ALTER TABLE tours DROP INDEX slug');
        }
        admin_ensure_index('tours', 'uq_tours_site_slug', 'ALTER TABLE tours ADD UNIQUE INDEX uq_tours_site_slug (site_id, slug)');
    }

    $defaultSiteId = (int) ($pdo->query(
        "SELECT id FROM company_sites ORDER BY FIELD(status, 'active', 'pending_setup', 'suspended', 'archived'), id LIMIT 1"
    )->fetchColumn() ?: 0);
    if ($defaultSiteId < 1) {
        $done = true;
        return;
    }

    if (admin_table_exists('tours')) {
        $pdo->prepare('UPDATE tours SET site_id = ? WHERE site_id IS NULL')->execute([$defaultSiteId]);
    }
    if (admin_table_exists('gallery_items')) {
        $pdo->prepare('UPDATE gallery_items SET site_id = ? WHERE site_id IS NULL')->execute([$defaultSiteId]);
    }
    if (admin_table_exists('staff_invites')) {
        $pdo->prepare('UPDATE staff_invites SET site_id = ? WHERE site_id IS NULL')->execute([$defaultSiteId]);
    }
    if (admin_table_exists('bookings')) {
        $pdo->prepare(
            'UPDATE bookings AS bookings
             LEFT JOIN tours ON tours.id = bookings.tour_id
             SET bookings.site_id = COALESCE(tours.site_id, ?)
             WHERE bookings.site_id IS NULL'
        )->execute([$defaultSiteId]);
    }
    if (admin_table_exists('staffs')) {
        $pdo->prepare(
            'UPDATE staffs AS staffs
             LEFT JOIN staff_invites ON staff_invites.id = staffs.invite_id
             SET staffs.site_id = COALESCE(staff_invites.site_id, ?)
             WHERE staffs.site_id IS NULL'
        )->execute([$defaultSiteId]);
    }
    if (admin_table_exists('staff_uploads')) {
        $pdo->prepare(
            'UPDATE staff_uploads AS uploads
             LEFT JOIN staffs ON staffs.id = uploads.staff_id
             SET uploads.site_id = COALESCE(staffs.site_id, ?)
             WHERE uploads.site_id IS NULL'
        )->execute([$defaultSiteId]);
    }
    if (admin_table_exists('staff_tasks')) {
        $pdo->prepare(
            'UPDATE staff_tasks AS tasks
             LEFT JOIN staffs ON staffs.id = tasks.staff_id
             SET tasks.site_id = COALESCE(staffs.site_id, ?)
             WHERE tasks.site_id IS NULL'
        )->execute([$defaultSiteId]);
    }
    $done = true;
}

function admin_default_permissions(): array
{
    return array_fill_keys(array_keys(admin_pages()), true);
}

function admin_pages(): array
{
    // Add new admin pages here so permissions, labels, and sidebar navigation stay in sync.
    return [
        'dashboard' => [
            'label' => 'Dashboard',
            'icon' => 'fa-chart-line',
            'sort_order' => 10,
            'permission' => true,
            'navigation' => true,
        ],
        'bookings' => [
            'label' => 'Bookings',
            'icon' => 'fa-calendar-check',
            'sort_order' => 20,
            'permission' => true,
            'navigation' => true,
        ],
        'gallery' => [
            'label' => 'Gallery',
            'icon' => 'fa-images',
            'sort_order' => 30,
            'permission' => true,
            'navigation' => true,
        ],
        'tours' => [
            'label' => 'Tours',
            'icon' => 'fa-route',
            'sort_order' => 40,
            'permission' => true,
            'navigation' => true,
        ],
        'staffs' => [
            'label' => 'Staffs',
            'icon' => 'fa-users-gear',
            'sort_order' => 45,
            'permission' => true,
            'navigation' => true,
        ],
        'companies' => [
            'label' => 'Companies',
            'icon' => 'fa-building-user',
            'sort_order' => 47,
            'permission' => true,
            'navigation' => true,
        ],
        'sites' => [
            'label' => 'Sites',
            'icon' => 'fa-globe',
            'sort_order' => 48,
            'permission' => true,
            'navigation' => true,
        ],
        'tasks' => [
            'label' => 'Task',
            'icon' => 'fa-list-check',
            'sort_order' => 46,
            'permission' => true,
            'navigation' => true,
        ],
        'settings' => [
            'label' => 'Settings',
            'icon' => 'fa-gear',
            'sort_order' => 50,
            'permission' => true,
            'navigation' => true,
        ],
        'business-data' => [
            'label' => 'Business Data',
            'icon' => 'fa-table-list',
            'sort_order' => 49,
            'permission' => true,
            'navigation' => false,
        ],
    ];
}

function admin_business_profiles(): array
{
    return [
        'tours-travel' => [
            'label' => 'Tours & Travel',
            'modules' => [
                'bookings' => ['label' => 'Bookings', 'icon' => 'fa-calendar-check', 'handler' => 'native', 'route' => 'bookings', 'sort_order' => 20],
                'gallery' => ['label' => 'Gallery', 'icon' => 'fa-images', 'handler' => 'native', 'route' => 'gallery', 'sort_order' => 30],
                'tours' => ['label' => 'Tours', 'icon' => 'fa-route', 'handler' => 'native', 'route' => 'tours', 'sort_order' => 40],
                'staffs' => ['label' => 'Staff', 'icon' => 'fa-users-gear', 'handler' => 'native', 'route' => 'staffs', 'sort_order' => 45],
                'tasks' => ['label' => 'Tasks', 'icon' => 'fa-list-check', 'handler' => 'native', 'route' => 'tasks', 'sort_order' => 46],
            ],
        ],
        'hotels-lodges' => [
            'label' => 'Hotels & Lodges',
            'modules' => [
                'reservations' => ['label' => 'Reservations', 'icon' => 'fa-calendar-check', 'handler' => 'records', 'sort_order' => 20],
                'rooms' => ['label' => 'Rooms', 'icon' => 'fa-bed', 'handler' => 'records', 'sort_order' => 30],
                'guests' => ['label' => 'Guests', 'icon' => 'fa-users', 'handler' => 'records', 'sort_order' => 40],
                'hotel-services' => ['label' => 'Services', 'icon' => 'fa-bell-concierge', 'handler' => 'records', 'sort_order' => 45],
                'housekeeping' => ['label' => 'Housekeeping', 'icon' => 'fa-broom', 'handler' => 'records', 'sort_order' => 46],
            ],
        ],
        'stationery-printing' => [
            'label' => 'Stationery & Printing',
            'modules' => [
                'orders' => ['label' => 'Orders', 'icon' => 'fa-cart-shopping', 'handler' => 'records', 'sort_order' => 20],
                'products' => ['label' => 'Products', 'icon' => 'fa-box-open', 'handler' => 'records', 'sort_order' => 30],
                'inventory' => ['label' => 'Inventory', 'icon' => 'fa-boxes-stacked', 'handler' => 'records', 'sort_order' => 40],
                'customers' => ['label' => 'Customers', 'icon' => 'fa-user-group', 'handler' => 'records', 'sort_order' => 45],
                'suppliers' => ['label' => 'Suppliers', 'icon' => 'fa-truck-field', 'handler' => 'records', 'sort_order' => 46],
            ],
        ],
        'car-rental' => [
            'label' => 'Car Rental',
            'modules' => [
                'reservations' => ['label' => 'Reservations', 'icon' => 'fa-calendar-check', 'handler' => 'records', 'sort_order' => 20],
                'vehicles' => ['label' => 'Vehicles', 'icon' => 'fa-car-side', 'handler' => 'records', 'sort_order' => 30],
                'customers' => ['label' => 'Customers', 'icon' => 'fa-user-group', 'handler' => 'records', 'sort_order' => 40],
                'drivers' => ['label' => 'Drivers', 'icon' => 'fa-id-card', 'handler' => 'records', 'sort_order' => 45],
                'maintenance' => ['label' => 'Maintenance', 'icon' => 'fa-screwdriver-wrench', 'handler' => 'records', 'sort_order' => 46],
            ],
        ],
        'general-business' => [
            'label' => 'General Business',
            'modules' => [
                'orders' => ['label' => 'Orders', 'icon' => 'fa-cart-shopping', 'handler' => 'records', 'sort_order' => 20],
                'products-services' => ['label' => 'Products & Services', 'icon' => 'fa-boxes-packing', 'handler' => 'records', 'sort_order' => 30],
                'inventory' => ['label' => 'Inventory', 'icon' => 'fa-boxes-stacked', 'handler' => 'records', 'sort_order' => 40],
                'customers' => ['label' => 'Customers', 'icon' => 'fa-user-group', 'handler' => 'records', 'sort_order' => 45],
                'suppliers' => ['label' => 'Suppliers', 'icon' => 'fa-truck-field', 'handler' => 'records', 'sort_order' => 46],
            ],
        ],
    ];
}

function admin_business_profile(string $businessType = ''): array
{
    $profiles = admin_business_profiles();
    return $profiles[$businessType] ?? $profiles['general-business'];
}

function admin_all_feature_permissions(): array
{
    $permissions = [];
    foreach (admin_business_profiles() as $profile) {
        foreach ($profile['modules'] as $key => $module) {
            if (($module['handler'] ?? '') === 'records') {
                $permissions['feature:' . $key] = (string) $module['label'];
            }
        }
    }
    return $permissions;
}

function admin_permission_options_for_business_type(string $businessType): array
{
    $profile = admin_business_profile($businessType);
    $options = ['dashboard' => 'Dashboard'];
    foreach ($profile['modules'] as $key => $module) {
        $permission = ($module['handler'] ?? '') === 'records'
            ? 'feature:' . $key
            : (string) ($module['route'] ?? $key);
        $options[$permission] = (string) $module['label'];
    }
    $options['settings'] = 'Settings';
    return $options;
}

function admin_ensure_settings_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo = Database::pdo();
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admin_settings (
            setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
            setting_value TEXT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $defaults = [
        'brand_name' => 'Hyena Admin',
        'brand_subtitle' => 'Tours & travel control',
        'brand_logo' => '/src/logo/Hyena%20Tours%20%26%20Travel%20Logo%20S-1-Recovered-clean.webp',
    ];
    $stmt = $pdo->prepare('INSERT IGNORE INTO admin_settings (setting_key, setting_value) VALUES (?, ?)');
    foreach ($defaults as $key => $value) {
        $stmt->execute([$key, $value]);
    }
    $done = true;
}

function admin_ensure_navigation_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo = Database::pdo();
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admin_navigation (
            nav_key VARCHAR(40) NOT NULL PRIMARY KEY,
            label VARCHAR(80) NOT NULL,
            icon VARCHAR(80) NOT NULL,
            is_visible TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $items = admin_default_navigation();
    $stmt = $pdo->prepare('INSERT IGNORE INTO admin_navigation (nav_key, label, icon, is_visible, sort_order) VALUES (?, ?, ?, ?, ?)');
    foreach ($items as $key => $item) {
        $stmt->execute([$key, $item['label'], $item['icon'], 1, $item['sort_order']]);
    }

    admin_ensure_staff_tables();
    admin_ensure_company_registration_tables();
    $done = true;
}

function admin_ensure_staff_tables(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo = Database::pdo();
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS staff_invites (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            site_id INT UNSIGNED NULL,
            token VARCHAR(120) NOT NULL UNIQUE,
            staff_type ENUM('tour_guide','driver') NOT NULL,
            email VARCHAR(180) NOT NULL,
            phone VARCHAR(80) NULL,
            created_by INT UNSIGNED NULL,
            used_at TIMESTAMP NULL,
            expires_at TIMESTAMP NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_staff_invites_token (token),
            INDEX idx_staff_invites_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    admin_ensure_column('staff_invites', 'phone', "ALTER TABLE staff_invites ADD COLUMN phone VARCHAR(80) NULL AFTER email");

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS staffs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            site_id INT UNSIGNED NULL,
            invite_id INT UNSIGNED NULL,
            staff_type ENUM('tour_guide','driver') NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            second_name VARCHAR(100) NOT NULL,
            third_name VARCHAR(100) NOT NULL,
            phone VARCHAR(80) NOT NULL,
            email VARCHAR(180) NOT NULL,
            zanzibar_national_id VARCHAR(120) NOT NULL,
            passport_image_path VARCHAR(255) NOT NULL,
            languages JSON NOT NULL,
            tour_guide_id VARCHAR(120) NULL,
            driver_licence VARCHAR(120) NULL,
            driver_licence_file_path VARCHAR(255) NULL,
            username VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            staff_theme VARCHAR(20) NOT NULL DEFAULT 'system',
            staff_sound_enabled TINYINT(1) NOT NULL DEFAULT 1,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            approved_by INT UNSIGNED NULL,
            approved_at TIMESTAMP NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_staffs_status (status),
            INDEX idx_staffs_type (staff_type),
            INDEX idx_staffs_email (email),
            CONSTRAINT fk_staffs_invite FOREIGN KEY (invite_id) REFERENCES staff_invites(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    admin_ensure_column('staffs', 'staff_theme', "ALTER TABLE staffs ADD COLUMN staff_theme VARCHAR(20) NOT NULL DEFAULT 'system' AFTER password_hash");
    admin_ensure_column('staffs', 'staff_sound_enabled', "ALTER TABLE staffs ADD COLUMN staff_sound_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER staff_theme");

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS staff_uploads (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            site_id INT UNSIGNED NULL,
            staff_id INT UNSIGNED NOT NULL,
            title VARCHAR(180) NOT NULL,
            location VARCHAR(180) NOT NULL DEFAULT 'Zanzibar',
            category VARCHAR(120) NOT NULL DEFAULT 'Staff',
            image_path VARCHAR(255) NOT NULL,
            note TEXT NULL,
            status ENUM('pending','gallery','deleted') NOT NULL DEFAULT 'pending',
            gallery_item_id INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_staff_uploads_staff (staff_id),
            INDEX idx_staff_uploads_status (status),
            CONSTRAINT fk_staff_uploads_staff FOREIGN KEY (staff_id) REFERENCES staffs(id) ON DELETE CASCADE,
            CONSTRAINT fk_staff_uploads_gallery FOREIGN KEY (gallery_item_id) REFERENCES gallery_items(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    admin_ensure_index('staff_uploads', 'idx_staff_uploads_staff_status_created', 'ALTER TABLE staff_uploads ADD INDEX idx_staff_uploads_staff_status_created (staff_id, status, created_at)');

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS staff_tasks (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            site_id INT UNSIGNED NULL,
            duty_group_id VARCHAR(32) NULL,
            task_type ENUM('private','group') NOT NULL DEFAULT 'private',
            staff_id INT UNSIGNED NOT NULL,
            task_date DATE NOT NULL,
            tour_details TEXT NOT NULL,
            guest_name VARCHAR(180) NOT NULL,
            hotel VARCHAR(180) NOT NULL,
            pax VARCHAR(80) NOT NULL,
            pickup_time VARCHAR(40) NOT NULL,
            phone VARCHAR(80) NOT NULL,
            language_role VARCHAR(120) NOT NULL,
            status ENUM('assigned','accepted','declined','completed','cancelled') NOT NULL DEFAULT 'assigned',
            response_at TIMESTAMP NULL,
            completion_note TEXT NULL,
            completed_at TIMESTAMP NULL,
            feedback VARCHAR(20) NULL,
            feedback_at TIMESTAMP NULL,
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_staff_tasks_staff (staff_id),
            INDEX idx_staff_tasks_date (task_date),
            INDEX idx_staff_tasks_status (status),
            CONSTRAINT fk_staff_tasks_staff FOREIGN KEY (staff_id) REFERENCES staffs(id) ON DELETE CASCADE,
            CONSTRAINT fk_staff_tasks_admin FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec("ALTER TABLE staff_tasks MODIFY status ENUM('assigned','accepted','declined','completed','cancelled') NOT NULL DEFAULT 'assigned'");
    admin_ensure_column('staff_tasks', 'response_at', "ALTER TABLE staff_tasks ADD COLUMN response_at TIMESTAMP NULL AFTER status");
    admin_ensure_column('staff_tasks', 'completion_note', "ALTER TABLE staff_tasks ADD COLUMN completion_note TEXT NULL AFTER response_at");
    admin_ensure_column('staff_tasks', 'completed_at', "ALTER TABLE staff_tasks ADD COLUMN completed_at TIMESTAMP NULL AFTER completion_note");
    admin_ensure_column('staff_tasks', 'feedback', "ALTER TABLE staff_tasks ADD COLUMN feedback VARCHAR(20) NULL AFTER completed_at");
    admin_ensure_column('staff_tasks', 'feedback_at', "ALTER TABLE staff_tasks ADD COLUMN feedback_at TIMESTAMP NULL AFTER feedback");
    admin_ensure_column('staff_tasks', 'duty_group_id', "ALTER TABLE staff_tasks ADD COLUMN duty_group_id VARCHAR(32) NULL AFTER id");
    admin_ensure_column('staff_tasks', 'task_type', "ALTER TABLE staff_tasks ADD COLUMN task_type ENUM('private','group') NOT NULL DEFAULT 'private' AFTER duty_group_id");
    admin_ensure_index('staff_tasks', 'idx_staff_tasks_staff_status_date_created', 'ALTER TABLE staff_tasks ADD INDEX idx_staff_tasks_staff_status_date_created (staff_id, status, task_date, created_at)');

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS staff_task_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            staff_task_id INT UNSIGNED NOT NULL,
            title VARCHAR(180) NOT NULL,
            pickup_time VARCHAR(80) NULL,
            location VARCHAR(180) NULL,
            notes TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_staff_task_items_task (staff_task_id),
            CONSTRAINT fk_staff_task_items_task FOREIGN KEY (staff_task_id) REFERENCES staff_tasks(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS staff_task_guests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            staff_task_id INT UNSIGNED NOT NULL,
            guest_name VARCHAR(180) NOT NULL,
            hotel VARCHAR(180) NOT NULL,
            pax VARCHAR(80) NOT NULL,
            pickup_time VARCHAR(40) NOT NULL,
            phone VARCHAR(80) NOT NULL,
            language_role VARCHAR(120) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_staff_task_guests_task (staff_task_id),
            CONSTRAINT fk_staff_task_guests_task FOREIGN KEY (staff_task_id) REFERENCES staff_tasks(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $done = true;
}

function admin_ensure_company_registration_tables(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo = Database::pdo();
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS company_registrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            registration_ref VARCHAR(32) NOT NULL UNIQUE,
            company_name VARCHAR(180) NOT NULL,
            owner_name VARCHAR(180) NOT NULL,
            email VARCHAR(180) NOT NULL,
            phone VARCHAR(80) NOT NULL,
            requested_username VARCHAR(100) NULL,
            requested_password_hash VARCHAR(255) NULL,
            website_type VARCHAR(120) NOT NULL DEFAULT 'Tour & Travel',
            plan_id VARCHAR(60) NOT NULL,
            plan_name VARCHAR(100) NOT NULL,
            monthly_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            setup_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            first_payment DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            currency CHAR(3) NOT NULL DEFAULT 'USD',
            payment_method VARCHAR(60) NOT NULL DEFAULT 'card',
            payment_status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            admin_note TEXT NULL,
            approved_by INT UNSIGNED NULL,
            approved_at TIMESTAMP NULL,
            rejected_at TIMESTAMP NULL,
            ip_address VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_company_registrations_status (status),
            INDEX idx_company_registrations_email (email),
            INDEX idx_company_registrations_created (created_at),
            CONSTRAINT fk_company_registrations_admin FOREIGN KEY (approved_by) REFERENCES admin_users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    admin_ensure_column('company_registrations', 'requested_username', "ALTER TABLE company_registrations ADD COLUMN requested_username VARCHAR(100) NULL AFTER phone");
    admin_ensure_column('company_registrations', 'requested_password_hash', "ALTER TABLE company_registrations ADD COLUMN requested_password_hash VARCHAR(255) NULL AFTER requested_username");
    admin_ensure_index('company_registrations', 'uq_company_registrations_username', 'ALTER TABLE company_registrations ADD UNIQUE INDEX uq_company_registrations_username (requested_username)');

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS company_registration_payments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_registration_id INT UNSIGNED NOT NULL,
            merchant_reference VARCHAR(50) NOT NULL UNIQUE,
            gateway_tracking_id VARCHAR(100) NULL UNIQUE,
            checkout_url TEXT NULL,
            amount DECIMAL(10,2) NOT NULL,
            currency CHAR(3) NOT NULL DEFAULT 'TZS',
            status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
            gateway_status VARCHAR(40) NULL,
            gateway_response JSON NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_company_payment_registration (company_registration_id),
            INDEX idx_company_payment_status (status),
            CONSTRAINT fk_company_payment_registration FOREIGN KEY (company_registration_id) REFERENCES company_registrations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS company_sites (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_registration_id INT UNSIGNED NOT NULL UNIQUE,
            site_ref VARCHAR(32) NOT NULL UNIQUE,
            site_name VARCHAR(180) NOT NULL,
            site_slug VARCHAR(180) NOT NULL UNIQUE,
            api_key VARCHAR(96) NULL,
            primary_domain VARCHAR(180) NULL,
            plan_id VARCHAR(60) NOT NULL,
            plan_name VARCHAR(100) NOT NULL,
            business_type VARCHAR(60) NOT NULL DEFAULT 'tours-travel',
            owner_name VARCHAR(180) NOT NULL,
            owner_email VARCHAR(180) NOT NULL,
            owner_phone VARCHAR(80) NOT NULL,
            status ENUM('active','suspended','pending_setup','archived') NOT NULL DEFAULT 'pending_setup',
            setup_status ENUM('pending','in_progress','ready','blocked') NOT NULL DEFAULT 'pending',
            admin_note TEXT NULL,
            created_by INT UNSIGNED NULL,
            activated_at TIMESTAMP NULL,
            suspended_at TIMESTAMP NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_company_sites_status (status),
            INDEX idx_company_sites_setup_status (setup_status),
            CONSTRAINT fk_company_sites_registration FOREIGN KEY (company_registration_id) REFERENCES company_registrations(id) ON DELETE CASCADE,
            CONSTRAINT fk_company_sites_admin FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    admin_ensure_column('company_sites', 'api_key', "ALTER TABLE company_sites ADD COLUMN api_key VARCHAR(96) NULL AFTER site_slug");
    admin_ensure_column('company_sites', 'business_type', "ALTER TABLE company_sites ADD COLUMN business_type VARCHAR(60) NOT NULL DEFAULT 'tours-travel' AFTER plan_name");
    admin_ensure_index('company_sites', 'uq_company_sites_api_key', 'ALTER TABLE company_sites ADD UNIQUE INDEX uq_company_sites_api_key (api_key)');

    $pdo->exec(
        "UPDATE company_sites AS sites
         INNER JOIN company_registrations AS registrations ON registrations.id = sites.company_registration_id
         SET sites.business_type = CASE
             WHEN registrations.plan_id IN ('tours-travel', 'hotels-lodges', 'stationery-printing', 'car-rental', 'general-business') THEN registrations.plan_id
             WHEN LOWER(registrations.website_type) LIKE '%tour%' OR LOWER(registrations.website_type) LIKE '%travel%' THEN 'tours-travel'
             WHEN LOWER(registrations.website_type) LIKE '%hotel%' OR LOWER(registrations.website_type) LIKE '%lodge%' THEN 'hotels-lodges'
             WHEN LOWER(registrations.website_type) LIKE '%stationery%' OR LOWER(registrations.website_type) LIKE '%printing%' THEN 'stationery-printing'
             WHEN LOWER(registrations.website_type) LIKE '%car%' OR LOWER(registrations.website_type) LIKE '%rental%' THEN 'car-rental'
             ELSE 'general-business'
         END
         WHERE sites.business_type NOT IN ('tours-travel', 'hotels-lodges', 'stationery-printing', 'car-rental', 'general-business')"
    );

    $missingKeys = $pdo->query("SELECT id FROM company_sites WHERE api_key IS NULL OR api_key = ''")->fetchAll();
    $saveKey = $pdo->prepare('UPDATE company_sites SET api_key = ? WHERE id = ?');
    foreach ($missingKeys as $site) {
        $saveKey->execute(['judy_' . bin2hex(random_bytes(24)), (int) $site['id']]);
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS site_settings (
            site_id INT UNSIGNED NOT NULL,
            setting_key VARCHAR(80) NOT NULL,
            setting_value TEXT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (site_id, setting_key),
            CONSTRAINT fk_site_settings_site FOREIGN KEY (site_id) REFERENCES company_sites(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS site_api_records (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            site_id INT UNSIGNED NOT NULL,
            collection_key VARCHAR(80) NOT NULL,
            record_key VARCHAR(80) NOT NULL,
            payload JSON NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_site_api_record (site_id, collection_key, record_key),
            INDEX idx_site_api_collection (site_id, collection_key, updated_at),
            CONSTRAINT fk_site_api_records_site FOREIGN KEY (site_id) REFERENCES company_sites(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS site_api_activity (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            site_id INT UNSIGNED NOT NULL,
            action_key VARCHAR(60) NOT NULL,
            collection_key VARCHAR(80) NULL,
            record_key VARCHAR(80) NULL,
            request_method VARCHAR(10) NOT NULL,
            ip_address VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_site_api_activity_site_created (site_id, created_at),
            INDEX idx_site_api_activity_action (site_id, action_key, created_at),
            CONSTRAINT fk_site_api_activity_site FOREIGN KEY (site_id) REFERENCES company_sites(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS site_features (
            site_id INT UNSIGNED NOT NULL,
            feature_key VARCHAR(80) NOT NULL,
            label VARCHAR(100) NOT NULL,
            icon VARCHAR(80) NOT NULL DEFAULT 'fa-table-list',
            handler ENUM('native','records') NOT NULL DEFAULT 'records',
            route VARCHAR(100) NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (site_id, feature_key),
            INDEX idx_site_features_navigation (site_id, is_enabled, sort_order),
            CONSTRAINT fk_site_features_site FOREIGN KEY (site_id) REFERENCES company_sites(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS business_records (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            site_id INT UNSIGNED NOT NULL,
            feature_key VARCHAR(80) NOT NULL,
            record_ref VARCHAR(50) NOT NULL,
            title VARCHAR(180) NOT NULL,
            status VARCHAR(60) NOT NULL DEFAULT 'active',
            amount DECIMAL(12,2) NULL,
            currency CHAR(3) NOT NULL DEFAULT 'TZS',
            details TEXT NULL,
            payload JSON NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_business_record_ref (site_id, feature_key, record_ref),
            INDEX idx_business_records_feature (site_id, feature_key, updated_at),
            INDEX idx_business_records_status (site_id, feature_key, status),
            CONSTRAINT fk_business_records_site FOREIGN KEY (site_id) REFERENCES company_sites(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $sites = $pdo->query('SELECT id, business_type FROM company_sites')->fetchAll();
    foreach ($sites as $site) {
        admin_seed_site_features((int) $site['id'], (string) $site['business_type']);
    }

    admin_ensure_site_data_isolation();

    $done = true;
}

function admin_seed_site_features(int $siteId, string $businessType): void
{
    if ($siteId < 1) {
        return;
    }
    $profile = admin_business_profile($businessType);
    Database::pdo()->prepare('UPDATE site_features SET is_enabled = 0 WHERE site_id = ?')->execute([$siteId]);
    $stmt = Database::pdo()->prepare(
        'INSERT INTO site_features (site_id, feature_key, label, icon, handler, route, is_enabled, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, 1, ?)
         ON DUPLICATE KEY UPDATE label = VALUES(label), icon = VALUES(icon), handler = VALUES(handler),
             route = VALUES(route), is_enabled = 1, sort_order = VALUES(sort_order)'
    );
    foreach ($profile['modules'] as $key => $module) {
        $stmt->execute([
            $siteId,
            $key,
            $module['label'],
            $module['icon'],
            $module['handler'],
            $module['route'] ?? null,
            $module['sort_order'],
        ]);
    }
}

function admin_default_navigation(): array
{
    return array_filter(admin_pages(), fn ($page) => !empty($page['navigation']));
}

function admin_page_label(string $page): string
{
    $pages = admin_pages();
    return (string) ($pages[$page]['label'] ?? ucwords(str_replace(['-', '_'], ' ', $page)));
}

function admin_user_by_username(string $username): ?array
{
    admin_ensure_users_table();
    $stmt = Database::pdo()->prepare('SELECT * FROM admin_users WHERE username = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    return is_array($user) ? $user : null;
}

function admin_current_user(): ?array
{
    if (!admin_is_logged_in()) {
        return null;
    }

    admin_ensure_users_table();
    $userId = (int) ($_SESSION['hyena_admin_user_id'] ?? 0);
    if ($userId > 0) {
        $stmt = Database::pdo()->prepare('SELECT * FROM admin_users WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (is_array($user)) {
            return $user;
        }
    }

    $username = (string) ($_SESSION['hyena_admin_username'] ?? ADMIN_USERNAME);
    return admin_user_by_username($username);
}

function admin_login(string $username, string $password): bool
{
    $user = admin_user_by_username($username);
    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        return false;
    }

    $siteId = (int) ($user['site_id'] ?? 0);
    if ($siteId > 0) {
        admin_ensure_company_registration_tables();
        $siteStmt = Database::pdo()->prepare('SELECT status FROM company_sites WHERE id = ? LIMIT 1');
        $siteStmt->execute([$siteId]);
        if ($siteStmt->fetchColumn() !== 'active') {
            return false;
        }
    }

    $_SESSION['hyena_admin_logged_in'] = true;
    $_SESSION['hyena_admin_token'] = ADMIN_TOKEN;
    $_SESSION['hyena_admin_user_id'] = (int) $user['id'];
    $_SESSION['hyena_admin_username'] = (string) $user['username'];
    $_SESSION['hyena_admin_theme'] = (string) $user['theme'];
    $_SESSION['hyena_admin_role'] = (string) ($user['role'] ?? 'admin');
    if ($siteId > 0) {
        $_SESSION['hyena_admin_selected_site_id'] = $siteId;
    } else {
        unset($_SESSION['hyena_admin_selected_site_id']);
    }
    return true;
}

function admin_logout(): never
{
    admin_clear_session();
    header('Location: ' . admin_url());
    exit;
}

function admin_clear_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function require_admin_page(?string $page = null): void
{
    if (!admin_is_logged_in()) {
        header('Location: ' . admin_url());
        exit;
    }

    if (!admin_current_user()) {
        admin_logout();
    }

    if (
        $page !== null
        && admin_is_super_admin()
        && admin_selected_site_id() < 1
        && !in_array($page, ['companies', 'sites'], true)
    ) {
        header('Location: ' . admin_url('select-site'));
        exit;
    }

    if ($page !== null && !admin_can_view($page)) {
        header('Location: ' . admin_first_allowed_url());
        exit;
    }
}

function admin_token(): string
{
    return (string) ($_SESSION['hyena_admin_token'] ?? ADMIN_TOKEN);
}

function admin_username(): string
{
    return (string) ($_SESSION['hyena_admin_username'] ?? ADMIN_USERNAME);
}

function admin_available_sites(): array
{
    admin_ensure_company_registration_tables();
    return Database::pdo()->query(
        'SELECT sites.id, sites.site_ref, sites.site_name, sites.site_slug, sites.primary_domain,
                sites.status, sites.setup_status, registrations.website_type
         FROM company_sites AS sites
         INNER JOIN company_registrations AS registrations ON registrations.id = sites.company_registration_id
         ORDER BY FIELD(sites.status, "active", "pending_setup", "suspended", "archived"), sites.site_name'
    )->fetchAll();
}

function admin_selected_site_id(): int
{
    return (int) ($_SESSION['hyena_admin_selected_site_id'] ?? 0);
}

function admin_selected_site(): ?array
{
    $id = admin_selected_site_id();
    if ($id < 1) {
        return null;
    }

    admin_ensure_company_registration_tables();
    $stmt = Database::pdo()->prepare(
        'SELECT id, site_ref, site_name, site_slug, primary_domain, plan_id, plan_name, business_type, status, setup_status
         FROM company_sites
         WHERE id = ?
         LIMIT 1'
    );
    $stmt->execute([$id]);
    $site = $stmt->fetch();
    return is_array($site) ? $site : null;
}

function admin_set_selected_site(int $id): ?array
{
    if ($id < 1) {
        unset($_SESSION['hyena_admin_selected_site_id']);
        return null;
    }

    admin_ensure_company_registration_tables();
    $stmt = Database::pdo()->prepare(
        'SELECT id, site_ref, site_name, site_slug, primary_domain, plan_id, plan_name, business_type, status, setup_status
         FROM company_sites
         WHERE id = ?
         LIMIT 1'
    );
    $stmt->execute([$id]);
    $site = $stmt->fetch();
    if (!is_array($site)) {
        unset($_SESSION['hyena_admin_selected_site_id']);
        return null;
    }

    $_SESSION['hyena_admin_selected_site_id'] = (int) $site['id'];
    return $site;
}

function admin_selected_site_label(): string
{
    $site = admin_selected_site();
    return $site ? (string) $site['site_name'] : 'All sites';
}

function admin_selected_business_type(): string
{
    $site = admin_selected_site();
    return (string) ($site['business_type'] ?? $site['plan_id'] ?? 'general-business');
}

function admin_selected_business_feature(string $featureKey): ?array
{
    $siteId = admin_selected_site_id();
    if ($siteId < 1 || $featureKey === '') {
        return null;
    }
    admin_ensure_company_registration_tables();
    $stmt = Database::pdo()->prepare(
        'SELECT feature_key, label, icon, handler, route, is_enabled, sort_order
         FROM site_features
         WHERE site_id = ? AND feature_key = ? AND is_enabled = 1
         LIMIT 1'
    );
    $stmt->execute([$siteId, $featureKey]);
    $feature = $stmt->fetch();
    return is_array($feature) ? $feature : null;
}

function admin_is_super_admin(): bool
{
    $user = admin_current_user();
    return ($user['role'] ?? $_SESSION['hyena_admin_role'] ?? 'admin') === 'super_admin'
        && ($user['username'] ?? '') === ADMIN_USERNAME;
}

function admin_can_manage_selected_site(): bool
{
    $siteId = admin_selected_site_id();
    if ($siteId < 1) {
        return false;
    }
    if (admin_is_super_admin()) {
        return true;
    }
    $user = admin_current_user();
    return (int) ($user['site_id'] ?? 0) === $siteId;
}

function admin_permissions(?array $user = null): array
{
    $user ??= admin_current_user();
    $permissions = json_decode((string) ($user['permissions'] ?? ''), true);
    if (!is_array($permissions)) {
        $permissions = admin_default_permissions();
    }
    return admin_sanitize_permissions($permissions, true);
}

function admin_sanitize_permissions(array $permissions, bool $allowEmpty = false): array
{
    $clean = [];
    foreach (array_keys(admin_default_permissions()) as $key) {
        $clean[$key] = !empty($permissions[$key]);
    }

    $featurePermissionsWereInitialized = !empty($permissions['_feature_permissions_initialized']);
    $legacyFeatureDefault = !$featurePermissionsWereInitialized
        && (!empty($permissions['business-data']) || !empty($permissions['dashboard']));
    foreach (array_keys(admin_all_feature_permissions()) as $key) {
        $clean[$key] = array_key_exists($key, $permissions)
            ? !empty($permissions[$key])
            : $legacyFeatureDefault;
    }
    $clean['_feature_permissions_initialized'] = $featurePermissionsWereInitialized;

    if (empty($clean['staffs'])) {
        $clean['tasks'] = false;
    }

    if (!$allowEmpty && !in_array(true, $clean, true)) {
        $clean['dashboard'] = true;
    }

    return $clean;
}

function admin_can_view(string $page): bool
{
    $user = admin_current_user();
    if (!$user) {
        return false;
    }

    if (($user['role'] ?? 'admin') === 'super_admin' && ($user['username'] ?? '') === ADMIN_USERNAME) {
        return true;
    }

    if (in_array($page, ['companies', 'sites'], true)) {
        return false;
    }

    $permissions = admin_permissions($user);
    if (str_starts_with($page, 'feature:')) {
        return !empty($permissions[$page]);
    }
    if ($page === 'business-data') {
        return !empty($permissions['business-data'])
            || count(array_filter($permissions, fn ($allowed, $key) => str_starts_with((string) $key, 'feature:') && $allowed, ARRAY_FILTER_USE_BOTH)) > 0;
    }
    return !empty($permissions[$page]);
}

function admin_first_allowed_url(): string
{
    foreach (admin_navigation_items(true) as $key => $item) {
        if (!empty($item['is_visible']) && admin_can_view((string) ($item['permission'] ?? $key))) {
            return (string) ($item['href'] ?? admin_url($key));
        }
    }
    return admin_url('no-access');
}

function admin_after_login_url(): string
{
    if (admin_is_super_admin()) {
        return admin_url('select-site');
    }

    return admin_first_allowed_url();
}

function admin_theme(): string
{
    $user = admin_current_user();
    $theme = (string) ($user['theme'] ?? $_SESSION['hyena_admin_theme'] ?? 'light');
    return in_array($theme, ['light', 'dark'], true) ? $theme : 'light';
}

function admin_update_theme(string $theme): void
{
    if (!in_array($theme, ['light', 'dark'], true)) {
        return;
    }

    $user = admin_current_user();
    if (!$user) {
        return;
    }

    $stmt = Database::pdo()->prepare('UPDATE admin_users SET theme = ? WHERE id = ?');
    $stmt->execute([$theme, (int) $user['id']]);
    $_SESSION['hyena_admin_theme'] = $theme;
}

function admin_change_password(string $currentPassword, string $newPassword): bool
{
    $user = admin_current_user();
    if (!$user || !password_verify($currentPassword, (string) $user['password_hash'])) {
        return false;
    }

    $stmt = Database::pdo()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
    $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $user['id']]);
    return true;
}

function admin_site_permissions(string $businessType = 'tours-travel'): array
{
    $permissions = array_fill_keys(array_keys(admin_default_permissions()), false);
    foreach (array_keys(admin_all_feature_permissions()) as $featurePermission) {
        $permissions[$featurePermission] = false;
    }
    foreach (array_keys(admin_permission_options_for_business_type($businessType)) as $permission) {
        $permissions[$permission] = true;
    }
    $permissions['business-data'] = $businessType !== 'tours-travel';
    $permissions['_feature_permissions_initialized'] = true;
    return $permissions;
}

function admin_update_user_controls(int $id, string $role, array $permissions, int $isActive): void
{
    if (!admin_is_super_admin() || $id < 1) {
        return;
    }

    $currentUser = admin_current_user();
    if ($currentUser && (int) $currentUser['id'] === $id) {
        $role = 'super_admin';
        $isActive = 1;
    }

    $role = in_array($role, ['super_admin', 'admin'], true) ? $role : 'admin';
    $permissions = admin_sanitize_permissions($permissions, true);
    $permissions['_feature_permissions_initialized'] = true;
    $stmt = Database::pdo()->prepare('UPDATE admin_users SET role = ?, permissions = ?, is_active = ? WHERE id = ?');
    $stmt->execute([$role, json_encode($permissions), $isActive === 1 ? 1 : 0, $id]);
}

function admin_delete_user(int $id): bool
{
    if (!admin_is_super_admin() || $id < 1) {
        return false;
    }

    $currentUser = admin_current_user();
    if ($currentUser && (int) $currentUser['id'] === $id) {
        return false;
    }

    $stmt = Database::pdo()->prepare('SELECT username, role FROM admin_users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!is_array($user) || ($user['username'] ?? '') === ADMIN_USERNAME || ($user['role'] ?? '') === 'super_admin') {
        return false;
    }

    $stmt = Database::pdo()->prepare('DELETE FROM admin_users WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

function admin_setting(string $key, string $fallback = ''): string
{
    if (!array_key_exists('admin_setting_cache', $GLOBALS)) {
        $GLOBALS['admin_setting_cache'] = [];
    }
    admin_ensure_settings_table();
    $siteId = admin_selected_site_id();
    $cacheKey = $siteId > 0 ? 'site:' . $siteId : 'global';

    if (!isset($GLOBALS['admin_setting_cache'][$cacheKey])) {
        $GLOBALS['admin_setting_cache'][$cacheKey] = [];
        if ($siteId > 0) {
            admin_ensure_company_registration_tables();
            $stmt = Database::pdo()->prepare('SELECT setting_key, setting_value FROM site_settings WHERE site_id = ?');
            $stmt->execute([$siteId]);
        } else {
            $stmt = Database::pdo()->query('SELECT setting_key, setting_value FROM admin_settings');
        }
        foreach ($stmt->fetchAll() as $row) {
            $GLOBALS['admin_setting_cache'][$cacheKey][(string) $row['setting_key']] = (string) $row['setting_value'];
        }
    }

    if (array_key_exists($key, $GLOBALS['admin_setting_cache'][$cacheKey])) {
        return (string) $GLOBALS['admin_setting_cache'][$cacheKey][$key];
    }

    if ($siteId > 0) {
        if ($key === 'brand_name') {
            $site = admin_selected_site();
            return (string) ($site['site_name'] ?? $fallback);
        }
        if ($key === 'brand_subtitle') {
            return 'Website management';
        }
        if ($key === 'brand_logo') {
            return '';
        }
    }

    return $fallback;
}

function admin_update_settings(array $settings): void
{
    if (!admin_can_manage_selected_site()) {
        return;
    }

    admin_ensure_settings_table();
    $siteId = admin_selected_site_id();
    if ($siteId > 0) {
        admin_ensure_company_registration_tables();
        $stmt = Database::pdo()->prepare('INSERT INTO site_settings (site_id, setting_key, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($settings as $key => $value) {
            $stmt->execute([$siteId, $key, (string) $value]);
        }
    } else {
        $stmt = Database::pdo()->prepare('INSERT INTO admin_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, (string) $value]);
        }
    }
    $GLOBALS['admin_setting_cache'] = [];
}

function admin_navigation_items(bool $includeHidden = false): array
{
    if (!array_key_exists('admin_navigation_cache', $GLOBALS)) {
        $GLOBALS['admin_navigation_cache'] = [];
    }
    $siteId = admin_selected_site_id();
    $cacheKey = ($includeHidden ? 'all' : 'visible') . ':site:' . $siteId;
    if (isset($GLOBALS['admin_navigation_cache'][$cacheKey])) {
        return $GLOBALS['admin_navigation_cache'][$cacheKey];
    }

    admin_ensure_navigation_table();

    if ($siteId > 0 && admin_selected_business_type() !== 'tours-travel') {
        $items = [
            'dashboard' => [
                'label' => 'Dashboard',
                'href' => admin_url('dashboard'),
                'icon' => 'fa-chart-line',
                'is_visible' => 1,
                'sort_order' => 10,
                'permission' => 'dashboard',
            ],
        ];
        $stmt = Database::pdo()->prepare(
            'SELECT feature_key, label, icon, handler, route, is_enabled, sort_order
             FROM site_features
             WHERE site_id = ?' . ($includeHidden ? '' : ' AND is_enabled = 1') . '
             ORDER BY sort_order, feature_key'
        );
        $stmt->execute([$siteId]);
        foreach ($stmt->fetchAll() as $row) {
            $key = 'feature:' . (string) $row['feature_key'];
            $handler = (string) $row['handler'];
            $route = trim((string) ($row['route'] ?? ''));
            $items[$key] = [
                'label' => (string) $row['label'],
                'href' => $handler === 'native' && $route !== ''
                    ? admin_url($route)
                    : admin_url('business-data') . '?module=' . rawurlencode((string) $row['feature_key']),
                'icon' => (string) $row['icon'],
                'is_visible' => (int) $row['is_enabled'],
                'sort_order' => (int) $row['sort_order'],
                'permission' => $handler === 'native' && $route !== '' ? $route : 'feature:' . (string) $row['feature_key'],
            ];
        }
        $items['settings'] = [
            'label' => 'Settings',
            'href' => admin_url('settings'),
            'icon' => 'fa-gear',
            'is_visible' => 1,
            'sort_order' => 90,
            'permission' => 'settings',
        ];
        $items['companies'] = [
            'label' => 'Companies',
            'href' => admin_url('companies'),
            'icon' => 'fa-building-user',
            'is_visible' => 1,
            'sort_order' => 95,
            'permission' => 'companies',
        ];
        $items['sites'] = [
            'label' => 'Sites',
            'href' => admin_url('sites'),
            'icon' => 'fa-globe',
            'is_visible' => 1,
            'sort_order' => 96,
            'permission' => 'sites',
        ];
        $GLOBALS['admin_navigation_cache'][$cacheKey] = $items;
        return $items;
    }

    $stmt = Database::pdo()->query('SELECT * FROM admin_navigation ORDER BY sort_order, nav_key');
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!$includeHidden && (int) $row['is_visible'] !== 1) {
            continue;
        }
        $key = (string) $row['nav_key'];
        $items[$key] = [
            'label' => (string) $row['label'],
            'href' => admin_url($key),
            'icon' => (string) $row['icon'],
            'is_visible' => (int) $row['is_visible'],
            'sort_order' => (int) $row['sort_order'],
            'permission' => $key,
        ];
    }
    $GLOBALS['admin_navigation_cache'][$cacheKey] = $items;
    return $items;
}

function admin_update_navigation(array $items): void
{
    if (!admin_is_super_admin()) {
        return;
    }

    admin_ensure_navigation_table();
    $stmt = Database::pdo()->prepare('UPDATE admin_navigation SET label = ?, icon = ?, is_visible = ?, sort_order = ? WHERE nav_key = ?');
    foreach ($items as $key => $item) {
        if (!array_key_exists($key, admin_default_navigation())) {
            continue;
        }
        $stmt->execute([
            trim((string) ($item['label'] ?? $key)) ?: $key,
            trim((string) ($item['icon'] ?? 'fa-circle')) ?: 'fa-circle',
            !empty($item['is_visible']) ? 1 : 0,
            (int) ($item['sort_order'] ?? 0),
            $key,
        ]);
    }
    $GLOBALS['admin_navigation_cache'] = [];
}


