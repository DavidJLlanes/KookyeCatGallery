# Navegación entre fotografías

Al abrir una fotografía con enlace propio (`/foto/slug`), puedes pasar a la siguiente deslizando hacia la izquierda sobre la imagen y volver a la anterior deslizando hacia la derecha. En escritorio puedes pulsar los botones laterales, arrastrar la imagen con el botón izquierdo del ratón o usar las flechas del teclado.

Los mismos controles funcionan en el visor de pantalla completa. Al cambiar de foto, el visor sigue abierto. Cada fotografía carga una página nueva desde el servidor, con su título, descripción, enlaces para compartir y opciones privadas de edición actualizados.

La navegación sigue el orden de la galería, desde las fotografías más recientes a las más antiguas. Las fotos sin enlace propio se omiten en las fichas. La primera y la última tienen deshabilitada la dirección que no tiene fotografía disponible. En la galería, las fotografías sin enlace propio conservan el visor rápido y su navegación por las imágenes visibles.

El gesto debe ser horizontal y recorrer al menos 50 píxeles. Los movimientos verticales permiten desplazar la página y los gestos con varios dedos no cambian de foto. Los botones de anterior y siguiente siguen funcionando sin JavaScript en las fichas.

Validación: `php tests/photo-navigation.php` y `node tests/photo-navigation.cjs` (requiere PHP y Playwright con Chromium). El workflow de GitHub ejecuta ambas pruebas.
