CREATE DATABASE IF NOT EXISTS zanexcel_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE zanexcel_db;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  theme ENUM('light','dark') NOT NULL DEFAULT 'light',
  role ENUM('super_admin','admin') NOT NULL DEFAULT 'admin',
  permissions JSON NULL,
  site_id INT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_admin_users_site (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_settings (
  setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO admin_settings (setting_key, setting_value) VALUES
  ('brand_name', 'Judylink'),
  ('brand_subtitle', 'Tours & travel control'),
  ('brand_logo', '/src/logo/Hyena%20Tours%20%26%20Travel%20Logo%20S-1-Recovered-clean.webp');

CREATE TABLE IF NOT EXISTS admin_navigation (
  nav_key VARCHAR(40) NOT NULL PRIMARY KEY,
  label VARCHAR(80) NOT NULL,
  icon VARCHAR(80) NOT NULL,
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO admin_navigation (nav_key, label, icon, is_visible, sort_order) VALUES
  ('dashboard', 'Dashboard', 'fa-chart-line', 1, 10),
  ('bookings', 'Bookings', 'fa-calendar-check', 1, 20),
  ('gallery', 'Gallery', 'fa-images', 1, 30),
  ('tours', 'Tours', 'fa-route', 1, 40),
  ('staffs', 'Staffs', 'fa-users-gear', 1, 45),
  ('tasks', 'Task', 'fa-list-check', 1, 46),
  ('companies', 'Companies', 'fa-building-user', 1, 47),
  ('sites', 'Sites', 'fa-globe', 1, 48),
  ('settings', 'Settings', 'fa-gear', 1, 50);

CREATE TABLE IF NOT EXISTS tours (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id INT UNSIGNED NULL,
  slug VARCHAR(180) NOT NULL,
  name VARCHAR(180) NOT NULL,
  category VARCHAR(120) NOT NULL,
  location VARCHAR(180) NOT NULL,
  duration VARCHAR(80) NOT NULL,
  group_size VARCHAR(80) NOT NULL DEFAULT 'Private or group',
  rating DECIMAL(3,2) NOT NULL DEFAULT 4.90,
  review_count INT UNSIGNED NOT NULL DEFAULT 0,
  price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  description TEXT NOT NULL,
  highlights JSON NOT NULL,
  includes JSON NOT NULL,
  excludes JSON NOT NULL,
  what_to_bring JSON NOT NULL,
  difficulty VARCHAR(80) NOT NULL DEFAULT 'Easy',
  languages JSON NOT NULL,
  pickup TINYINT(1) NOT NULL DEFAULT 1,
  cancellation VARCHAR(255) NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  gallery JSON NOT NULL,
  itinerary JSON NOT NULL,
  faqs JSON NOT NULL,
  tags JSON NOT NULL,
  is_popular TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  display_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tours_category (category),
  INDEX idx_tours_site (site_id),
  UNIQUE KEY uq_tours_site_slug (site_id, slug),
  INDEX idx_tours_popular (is_popular, display_order),
  INDEX idx_tours_active_order (is_active, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gallery_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id INT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  location VARCHAR(180) NOT NULL DEFAULT 'Zanzibar',
  category VARCHAR(80) NOT NULL DEFAULT 'Activities',
  image_path VARCHAR(255) NOT NULL,
  alt_text VARCHAR(255) NULL,
  span ENUM('wide','tall','normal') NOT NULL DEFAULT 'normal',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_gallery_category (category),
  INDEX idx_gallery_site (site_id),
  INDEX idx_gallery_active_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id INT UNSIGNED NULL,
  booking_ref VARCHAR(32) NOT NULL UNIQUE,
  tour_id INT UNSIGNED NULL,
  tour_slug VARCHAR(180) NOT NULL,
  tour_name VARCHAR(180) NOT NULL,
  travel_date DATE NOT NULL,
  start_time TIME NOT NULL,
  adults INT UNSIGNED NOT NULL DEFAULT 1,
  children INT UNSIGNED NOT NULL DEFAULT 0,
  pickup TINYINT(1) NOT NULL DEFAULT 1,
  pickup_location VARCHAR(255) NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(180) NOT NULL,
  phone VARCHAR(80) NOT NULL,
  nationality VARCHAR(100) NOT NULL,
  country VARCHAR(100) NULL,
  contact_method ENUM('email','phone') NOT NULL DEFAULT 'email',
  special_requests TEXT NULL,
  promo_code VARCHAR(50) NULL,
  payment_method VARCHAR(50) NOT NULL DEFAULT 'later',
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  service_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  agreed_terms TINYINT(1) NOT NULL DEFAULT 0,
  agreed_cancellation TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('new','confirmed','cancelled','completed') NOT NULL DEFAULT 'new',
  payment_status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_bookings_date (travel_date),
  INDEX idx_bookings_site (site_id),
  INDEX idx_bookings_status (status),
  INDEX idx_bookings_created (created_at),
  INDEX idx_bookings_status_payment (status, payment_status),
  INDEX idx_bookings_tour (tour_slug),
  CONSTRAINT fk_bookings_tour FOREIGN KEY (tour_id) REFERENCES tours(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_invites (
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
  INDEX idx_staff_invites_site (site_id),
  INDEX idx_staff_invites_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_registrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  registration_ref VARCHAR(32) NOT NULL UNIQUE,
  company_name VARCHAR(180) NOT NULL,
  owner_name VARCHAR(180) NOT NULL,
  email VARCHAR(180) NOT NULL,
  phone VARCHAR(80) NOT NULL,
  requested_username VARCHAR(100) NULL UNIQUE,
  requested_password_hash VARCHAR(255) NULL,
  website_type VARCHAR(120) NOT NULL DEFAULT 'Tour & Travel',
  plan_id VARCHAR(60) NOT NULL,
  plan_name VARCHAR(100) NOT NULL,
  monthly_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  setup_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  first_payment DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  currency CHAR(3) NOT NULL DEFAULT 'TZS',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_registration_payments (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_sites (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_registration_id INT UNSIGNED NOT NULL UNIQUE,
  site_ref VARCHAR(32) NOT NULL UNIQUE,
  site_name VARCHAR(180) NOT NULL,
  site_slug VARCHAR(180) NOT NULL UNIQUE,
  api_key VARCHAR(96) NULL UNIQUE,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_settings (
  site_id INT UNSIGNED NOT NULL,
  setting_key VARCHAR(80) NOT NULL,
  setting_value TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (site_id, setting_key),
  CONSTRAINT fk_site_settings_site FOREIGN KEY (site_id) REFERENCES company_sites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_api_records (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_api_activity (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_features (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_records (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staffs (
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
  INDEX idx_staffs_site (site_id),
  INDEX idx_staffs_type (staff_type),
  INDEX idx_staffs_email (email),
  CONSTRAINT fk_staffs_invite FOREIGN KEY (invite_id) REFERENCES staff_invites(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_uploads (
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
  INDEX idx_staff_uploads_site (site_id),
  INDEX idx_staff_uploads_status (status),
  INDEX idx_staff_uploads_staff_status_created (staff_id, status, created_at),
  CONSTRAINT fk_staff_uploads_staff FOREIGN KEY (staff_id) REFERENCES staffs(id) ON DELETE CASCADE,
  CONSTRAINT fk_staff_uploads_gallery FOREIGN KEY (gallery_item_id) REFERENCES gallery_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_tasks (
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
  INDEX idx_staff_tasks_site (site_id),
  INDEX idx_staff_tasks_date (task_date),
  INDEX idx_staff_tasks_status (status),
  INDEX idx_staff_tasks_staff_status_date_created (staff_id, status, task_date, created_at),
  CONSTRAINT fk_staff_tasks_staff FOREIGN KEY (staff_id) REFERENCES staffs(id) ON DELETE CASCADE,
  CONSTRAINT fk_staff_tasks_admin FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_task_items (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_task_guests (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
