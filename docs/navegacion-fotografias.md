# Navegación entre fotografías

Al abrir una fotografía con enlace propio (`/foto/slug`), puedes pasar a la siguiente deslizando hacia la izquierda sobre la imagen y volver a la anterior deslizando hacia la derecha. En escritorio puedes pulsar los botones laterales, arrastrar la imagen con el botón izquierdo del ratón o usar las flechas del teclado.

Los mismos controles funcionan en el visor de pantalla completa. Al cambiar de foto, el visor sigue abierto.

**Cambio suave, sin recargar.** Pasar de una foto a otra (flechas de la ficha o del visor, teclado o gestos) no recarga la página: `assets/js/main.js` (`initPhotoPage`) pide al servidor la ficha de la foto vecina, precarga y decodifica su imagen y entonces cambia todo en el sitio: imagen, título, descripción, coordenadas, enlaces para compartir, opciones privadas de edición, fotos relacionadas, flechas, título de la pestaña, dirección canónica y etiquetas para redes, y añade la nueva dirección al historial (en el visor, con `?viewer=1`). Así los datos siguen siendo los del servidor.

- **En pantalla completa**, el cambio es un **deslizamiento continuo, como un carrusel** (`slideImage`): la foto nueva entra por el lado del que viene y la anterior sale por el contrario, pegadas, a opacidad completa y con la misma geometría (ambas imágenes están centradas con posición absoluta en el escenario), así que **no se ve el fondo ni hay franjas o saltos de tamaño**, con cualquier tamaño de foto. Lo mismo hace el visor de la galería (fotos sin ficha propia). Con «reducir movimiento» el cambio es instantáneo.
- **Con el dedo (o el ratón), la foto sigue al gesto:** al arrastrar en horizontal la foto se mueve con el dedo y, por el lado hacia el que va, **ya asoma la vecina** (precargada). Al soltar pasado el umbral (50 px) la animación continúa desde donde estaba el dedo; si el gesto se queda corto, todo vuelve a su sitio con una animación suave. En la primera y la última foto, hacia el lado sin foto, la imagen se resiste (se mueve un tercio) y vuelve.
- **La foto empieza a deslizar sin esperar a la ficha:** se usa la imagen vecina ya precargada y, mientras tanto, se pide la ficha para actualizar textos y datos.
- **En la ficha** (sin visor), el navegador funde la página entera con una transición de vista breve si la admite; si no, el cambio es instantáneo.
- **Pulsaciones rápidas** se encadenan: se hace la primera y después la última pulsación, sin perder ni mezclar cambios.
- **Atrás y Adelante** del navegador muestran la foto de esa dirección (y el visor si la dirección lleva `?viewer=1`) sin recargar.
- **Respaldo:** si la petición o la imagen fallan, se navega a la página como siempre, y sin JavaScript las flechas siguen siendo enlaces.
- **Recarga con el visor abierto** (`?viewer=1`): el servidor marca `<html class="viewer-pending">` y el CSS oculta lo de detrás hasta que el visor se abre, para que no se vea la ficha un instante.

La navegación sigue el orden de la galería, desde las fotografías más recientes a las más antiguas. Las fotos sin enlace propio se omiten en las fichas. La primera y la última tienen deshabilitada la dirección que no tiene fotografía disponible. En la galería, las fotografías sin enlace propio conservan el visor rápido y su navegación por las imágenes visibles.

El gesto debe ser horizontal y recorrer al menos 50 píxeles. Los movimientos verticales permiten desplazar la página y los gestos con varios dedos no cambian de foto. Los botones de anterior y siguiente siguen funcionando sin JavaScript en las fichas.

Validación: `php tests/photo-navigation.php`, `node tests/photo-navigation.cjs` y `node tests/photo-transition.cjs` (requiere PHP y Playwright con Chromium). `photo-transition` mide cada fotograma del cambio y comprueba que las dos fotos cubren todo el ancho (sin franjas), que siempre hay una foto a opacidad completa, que la foto sigue al dedo y deja ver la vecina, que un gesto corto vuelve a su sitio, la resistencia en los extremos, que no se recarga la página, las pulsaciones rápidas, Atrás, el cierre del visor, las flechas de la ficha y la recarga con `?viewer=1`. El workflow de GitHub ejecuta las tres pruebas.
