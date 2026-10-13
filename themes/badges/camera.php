<?php
declare(strict_types=1);

// Insignia de verificación «Cámara». `svg` es el interior de un <svg viewBox="0 0 24 24">: .b-fill se rellena con el color de acento,
// .b-ink dibuja la marca interior con el color de fondo de la paleta y .b-stroke es un trazo con el color de acento.
return [
    'order' => 100,
    'name' => 'Cámara',
    'svg' => '<path class="b-fill b-round" d="M8 5.6l1.3-2.1h5.4L16 5.6h2.2A3.3 3.3 0 0 1 21.5 9v8.2a3.3 3.3 0 0 1-3.3 3.3H5.8a3.3 3.3 0 0 1-3.3-3.3V9a3.3 3.3 0 0 1 3.3-3.4z"/><circle class="b-ink" cx="12" cy="13" r="3.9"/>',
];
