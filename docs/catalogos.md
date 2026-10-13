# Catálogo de opciones de diseño

Generado por `php scripts/build-catalogs.php` a partir de `themes/`; no se edita a mano (la prueba `tests/catalog-docs.php` falla si está desactualizado). Para añadir una opción, crea su archivo en la carpeta que corresponda (ver [CONTRIBUTING.md](CONTRIBUTING.md)).

## Paletas de color (7)

| Id | Nombre | Modo | Fondo | Texto | Acento |
| --- | --- | --- | --- | --- | --- |
| current | Elegante | oscuro | del diseño base | del diseño base | del diseño base |
| black | Noche · negro y blanco | oscuro | #000 | #fff | #fff |
| white | Luz · blanco y tinta | claro | #fff | #181818 | #79512b |
| cyberpunk | Cyberpunk · neón | oscuro | #090619 | #f5f3ff | #00f5dc |
| japanese | Japón · papel y carmesí | claro | #f7f2e8 | #252824 | #a22c35 |
| forest | Bosque · verde y marfil | claro | #edf2e8 | #20372e | #397653 |
| ocean | Océano · azul profundo y turquesa | oscuro | #102737 | #f2f5ec | #70d5c5 |

## Familias tipográficas (17)

| Id | Nombre | Pesos |
| --- | --- | --- |
| archivo | Archivo | 300 700 |
| bodoni-moda | Bodoni Moda | 400 700 |
| cormorant-garamond | Cormorant Garamond | 300 700 |
| dm-sans | DM Sans | 300 700 |
| eb-garamond | EB Garamond | 400 700 |
| fraunces | Fraunces | 300 700 |
| inter | Inter | 300 700 |
| lora | Lora | 400 700 |
| manrope | Manrope | 300 700 |
| outfit | Outfit | 300 700 |
| playfair-display | Playfair Display | 400 700 |
| plus-jakarta-sans | Plus Jakarta Sans | 300 700 |
| roboto-mono | Roboto Mono | 300 700 |
| source-sans-3 | Source Sans 3 | 300 700 |
| source-serif-4 | Source Serif 4 | 300 700 |
| space-grotesk | Space Grotesk | 300 700 |
| syne | Syne | 400 700 |

## Parejas de tipografías (30)

| Id | Nombre | Grupo | Títulos + texto |
| --- | --- | --- | --- |
| classic | Editorial clásico | editorial | Cormorant Garamond + Inter |
| luminous | Galería luminosa | editorial | Playfair Display + DM Sans |
| essay | Ensayo fotográfico | editorial | Fraunces + Source Sans 3 |
| intimate | Colección íntima | editorial | EB Garamond + Manrope |
| photobook | Fotolibro actual | editorial | Source Serif 4 + Outfit |
| landscape | Paisaje sereno | editorial | Lora + Inter |
| couture | Retrato de autor | editorial | Bodoni Moda + Manrope |
| museum | Museo contemporáneo | editorial | Cormorant Garamond + DM Sans |
| magazine | Revista visual | editorial | Playfair Display + Source Sans 3 |
| journal | Cuaderno personal | editorial | Fraunces + Lora |
| heritage | Patrimonio visual | editorial | EB Garamond + Source Sans 3 |
| portrait | Elegancia urbana | editorial | Bodoni Moda + Inter |
| nature | Naturaleza editorial | editorial | Lora + Manrope |
| archive | Archivo de imágenes | editorial | Source Serif 4 + DM Sans |
| quiet | Mirada tranquila | editorial | Cormorant Garamond + Source Sans 3 |
| urban | Urbano limpio | modern | Space Grotesk + Inter |
| studio | Estudio creativo | modern | Syne + DM Sans |
| minimal | Minimal cálido | modern | Outfit + Lora |
| contemporary | Portfolio contemporáneo | modern | Manrope + Source Serif 4 |
| precise | Precisión visual | modern | Plus Jakarta Sans + Inter |
| documentary | Documental moderno | modern | Archivo + Manrope |
| gallery | Galería esencial | modern | DM Sans + Source Serif 4 |
| soft | Luz natural | modern | Source Sans 3 + Lora |
| geometry | Geometría editorial | modern | Space Grotesk + Source Serif 4 |
| bold | Portada expresiva | modern | Syne + Lora |
| architecture | Arquitectura nítida | modern | Archivo + DM Sans |
| night | Nocturno gráfico | experimental | Outfit + Roboto Mono |
| contact | Hoja de contactos | experimental | Roboto Mono + Inter |
| analog | Laboratorio analógico | experimental | Fraunces + Roboto Mono |
| typewriter | Archivo tipográfico | experimental | Cormorant Garamond + Roboto Mono |

## Cabeceras de móvil (9)

| Id | Nombre | Archivos propios |
| --- | --- | --- |
| current | Perfil social | solo el estilo común |
| centered | Retrato centrado | solo el estilo común |
| compact | Compacta | solo el estilo común |
| editorial | Editorial | solo el estilo común |
| reel | Cine · tira de película | `assets/css/worlds/mobile-reel.css`, `assets/css/worlds/mobile-common.css` |
| atlas | Atlas · cuaderno cartográfico | `assets/css/worlds/mobile-atlas.css`, `assets/css/worlds/mobile-common.css` |
| studio | Estudio · retícula suiza | `assets/css/worlds/mobile-studio.css`, `assets/css/worlds/mobile-common.css` |
| orbit | Órbita · cielo 3D | `assets/css/worlds/canvas.css`, `assets/css/worlds/mobile-orbit.css`, `assets/css/worlds/mobile-common.css`, `assets/js/interface-worlds.js` |
| scrapbook | Álbum · recortes y postales | `assets/css/worlds/mobile-scrapbook.css`, `assets/css/worlds/mobile-common.css` |

## Cabeceras de escritorio (9)

| Id | Nombre | Archivos propios |
| --- | --- | --- |
| current | Tipográfica | solo el estilo común |
| centered | Retrato centrado | solo el estilo común |
| compact | Compacta | solo el estilo común |
| editorial | Editorial | solo el estilo común |
| museum | Museo · galería blanca | `assets/css/worlds/desktop-museum.css`, `assets/css/worlds/desktop-common.css` |
| observatory | Observatorio · constelación 3D | `assets/css/worlds/canvas.css`, `assets/css/worlds/desktop-observatory.css`, `assets/css/worlds/desktop-common.css`, `assets/js/interface-worlds.js` |
| newsroom | Periódico · portada de autor | `assets/css/worlds/desktop-newsroom.css`, `assets/css/worlds/desktop-common.css` |
| noir | Noir · estreno de cine | `assets/css/worlds/desktop-noir.css`, `assets/css/worlds/desktop-common.css` |
| blueprint | Plano · archivo técnico | `assets/css/worlds/desktop-blueprint.css`, `assets/css/worlds/desktop-common.css` |

## Galerías estándar (10)

| Id | Nombre | Dispositivos | Controles |
| --- | --- | --- | --- |
| standard | Masonry · alturas naturales | mobile, desktop | 1, 1, , 1 |
| grid | Cuadrícula | mobile, desktop | 1, 1, 1, 1 |
| mosaic | Mosaico cromático | mobile, desktop | 1, 1, , 1 |
| asymmetric | Editorial asimétrica | mobile, desktop | 1, 1, , 1 |
| category-rails | Filas por categoría | mobile, desktop | , , ,  |
| scattered | Álbum desordenado | mobile, desktop | 1, 1, , 1 |
| exhibition | Sala de exposición | mobile, desktop | , 1, , 1 |
| contact-sheet | Hoja de contactos | mobile, desktop | 1, 1, 1, 1 |
| narrative | Secuencia narrativa | mobile, desktop | 1, 1, , 1 |
| triptych | Trípticos | mobile, desktop | , 1, , 1 |

## Efectos hover (10)

| Id | Nombre |
| --- | --- |
| soft | Suave |
| zoom | Acercamiento |
| lift | Elevación |
| reveal | Revelado |
| tint | Virado de color |
| frame | Marco luminoso |
| slide | Desplazamiento |
| tilt | Perspectiva |
| focus | Enfoque |
| shine | Destello |

## Proporciones de foto (4)

| Id | Nombre |
| --- | --- |
| adaptive | Según el diseño |
| square | Cuadrada |
| landscape | Horizontal · 4:3 |
| portrait | Vertical · 3:4 |

## Formas de paginación (2)

| Id | Nombre |
| --- | --- |
| circle | Círculos |
| square | Cuadrados |
