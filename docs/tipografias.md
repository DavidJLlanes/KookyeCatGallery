# Tipografías de la galería

En **Administración → Diseño → Tipografías** puedes elegir entre 30 parejas. Cada tarjeta enseña un título y un párrafo con las fuentes reales. Puedes filtrar por estilo y la pareja activa queda marcada; el cambio se aplica a la web al pulsar **Guardar configuración**.

Una pareja asigna una familia a los títulos y otra al texto general. Se utiliza en las cabeceras, las galerías estándar y premium, las fichas de fotografías y las páginas legales, tanto en móvil como en escritorio. El valor inicial, **Editorial clásico**, mantiene Cormorant Garamond para los títulos e Inter para el texto.

Las combinaciones agrupan tres enfoques:

- **Editoriales:** serif con personalidad para titulares, acompañadas por texto legible; incluyen opciones para fotolibros, reportajes, retratos y paisaje.
- **Modernas:** titulares sans más limpios o geométricos, con textos sans o serif según la composición.
- **Expresivas:** combinaciones con monoespaciada para archivos, series conceptuales y portadas más gráficas.

El conjunto utiliza 17 familias de código abierto. Los archivos WOFF2 de los subconjuntos latino y latino extendido viven en `assets/fonts/`, junto a una copia de la licencia SIL Open Font License de cada familia. La hoja `assets/css/typography.css` las registra con `font-display: swap`; el navegador descarga únicamente las familias que aparecen en la página. No se solicitan fuentes a Google Fonts cuando una persona visita la web o el panel.

Las opciones permitidas y las 30 combinaciones están definidas en `inc/site-typography.php`. El servidor valida el identificador elegido antes de guardarlo en la configuración privada: no acepta nombres de fuente ni direcciones arbitrarias enviados por el navegador.

Al actualizar la web, copia también `assets/fonts/`, `assets/css/typography.css`, `assets/css/font-picker.css` y `assets/js/font-picker.js`. Los archivos de fuentes y sus licencias forman parte de la aplicación; la selección guardada sigue en la carpeta privada de configuración.
