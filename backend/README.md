# Hyena Tour & Travel PHP Backend

This backend stores bookings and lets an admin manage gallery images and tour information with MySQL.

## Setup

1. Create a MySQL database named `hyena_tour_travel`.
2. Import `backend/database/schema.sql`.
3. Copy `backend/config.example.php` to `backend/config.php` and update database credentials plus `ADMIN_TOKEN`.
4. Put the project in your web server root, for example XAMPP `htdocs`, or map Apache to this project folder.
5. Use the admin panel at `/backend/secure-operation-4f2a/`.

The footer logo opens the admin login after 8 clicks. Default login:

- Username: `Hyena`
- Password: `Nassir@2020`

You can change these in `backend/config.php`.

## API

- `POST /backend/api/bookings.php` stores a public booking.
- `GET /backend/api/bookings.php` lists bookings. Requires `X-Admin-Token`.
- `PATCH /backend/api/bookings.php?id=1` updates booking status/payment status. Requires `X-Admin-Token`.
- `GET /backend/api/gallery.php` lists gallery items.
- `POST /backend/api/gallery.php` uploads a gallery image. Requires `X-Admin-Token`.
- `DELETE /backend/api/gallery.php?id=1` deletes a gallery image. Requires `X-Admin-Token`.
- `GET /backend/api/tours.php` lists active tours.
- `GET /backend/api/tours.php?slug=prison-island` returns one tour.
- `POST /backend/api/tours.php` creates a tour. Requires `X-Admin-Token`.
- `PUT /backend/api/tours.php?id=1` updates a tour. Requires `X-Admin-Token`.
- `DELETE /backend/api/tours.php?id=1` disables a tour. Requires `X-Admin-Token`.

Tour fields preserve all current frontend details: highlights, includes, excludes, what to bring, languages, gallery, itinerary, FAQs, tags, pickup, cancellation, rating, review count, price, duration, category, location, difficulty, and images.

## Per-site data API

Every approved site receives a unique slug and API key. Super admins can copy or regenerate them from **Admin > Sites > API**.

Endpoint:

```text
GET /backend/api/site-data.php?site={site-slug}&collection={collection}
```

Send the key on every request:

```text
X-Site-API-Key: judy_your_site_key
```

Create or replace a record with `POST`, `PUT`, or `PATCH`:

```json
{
  "key": "stone-town",
  "data": {
    "title": "Stone Town Tour"
  }
}
```

Add `&key=stone-town` to read or delete one record. Collections and record keys support lowercase letters, numbers, hyphens, and underscores. Data is isolated by site, and suspended sites receive `403` responses.

Every API query includes the authenticated site's database ID, including reads, updates, and deletes. Identical collection and record keys can therefore exist safely on different sites. API activity is recorded in `site_api_activity` under the same site ID. Only the configured super admin can access the global Companies and Sites screens.

## ScayPay sandbox registration payments

Company registration uses the ScayPay sandbox collection API. Configure these server-only values in `backend/config.php` before testing:

```php
const SCAYPAY_ENVIRONMENT = 'sandbox';
const SCAYPAY_API_URL = 'https://api.scaypay.com/v1';
const SCAYPAY_SECRET_KEY = 'your-sk_test-key';
const SCAYPAY_WEBHOOK_URL = 'your-sandbox-webhook-url';
const SCAYPAY_WEBHOOK_SECRET = 'your-webhook-signing-secret';
const SCAYPAY_CALLBACK_URL = APP_URL . '/plans';
```

Sandbox collections complete immediately without real money and may return directly to the callback instead of displaying the hosted card checkout. Live card payments return ScayPay's hosted `checkout_url`. Keep sandbox and live keys separate, and never expose either key to frontend code.
