<?php
declare(strict_types=1);

return [
    'description' => 'Ajustes nuevos: «Mostrar el logo junto a la foto de perfil» (show_logo) y la disposición de «El proyecto» (project_layout). Un archivo guardado antes de que existieran los conserva como estaban: logo visible e imagen con texto.',
    'when' => 'load',
    'apply' => static function (array $data): array {
        if (!array_key_exists('show_logo', $data)) $data['show_logo'] = true;
        if (!is_string($data['project_layout'] ?? null) || $data['project_layout'] === '') $data['project_layout'] = 'image';
        return $data;
    },
];
