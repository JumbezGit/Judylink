<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Bootstrap.php';

$pdo = Database::pdo();
$method = $_SERVER['REQUEST_METHOD'];
$toursMaxItems = defined('TOURS_MAX_ITEMS') ? TOURS_MAX_ITEMS : 20;

$columns = [
    'slug', 'name', 'category', 'location', 'duration', 'group_size', 'rating', 'review_count',
    'price', 'currency', 'description', 'highlights', 'includes', 'excludes', 'what_to_bring',
    'difficulty', 'languages', 'pickup', 'cancellation', 'image_path', 'gallery', 'itinerary',
    'faqs', 'tags', 'is_popular', 'display_order',
];

if ($method === 'GET') {
    if (!empty($_GET['summary'])) {
        require_admin();
        $siteId = request_site_id();
        ensure_tours_popular_column($pdo);
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM tours WHERE site_id = ? AND is_active = 1');
        $countStmt->execute([$siteId]);
        $withoutImageStmt = $pdo->prepare("SELECT COUNT(*) FROM tours WHERE site_id = ? AND is_active = 1 AND (image_path IS NULL OR image_path = '')");
        $withoutImageStmt->execute([$siteId]);
        $imageRows = $pdo->prepare('SELECT image_path, gallery FROM tours WHERE site_id = ? AND is_active = 1');
        $imageRows->execute([$siteId]);
        $imageRows = $imageRows->fetchAll();
        $imageTotal = 0;
        foreach ($imageRows as $row) {
            if (trim((string) ($row['image_path'] ?? '')) !== '') {
                $imageTotal++;
            }
            $gallery = json_decode((string) ($row['gallery'] ?? '[]'), true);
            if (is_array($gallery)) {
                $imageTotal += count($gallery);
            }
        }
        Response::json([
            'ok' => true,
            'count' => (int) $countStmt->fetchColumn(),
            'withoutImages' => (int) $withoutImageStmt->fetchColumn(),
            'totalImages' => $imageTotal,
            'max' => $toursMaxItems,
        ]);
    }

    $siteId = request_site_id();
    ensure_tours_popular_column($pdo);
    normalize_popular_tours($pdo, $siteId);

    if (!empty($_GET['slug'])) {
        $stmt = $pdo->prepare('SELECT * FROM tours WHERE site_id = ? AND slug = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$siteId, $_GET['slug']]);
        $tour = $stmt->fetch();
        if (!$tour) {
            Response::error('Tour not found', 404);
        }
        Response::json(['ok' => true, 'tour' => decode_tour($tour)]);
    }

    $stmt = $pdo->prepare('SELECT * FROM tours WHERE site_id = ? AND is_active = 1 ORDER BY display_order, id');
    $stmt->execute([$siteId]);
    $tours = array_map('decode_tour', $stmt->fetchAll());
    Response::json([
        'ok' => true,
        'tours' => $tours,
        'count' => count($tours),
        'max' => $toursMaxItems,
    ]);
}

if ($method === 'PATCH') {
    require_admin();
    $siteId = request_site_id();
    ensure_tours_popular_column($pdo);
    normalize_popular_tours($pdo, $siteId);
    $id = (int) ($_GET['id'] ?? 0);
    if ($id < 1) {
        Response::error('Tour id is required');
    }

    $data = request_json();
    if (array_key_exists('is_popular', $data)) {
        $isPopular = as_bool($data['is_popular']);
        if ($isPopular === 1) {
            ensure_popular_limit($pdo, $siteId, $id);
        }
        $stmt = $pdo->prepare('UPDATE tours SET is_popular = ? WHERE id = ? AND site_id = ?');
        $stmt->execute([$isPopular, $id, $siteId]);
        Response::json(['ok' => true]);
    }

    Response::error('No supported fields to update');
}

if ($method === 'POST' || $method === 'PUT') {
    require_admin();
    $siteId = request_site_id();
    ensure_tours_popular_column($pdo);
    normalize_popular_tours($pdo, $siteId);
    $data = request_json();
    if (empty($data['name'])) {
        Response::error('Tour name is required');
    }
    $data['slug'] = $data['slug'] ?? slugify((string) $data['name']);

    foreach (['highlights', 'includes', 'excludes', 'what_to_bring', 'languages', 'gallery', 'itinerary', 'faqs', 'tags'] as $field) {
        $data[$field] = json_field($data[$field] ?? []);
    }
    $data['pickup'] = as_bool($data['pickup'] ?? true);
    $data['is_popular'] = as_bool($data['is_popular'] ?? false);

    if ($method === 'POST') {
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM tours WHERE site_id = ? AND is_active = 1');
        $countStmt->execute([$siteId]);
        if ((int) $countStmt->fetchColumn() >= $toursMaxItems) {
            Response::error("Tour limit reached. Maximum {$toursMaxItems} tours are allowed.");
        }

        if ($data['is_popular'] === 1) {
            ensure_popular_limit($pdo, $siteId, 0);
        }
        $fields = 'site_id, ' . implode(', ', $columns);
        $placeholders = ':site_id, :' . implode(', :', $columns);
        $stmt = $pdo->prepare("INSERT INTO tours ({$fields}) VALUES ({$placeholders})");
    } else {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id < 1) {
            Response::error('Tour id is required');
        }
        if ($data['is_popular'] === 1) {
            ensure_popular_limit($pdo, $siteId, $id);
        }
        $assignments = implode(', ', array_map(fn ($column) => "{$column} = :{$column}", $columns));
        $stmt = $pdo->prepare("UPDATE tours SET {$assignments} WHERE id = :id AND site_id = :site_id");
        $data['id'] = $id;
    }

    $defaults = [
        'category' => 'Activities',
        'location' => 'Zanzibar',
        'duration' => 'Flexible',
        'group_size' => 'Private or group',
        'rating' => 4.9,
        'review_count' => 0,
        'price' => 0,
        'currency' => 'USD',
        'description' => '',
        'difficulty' => 'Easy',
        'cancellation' => 'Free cancellation up to 24 hours before',
        'image_path' => '',
        'display_order' => 0,
    ];
    $payload = array_intersect_key($data + $defaults, array_flip($columns));
    $payload['site_id'] = $siteId;
    if (isset($data['id'])) {
        $payload['id'] = $data['id'];
    }
    $stmt->execute($payload);
    Response::json(['ok' => true, 'id' => $method === 'POST' ? (int) $pdo->lastInsertId() : (int) $data['id']], $method === 'POST' ? 201 : 200);
}

if ($method === 'DELETE') {
    require_admin();
    $siteId = request_site_id();
    ensure_tours_popular_column($pdo);
    $id = (int) ($_GET['id'] ?? 0);
    if ($id < 1) {
        Response::error('Tour id is required');
    }
    $stmt = $pdo->prepare('UPDATE tours SET is_active = 0 WHERE id = ? AND site_id = ?');
    $stmt->execute([$id, $siteId]);
    Response::json(['ok' => true]);
}

Response::error('Method not allowed', 405);

function ensure_tours_popular_column(PDO $pdo): void
{
    $stmt = $pdo->query("SHOW COLUMNS FROM tours LIKE 'is_popular'");
    if ($stmt->fetch()) {
        return;
    }

    $pdo->exec('ALTER TABLE tours ADD COLUMN is_popular TINYINT(1) NOT NULL DEFAULT 0 AFTER tags');
    $pdo->exec('ALTER TABLE tours ADD INDEX idx_tours_popular (is_popular, display_order)');
}

function ensure_popular_limit(PDO $pdo, int $siteId, int $currentId): void
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tours WHERE site_id = ? AND is_active = 1 AND is_popular = 1 AND id <> ?');
    $stmt->execute([$siteId, $currentId]);
    if ((int) $stmt->fetchColumn() >= 3) {
        Response::error('Only three tours can be selected for Most Popular tours.');
    }
}

function normalize_popular_tours(PDO $pdo, int $siteId): void
{
    $stmt = $pdo->prepare('SELECT id FROM tours WHERE site_id = ? AND is_active = 1 AND is_popular = 1 ORDER BY display_order, id');
    $stmt->execute([$siteId]);
    $popularIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if (count($popularIds) <= 3) {
        return;
    }

    $extraIds = array_slice($popularIds, 3);
    $placeholders = implode(',', array_fill(0, count($extraIds), '?'));
    $stmt = $pdo->prepare("UPDATE tours SET is_popular = 0 WHERE site_id = ? AND id IN ({$placeholders})");
    $stmt->execute(array_merge([$siteId], $extraIds));
}
