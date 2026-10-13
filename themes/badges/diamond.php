<?php
declare(strict_types=1);

// Insignia de verificación «Diamante». `svg` es el interior de un <svg viewBox="0 0 24 24">: .b-fill se rellena con el color de acento,
// .b-ink dibuja la marca interior con el color de fondo de la paleta y .b-stroke es un trazo con el color de acento.
return [
    'order' => 60,
    'name' => 'Diamante',
    'svg' => '<path class="b-fill b-round" d="M7 3.5h10l4.5 5.7L12 21 2.5 9.2z"/><path class="b-ink" d="M2.8 9.2h18.4M9.2 9.2L12 3.6l2.8 5.6M9.2 9.2L12 20.6l2.8-11.4"/>',
];
