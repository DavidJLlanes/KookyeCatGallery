# Textos y diseño

Entra en `/admin.php`, inicia sesión y abre **Textos y diseño**. Guarda para aplicar los cambios a la web pública.

- Paletas: Elegante, Noche (negro y blanco), Luz (blanco y tinta), Cyberpunk (neón) Japón (papel y carmesí), Bosque (verde y marfil) y Océano (azul profundo y turquesa).
- Galería: adaptativa, masonry, cuadrada, horizontal 4:3 o vertical 3:4. Todas usan las columnas seleccionadas: 1–4 en móvil y 1–10 en escritorio.
- Fotos visibles a la vez: ajuste independiente para móvil y escritorio (6 a 100, o «Todas»). No depende de las columnas; si hay más fotos, se paginan. Por defecto: 12 en móvil y 20 en escritorio.
- Título de la web: en **Diseño → Identidad**. Sustituye al título original en la pestaña del navegador, las cabeceras, el pie y los textos que lo mencionan. Vacío = título original. El nombre de la app instalada (`manifest.json`) no cambia.
- Galería premium: en **Diseño → Galería premium** se elige un diseño completo (por ahora, «Estilo Baraja») que sustituye a la galería estándar. Con una activa se ignoran y se desactivan los ajustes de cuadrícula, galerías de móvil y escritorio, forma de la paginación, columnas y fotos visibles a la vez. Ver [galerías premium](galerias-premium.md).
- Estructura de la página: en **Diseño → Estructura de la página** se ordenan con flechas los bloques de la portada (categorías, galería, mapa, «El proyecto» y enlaces sociales) y se activan o desactivan los opcionales. La galería siempre se muestra. El orden se guarda en `section_order`.
- Cabecera: la cabecera de móvil y la de escritorio se pueden ocultar por separado (`show_header_mobile`, `show_header_desktop`).
- Panel: el área privada usa una barra lateral en escritorio y una barra inferior en móvil; Perfil y Diseño son entradas de menú separadas, con los estilos en `assets/css/admin.css`.
- Forma de la paginación: los números y flechas de página pueden ser círculos (por defecto) o cuadrados (`pagination_shape`). Los botones siempre tienen la misma anchura y altura, así que nunca se ovalan.
- Cabeceras: nueve para móvil y nueve para escritorio, con selectores independientes de la cuadrícula y de la paleta. Móvil corresponde a hasta 768 px y escritorio a partir de 769 px.
- Modos de galería: masonry, cuadrícula, mosaico cromático, editorial asimétrica, filas por categoría, álbum desordenado, sala de exposición, hoja de contactos, secuencia narrativa y trípticos. Disponibles en móvil y escritorio; se eligen independientemente.
- Efectos: Suave, Acercamiento, Elevación, Revelado, Virado de color, Marco luminoso, Desplazamiento, Perspectiva, Enfoque y Destello.
- Textos: abre los grupos para editar el contenido de inicio, perfil, proyecto, contacto, controles, metadatos y páginas legales. Los títulos, descripciones y categorías de las fotos se editan en **Gestionar fotos**. Se conserva el formato de la página; los campos admiten texto plano y pueden quedar vacíos. En los textos de recuento, conserva `{count}` para mostrar el número de fotos.

La configuración se guarda en `var/site-settings.json`, fuera del directorio público y de los archivos desplegados. El usuario que ejecuta PHP necesita permisos de escritura en esa carpeta. El archivo se crea al guardar por primera vez. Un error de guardado se muestra en el panel y conserva el archivo anterior. No se necesitan migraciones de base de datos.

Los valores por defecto usan la paleta Elegante y las cabeceras Perfil social en móvil y Tipográfica en escritorio; las columnas iniciales son tres en ambas pantallas. La opción adaptativa usa imágenes cuadradas en móvil y masonry en escritorio. Las cuadrículas de proporción fija recortan la miniatura; el visor abre la fotografía completa.

Para añadir textos en el futuro, incorpora una clave estable a `inc/site-texts.php` y utiliza `site_text_html()` en HTML o `site_text()` en contextos que ya escapen el valor. Los controles JavaScript usan `siteText()` y `siteTextHTML()`. No cambies claves existentes al cambiar sus valores por defecto.

Validación: `php tests/site-settings.php` y comprobaciones de sintaxis PHP/JavaScript del workflow de GitHub.
