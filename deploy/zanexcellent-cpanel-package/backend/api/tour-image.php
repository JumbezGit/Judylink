<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Bootstrap.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$image = save_upload('image', 'tours');
if ($image === null) {
    Response::error('Tour image is required');
}

Response::json(['ok' => true, 'image_path' => $image], 201);

