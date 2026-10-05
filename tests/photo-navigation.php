<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/photo-navigation.php';

function verify(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$photos = [['slug' => 'first'], ['slug' => ''], ['slug' => 'middle'], ['title' => 'No permalink'], ['slug' => 'last']];
$neighbors = photo_neighbors($photos, 'middle');
verify($neighbors['previous']['slug'] === 'first', 'Previous must follow gallery order and skip missing slugs.');
verify($neighbors['next']['slug'] === 'last', 'Next must follow gallery order and skip missing slugs.');
verify(photo_neighbors($photos, 'first')['previous'] === null, 'No previous before first photo.');
verify(photo_neighbors($photos, 'last')['next'] === null, 'No next after last photo.');
verify(photo_neighbors([], 'missing') === ['previous' => null, 'next' => null], 'Empty gallery.');
verify(photo_neighbors([['slug' => 'only']], 'only') === ['previous' => null, 'next' => null], 'Single photo.');
verify(photo_neighbors($photos, 'missing') === ['previous' => null, 'next' => null], 'Unknown slug.');
echo "Photo neighbors tests passed.\n";
