<?php

declare(strict_types=1);

// Production cPanel database
const DB_HOST = 'localhost';
const DB_NAME = 'zanexcel_db';
const DB_USER = 'zanexcel_db';
const DB_PASS = 'Jumbez*1998';
const DB_CHARSET = 'utf8mb4';

// Production domain
const APP_URL = 'https://zanexcellent.com';

const ADMIN_TOKEN = 'zanexcellent-admin-2026-09-14-8f3c7b6a0d2e49f1b5c8a73491e6d0aa';

const ADMIN_USERNAME = 'Jumbez';
const ADMIN_PASSWORD = 'Jumbez*123';

// ScayPay sandbox credentials. Keep these values on the server only.
const SCAYPAY_ENVIRONMENT = 'sandbox';
const SCAYPAY_API_URL = 'https://api.scaypay.com/v1';
const SCAYPAY_SECRET_KEY = 'sk_test_611bb8ba63586f9c288fb3a39edf40eaf6579014';
const SCAYPAY_WEBHOOK_URL = 'https://api.scaypay.com/webhook.php?key=sk_test_611bb8ba63586f9c288fb3a39edf40eaf6579014';
const SCAYPAY_WEBHOOK_SECRET = 'whsec_74be6f763bc4ec96fe7a2e0d0fa4a6d3a7de71d8f0a31da7626901e751209805';
const SCAYPAY_CALLBACK_URL = APP_URL . '/plans';

const UPLOAD_ROOT = __DIR__ . '/uploads';
const PUBLIC_UPLOAD_BASE = '/backend/uploads';
const IMAGE_MAX_UPLOAD_BYTES = 2 * 1024 * 1024;
const IMAGE_MAX_WIDTH = 1600;
const IMAGE_MAX_HEIGHT = 1200;
const IMAGE_WEBP_QUALITY = 78;
const GALLERY_MAX_IMAGES = 100;
const TOURS_MAX_ITEMS = 20;
