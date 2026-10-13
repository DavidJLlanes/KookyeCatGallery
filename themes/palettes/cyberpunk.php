<?php
declare(strict_types=1);

return [
    'order' => 40,
    'name' => 'Cyberpunk · neón',
    'mode' => 'dark',
    'vars' => [
        'bg' => '#090619', 'bg-soft' => '#140c2a', 'bg-card' => '#201237', 'fg' => '#f5f3ff',
        'fg-soft' => '#d0c3f0', 'fg-dim' => '#b7a8d4', 'accent' => '#00f5dc', 'accent-soft' => '#00f5dc20', 'line' => '#ff3ac84d',
    ],
    'extra_vars' => ['serif' => 'var(--sans)'],
    'css' => [
        '{p} .hero__title { text-shadow:3px 0 #ff3ac8,-3px 0 #00f5dc; }',
        '{p} .hero__ornament { border-color:#ff3ac8; }',
    ],
];
