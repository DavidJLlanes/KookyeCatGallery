<?php
declare(strict_types=1);

// Insignia de verificación «Hoja». `svg` es el interior de un <svg viewBox="0 0 24 24">: .b-fill se rellena con el color de acento,
// .b-ink dibuja la marca interior con el color de fondo de la paleta y .b-stroke es un trazo con el color de acento.
return [
    'order' => 120,
    'name' => 'Hoja',
    'svg' => '<path class="b-fill b-round" d="M20.5 3.5C10 3.5 4 8.8 4 15.2c0 1.6.5 3 1.4 4.3 1-5.6 4.8-9.5 10.3-11.2-4.7 2.5-7.7 6-9.1 11.6 1.2.7 2.7 1 4.1 1 6.8 0 10.6-6.7 9.8-17.4z"/>',
];
