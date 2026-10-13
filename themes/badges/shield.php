<?php
declare(strict_types=1);

// Insignia de verificación «Escudo». `svg` es el interior de un <svg viewBox="0 0 24 24">: .b-fill se rellena con el color de acento,
// .b-ink dibuja la marca interior con el color de fondo de la paleta y .b-stroke es un trazo con el color de acento.
return [
    'order' => 30,
    'name' => 'Escudo',
    'svg' => '<path class="b-fill" d="M12 2.4l8 3v6.1c0 5-3.3 8.6-8 10.1-4.7-1.5-8-5.1-8-10.1V5.4z"/><path class="b-ink" d="M8.2 12.2l2.6 2.7 5-5.3"/>',
];
