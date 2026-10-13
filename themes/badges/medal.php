<?php
declare(strict_types=1);

// Insignia de verificación «Medalla». `svg` es el interior de un <svg viewBox="0 0 24 24">: .b-fill se rellena con el color de acento,
// .b-ink dibuja la marca interior con el color de fondo de la paleta y .b-stroke es un trazo con el color de acento.
return [
    'order' => 110,
    'name' => 'Medalla',
    'svg' => '<path class="b-fill b-round" d="M8.2 13.6L6 21.5l6-2.9 6 2.9-2.2-7.9z"/><circle class="b-fill" cx="12" cy="9" r="6.8"/><path class="b-ink" d="M9.2 9.2l2 2 3.6-3.9"/>',
];
