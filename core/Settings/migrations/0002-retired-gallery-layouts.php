<?php
declare(strict_types=1);

return [
    'description' => 'Los diseños de galería «immersive» y «art-walk» se retiraron: las instalaciones que los guardaron pasan a «standard».',
    'when' => 'load',
    'apply' => static function (array $data): array {
        foreach (['gallery_mobile', 'gallery_desktop'] as $key) {
            if (in_array($data[$key] ?? '', ['immersive', 'art-walk'], true)) $data[$key] = 'standard';
        }
        return $data;
    },
];
