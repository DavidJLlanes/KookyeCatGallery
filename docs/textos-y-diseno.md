# Textos y diseño

Entra en `/admin.php`, inicia sesión y usa las secciones **Diseño** y **Textos**. Guarda para aplicar los cambios a la web pública.

- Paletas: Elegante, Noche (negro y blanco), Luz (blanco y tinta), Cyberpunk (neón) Japón (papel y carmesí), Bosque (verde y marfil) y Océano (azul profundo y turquesa).
- Galería: adaptativa, masonry, cuadrada, horizontal 4:3 o vertical 3:4. Todas usan las columnas seleccionadas: 1–4 en móvil y 1–10 en escritorio.
- Fotos visibles a la vez: ajuste independiente para móvil y escritorio (6 a 100, o «Todas»). No depende de las columnas; si hay más fotos, se paginan. Por defecto: 12 en móvil y 20 en escritorio.
- Identidad: en **Textos → Nombre y marca** puedes cambiar el nombre de la web, quién hace las fotos, la presentación, el enlace personal y el crédito del pie. El año del copyright se actualiza automáticamente. El nombre de la app instalada (`manifest.json`) no cambia.
- Galería premium: en **Diseño → Galería premium** se elige un diseño completo. Los mensajes visibles propios de cada galería aparecen en **Textos → Textos de las plantillas**. Cada diseño conserva los ajustes estándar que utiliza y desactiva los demás. Ver [galerías premium](galerias-premium.md).
- Estructura de la página: en **Diseño → Estructura de la página** se ordenan con flechas los bloques de la portada (categorías, galería, mapa, «El proyecto» y enlaces sociales) y se activan o desactivan los opcionales. La galería siempre se muestra. El orden se guarda en `section_order`.
- Cabecera: la cabecera de móvil y la de escritorio se pueden ocultar por separado (`show_header_mobile`, `show_header_desktop`).
- Panel: el área privada usa una barra lateral en escritorio y una barra inferior en móvil; Perfil, Diseño y Textos son secciones independientes.
- Forma de la paginación: los números y flechas de página pueden ser círculos (por defecto) o cuadrados (`pagination_shape`). Los botones siempre tienen la misma anchura y altura, así que nunca se ovalan.
- Cabeceras: nueve para móvil y nueve para escritorio, con selectores independientes de la cuadrícula y de la paleta. Móvil corresponde a hasta 768 px y escritorio a partir de 769 px.
- Modos de galería: masonry, cuadrícula, mosaico cromático, editorial asimétrica, filas por categoría, álbum desordenado, sala de exposición, hoja de contactos, secuencia narrativa y trípticos. Disponibles en móvil y escritorio; se eligen independientemente.
- Efectos: Suave, Acercamiento, Elevación, Revelado, Virado de color, Marco luminoso, Desplazamiento, Perspectiva, Enfoque y Destello.
- Textos: en **Textos → Nombre y marca** edita el nombre de la web, el nombre del fotógrafo, sus descripciones y el crédito del pie. **Textos de las plantillas** cambia según la cabecera y la galería elegidas. Los controles técnicos y etiquetas de accesibilidad se generan automáticamente; títulos, descripciones y categorías de fotos se editan en **Gestionar fotos**.

La configuración se guarda en `var/site-settings.json`, fuera del directorio público y de los archivos desplegados. El usuario que ejecuta PHP necesita permisos de escritura en esa carpeta. El archivo se crea al guardar por primera vez. Un error de guardado se muestra en el panel y conserva el archivo anterior. No se necesitan migraciones de base de datos.

Los valores por defecto usan la paleta Elegante y las cabeceras Perfil social en móvil y Tipográfica en escritorio; las columnas iniciales son tres en ambas pantallas. La opción adaptativa usa imágenes cuadradas en móvil y masonry en escritorio. Las cuadrículas de proporción fija recortan la miniatura; el visor abre la fotografía completa.

Para añadir textos en el futuro, incorpora una clave estable a `inc/site-texts.php` y utiliza `site_text_html()` en HTML o `site_text()` en contextos que ya escapen el valor. Los controles JavaScript usan `siteText()` y `siteTextHTML()`. No cambies claves existentes al cambiar sus valores por defecto.

Validación: `php tests/site-settings.php` y comprobaciones de sintaxis PHP/JavaScript del workflow de GitHub.
