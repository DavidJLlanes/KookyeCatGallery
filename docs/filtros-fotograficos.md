# Los 100 filtros fotográficos

El catálogo se ha diseñado desde cero. Hay **diez familias con diez filtros cada una**:

| Familia | Acabados |
| --- | --- |
| Vintage · película envejecida | Copias desvaídas, diapositiva de los 80, flash de los 90, negativo caducado, emulsión violeta, papel amarillo y veladura solar. |
| Instantáneas y papel | Papel crema, Polaroid chocolate, packfilm menta, instantánea rosa, fiesta de un solo uso y transferencia índigo. |
| Procesos cruzados | E-6 en C-41, C-41 en E-6 y ocho combinaciones de respuestas opuestas en los canales RGB. |
| Cine y revelado | 35 mm ámbar, teal, bleach bypass, Technicolor, western, thriller frío y difusión romántica. |
| Virados y técnicas históricas | Sepia, cianotipia azul Prusia, selenio, cobre, platino, verde botella, oro, manganeso, hierro y duotono. |
| Blanco y negro | Plata documental, Tri-X, mezcla de filtro rojo/azul, high key, low key, carbón mate, lith e infrarrojo. |
| Lomo y cámaras | Lomo LC-A, Holga, Diana, redscale, esténopo, CCD, aberración y fugas de luz. |
| Color creativo | Editorial, pastel, esmeralda, cobre, océano, melocotón/salvia, cerezo, cromo y rojo selectivo. |
| Nocturnos y neón | Tokio, tungsteno 800, sodio, mercurio, hora azul, cyber rojo, violeta, luna, jazz y neón verde/naranja. |
| Laboratorio experimental | Aerochrome simulado, solarización, negativos, posterización, duotono pop, visión nocturna y emulsión velada. |

Son interpretaciones digitales de estos procesos, no perfiles oficiales de fabricantes.

En **Filtros fotográficos**, elige una familia y un filtro. Cada miniatura usa tu propia foto; el nombre completo y la descripción están disponibles al mantener el puntero sobre ella y mediante lector de pantalla. **Intensidad** mezcla todo el resultado, incluidos grano, viñeta, fugas, difusión y halation: a 0 % se recupera la imagen con sus ajustes manuales.

## Motor

`photo-filter-engine.js` aplica curvas suaves de luminancia y curvas independientes por canal, mezcla monocroma, selección por tono, virados de tres tintas y efectos espaciales. Los procesos cruzados llevan respuestas RGB distintas en sombras, medios y luces. Las emulsiones vintage tienen respuestas de película y papel envejecido, además de textura. La difusión y el halation usan mapas desenfocados de las luces para extender el brillo a los píxeles vecinos.

Miniaturas, vista previa y exportación usan la misma receta y el mismo motor. Los efectos de lente se sitúan en coordenadas relativas a la foto; el grano y las motas son deterministas. Las imágenes guardadas previamente conservan sus píxeles; la nueva colección afecta a las siguientes ediciones.

## Comprobaciones

`node tests/photo-presets.cjs` comprueba los 100 resultados sobre un patrón de colores y luminancias, diferencias entre recetas, mezcla a 0/50/100 %, virados, curvas cruzadas, determinismo y expansión espacial de los halos. El catálogo está en `photo-editor-presets.js`; las recetas incluyen descripciones de sus efectos.
