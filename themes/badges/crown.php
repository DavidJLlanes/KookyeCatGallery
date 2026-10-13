<?php
declare(strict_types=1);

// Insignia de verificación «Corona». `svg` es el interior de un <svg viewBox="0 0 24 24">: .b-fill se rellena con el color de acento,
// .b-ink dibuja la marca interior con el color de fondo de la paleta y .b-stroke es un trazo con el color de acento.
return [
    'order' => 70,
    'name' => 'Corona',
    'svg' => '<path class="b-fill b-round" d="M3 8l4.6 4.2L12 4.8l4.4 7.4L21 8l-1.9 11.2H4.9z"/><path class="b-ink" d="M6 16.2h12"/>',
];
