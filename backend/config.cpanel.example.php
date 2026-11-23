<?php

declare(strict_types=1);

const APP_ENV = 'production';

// cPanel prefixes database and user names with your account username.
const DB_HOST = 'localhost';
const DB_NAME = 'CPANELUSER_DATABASE';
const DB_USER = 'CPANELUSER_DBUSER';
const DB_PASS = 'REPLACE_WITH_DATABASE_PASSWORD';
const DB_CHARSET = 'utf8mb4';

// No trailing slash. Always use the final HTTPS website address.
const APP_URL = 'https://example.com';

// Use long, unique random values and keep this file server-side only.
const ADMIN_TOKEN = 'REPLACE_WITH_AT_LEAST_32_RANDOM_CHARACTERS';
const ADMIN_USERNAME = 'REPLACE_WITH_ADMIN_USERNAME';
const ADMIN_PASSWORD = 'REPLACE_WITH_A_STRONG_ADMIN_PASSWORD';

const SCAYPAY_ENVIRONMENT = 'sandbox';
const SCAYPAY_API_URL = 'https://api.scaypay.com/v1';
const SCAYPAY_SECRET_KEY = '';
const SCAYPAY_WEBHOOK_URL = '';
const SCAYPAY_WEBHOOK_SECRET = '';
const SCAYPAY_CALLBACK_URL = APP_URL . '/plans';

const UPLOAD_ROOT = __DIR__ . '/uploads';
const PUBLIC_UPLOAD_BASE = '/backend/uploads';
const IMAGE_MAX_UPLOAD_BYTES = 2 * 1024 * 1024;
const IMAGE_MAX_SOURCE_BYTES = 10 * 1024 * 1024;
const IMAGE_MAX_SOURCE_PIXELS = 64_000_000;
const IMAGE_MAX_WIDTH = 1600;
const IMAGE_MAX_HEIGHT = 1200;
const IMAGE_WEBP_QUALITY = 78;
const GALLERY_MAX_IMAGES = 100;
const TOURS_MAX_ITEMS = 20;
