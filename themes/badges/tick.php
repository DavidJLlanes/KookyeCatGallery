<?php
declare(strict_types=1);

// Insignia de verificación «Solo la marca». `svg` es el interior de un <svg viewBox="0 0 24 24">: .b-fill se rellena con el color de acento,
// .b-ink dibuja la marca interior con el color de fondo de la paleta y .b-stroke es un trazo con el color de acento.
return [
    'order' => 130,
    'name' => 'Solo la marca',
    'svg' => '<path class="b-stroke" d="M4.8 12.6l4.8 4.8L19.4 6.8"/>',
];
