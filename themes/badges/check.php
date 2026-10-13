<?php
declare(strict_types=1);

// Insignia de verificación «Círculo con marca». `svg` es el interior de un <svg viewBox="0 0 24 24">: .b-fill se rellena con el color de acento,
// .b-ink dibuja la marca interior con el color de fondo de la paleta y .b-stroke es un trazo con el color de acento.
return [
    'order' => 10,
    'name' => 'Círculo con marca',
    'svg' => '<circle class="b-fill" cx="12" cy="12" r="10"/><path class="b-ink" d="M7.4 12.4l3 3 6.2-6.6"/>',
];
