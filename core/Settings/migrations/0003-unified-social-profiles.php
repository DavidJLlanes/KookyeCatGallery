<?php
declare(strict_types=1);

return [
    'description' => 'Instagram y Threads tenían campos propios (instagram_url, threads_url y sus usuarios en los textos); ahora son filas de social_links. Las URLs de ejemplo no son perfiles reales.',
    'when' => 'always',
    'apply' => static function (array $data): array {
        $socialLinks = $data['social_links'] ?? [];
        // Si no es una lista, no se toca: el validador la rechazará con su mensaje.
        if (!is_array($socialLinks)) return $data;
        $texts = is_array($data['texts'] ?? null) ? $data['texts'] : [];
        $catalog = site_text_catalog();
        $legacy = [
            ['network' => 'instagram', 'url' => (string) ($data['instagram_url'] ?? ''), 'handle' => trim((string) ($texts['text_3b20084d3d94b4ee'] ?? '')) ?: $catalog['text_3b20084d3d94b4ee']['default']],
            ['network' => 'threads', 'url' => (string) ($data['threads_url'] ?? ''), 'handle' => trim((string) ($texts['social_threads_user'] ?? '')) ?: $catalog['social_threads_user']['default']],
        ];
        $existing = array_map(static fn($row) => is_array($row) ? strtolower((string) ($row['network'] ?? '')) : '', $socialLinks);
        $legacy = array_values(array_filter($legacy, static fn($row) =>
            trim($row['url']) !== '' && trim($row['url']) !== 'https://example.com' && !in_array($row['network'], $existing, true)));
        $data['social_links'] = array_merge($legacy, $socialLinks);
        unset($data['instagram_url'], $data['threads_url']);
        return $data;
    },
];
