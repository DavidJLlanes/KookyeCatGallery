<?php
declare(strict_types=1);

// Insignia de verificación «Sello redondeado». `svg` es el interior de un <svg viewBox="0 0 24 24">: .b-fill se rellena con el color de acento,
// .b-ink dibuja la marca interior con el color de fondo de la paleta y .b-stroke es un trazo con el color de acento.
return [
    'order' => 20,
    'name' => 'Sello redondeado',
    'svg' => '<rect class="b-fill" x="4.2" y="4.2" width="15.6" height="15.6" rx="3.4"/><rect class="b-fill" x="4.2" y="4.2" width="15.6" height="15.6" rx="3.4" transform="rotate(45 12 12)"/><path class="b-ink" d="M8 12.3l2.9 2.9 5.2-5.6"/>',
];
