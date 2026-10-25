<?php

declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'hyena_tour_travel';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

// const APP_URL = 'http://localhost:5173';
const APP_URL = 'http://localhost:8443';
const ADMIN_TOKEN = 'change-this-admin-token';
const ADMIN_USERNAME = 'Jumbez';
const ADMIN_PASSWORD = 'Jumbez*123';

// ScayPay checkout. Keep sandbox enabled until testing is complete.
const SCAYPAY_ENVIRONMENT = 'sandbox';
const SCAYPAY_API_URL = 'https://api.scaypay.com/v1';
const SCAYPAY_SECRET_KEY = '';
const SCAYPAY_WEBHOOK_URL = '';
const SCAYPAY_WEBHOOK_SECRET = '';
const SCAYPAY_CALLBACK_URL = APP_URL . '/plans';

const UPLOAD_ROOT = __DIR__ . '/uploads';
const PUBLIC_UPLOAD_BASE = '/backend/uploads';
const IMAGE_MAX_UPLOAD_BYTES = 2 * 1024 * 1024;
const IMAGE_MAX_WIDTH = 1600;
const IMAGE_MAX_HEIGHT = 1200;
const IMAGE_WEBP_QUALITY = 78;
const GALLERY_MAX_IMAGES = 100;
const TOURS_MAX_ITEMS = 20;
