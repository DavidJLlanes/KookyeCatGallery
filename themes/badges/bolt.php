<?php
declare(strict_types=1);

// Insignia de verificación «Rayo». `svg` es el interior de un <svg viewBox="0 0 24 24">: .b-fill se rellena con el color de acento,
// .b-ink dibuja la marca interior con el color de fondo de la paleta y .b-stroke es un trazo con el color de acento.
return [
    'order' => 90,
    'name' => 'Rayo',
    'svg' => '<path class="b-fill b-round" d="M13.6 2L4.8 13.4h6L9.8 22l9.4-12h-6.1z"/>',
];
