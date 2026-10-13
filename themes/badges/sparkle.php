<?php
declare(strict_types=1);

// Insignia de verificación «Destello». `svg` es el interior de un <svg viewBox="0 0 24 24">: .b-fill se rellena con el color de acento,
// .b-ink dibuja la marca interior con el color de fondo de la paleta y .b-stroke es un trazo con el color de acento.
return [
    'order' => 50,
    'name' => 'Destello',
    'svg' => '<path class="b-fill b-round" d="M12 2c.9 5.4 3.6 8.1 10 10-6.4 1.9-9.1 4.6-10 10-.9-5.4-3.6-8.1-10-10C8.4 10.1 11.1 7.4 12 2z"/>',
];
