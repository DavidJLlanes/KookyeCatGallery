# Opciones del bloque de galería

Móvil (hasta 768 px) y escritorio tienen diseños, columnas y cantidades independientes.
La paleta y los diez efectos hover se aplican a todos los diseños. Los efectos actúan sobre
la imagen, conservando la posición y la transformación de la tarjeta; también funcionan
al enfocar con el teclado y respetan la preferencia de movimiento reducido.

| Diseño estándar | Proporción seleccionable | Columnas | Fotos por página | Forma de paginación |
| --- | --- | --- | --- | --- |
| Masonry | No, conserva la original | Sí | Sí | Sí |
| Cuadrícula | Sí | Sí | Sí | Sí |
| Mosaico cromático | No, conserva la original | Sí | Sí | Sí |
| Editorial asimétrica | No, conserva la original | Sí, densidad de las filas | Sí | Sí |
| Filas por categoría | No, tarjetas cuadradas | No, filas horizontales | No, todas en su fila | No |
| Álbum desordenado | No, tarjetas cuadradas | Sí, columnas de la composición | Sí | Sí |
| Sala de exposición | No, conserva la original | No, una foto centrada | Sí | Sí |
| Hoja de contactos | Sí | Sí | Sí | Sí |
| Secuencia narrativa | No, conserva la original | Sí, secuencia tras la foto principal | Sí | Sí |
| Trípticos | No, composición propia | No, grupos de tres | Sí | Sí |

Las opciones compartidas de proporción y paginación permanecen disponibles si las usa
al menos uno de los dos diseños. La forma de paginación se desactiva cuando ambos muestran
«Todas», o cuando ninguno utiliza páginas. En Cuadrícula, «Según el diseño» usa la proporción cuadrada predeterminada;
en Hoja de contactos adapta la
proporción original al intervalo de una hoja de contactos (0,7 a 1,4). El antiguo valor «Masonry» de este selector se migra a
«Según el diseño», sin cambiar el diseño de galería seleccionado.

Las galerías premium sustituyen ambos diseños estándar. Baraja, Cover Flow, Tambor,
Cilindro, Polaroids y Tinder tienen su propia composición y navegación: no usan las opciones
de proporción, columnas, cantidad por página ni forma de paginación estándar.
Burbujas y Cuadrados sí usan la cantidad como **máximo por página**. Si el espacio no permite
mantener la separación y un tamaño útil, muestran menos fotos y paginan el resto.
«Todas» utiliza la capacidad de la pantalla; no superpone todas las fotos en un espacio insuficiente.

Un campo desactivado muestra el motivo y conserva su valor. Cambiar a un diseño compatible
recupera ese valor. El servidor aplica las mismas reglas al guardar, incluso si una petición
intenta modificar ajustes incompatibles.

## Mantenimiento y pruebas

`inc/gallery-capabilities.php` declara las capacidades de los diez diseños estándar.
Las capacidades premium proceden de `inc/premium-galleries.php`. El panel recibe este
contrato como JSON; no mantiene otra lista de compatibilidades en JavaScript.
La geometría estándar está en `assets/js/gallery-layout.js`, la paginación y los filtros en
`assets/js/main.js`, y los efectos comunes en `assets/css/gallery-layout.css`.

- `php tests/gallery-options.php`: reglas por dispositivo, 100 combinaciones estándar,
  ocho galerías premium, conservación de valores y guardado real.
- `node tests/gallery-options.cjs`: formulario PHP y JavaScript reales, controles enviados
  y cambios de diseño.
- `node tests/gallery-layout.cjs`: diez diseños, cuatro anchos, límites configurados,
  proporciones, solapes, filtros y temas de cabecera.
- `node tests/gallery-behavior.cjs`: límites, última página, favoritos, filtro por ubicación,
  cambio entre filas y paginación sin recargar, y visor.
- `node tests/premium-hover.cjs`: diez efectos en los dieciocho diseños, con estados de
  foco reales y sin sustituir los efectos por CSS de prueba.
- `node tests/premium-*.cjs`: navegación, filtros, geometría y gestos de cada premium.

Las pruebas de navegador requieren Playwright y Chromium. Se puede indicar un navegador
instalado con `TEST_BROWSER=/ruta/a/chromium`.
