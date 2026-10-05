<?php

declare(strict_types=1);

require __DIR__ . '/inc/helpers.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

echo json_encode(['version' => gallery_content_version(__DIR__)], JSON_THROW_ON_ERROR);
