<?php
declare(strict_types=1);

return [
    'description' => 'El ajuste «Título de la web» (site_title) desaparece: el nombre de la web es uno solo (text_52179dc42df7efe5). Si había un título propio y aún no había nombre, pasa a ser el nombre.',
    'when' => 'always',
    'apply' => static function (array $data): array {
        $texts = $data['texts'] ?? [];
        if (!is_array($texts)) return $data;
        $key = 'text_52179dc42df7efe5';
        $legacy = is_string($data['site_title'] ?? null) ? trim($data['site_title']) : '';
        if ($legacy !== '' && (!is_string($texts[$key] ?? '') || trim((string) ($texts[$key] ?? '')) === '')) $texts[$key] = $legacy;
        unset($data['site_title']);
        $data['texts'] = $texts;
        return $data;
    },
];
