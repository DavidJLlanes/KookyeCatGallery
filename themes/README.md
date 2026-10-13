# themes/

Opciones de personalización registradas **una por archivo**. Cada `themes/<tipo>/<id>.php` devuelve un array; el identificador es el nombre del archivo y `order` fija su posición en el panel. Lo lee `core/Registry.php`.

| Carpeta | Opción | Claves obligatorias |
| --- | --- | --- |
| `palettes/` | Paleta de colores | `name`, `mode`, `vars` |
| `fonts/` | Familia tipográfica | `name`, `weights` |
| `pairs/` | Pareja de títulos + texto | `name`, `heading`, `body`, `group` |
| `headers-mobile/`, `headers-desktop/` | Cabecera por dispositivo | `name` |
| `galleries/` | Galería estándar | `name`, `devices`, `capabilities` |
| `hover/`, `grids/`, `pagination-shapes/` | Efecto hover, proporción, forma de paginación | `name` |

Las clases `Palettes` y `Fonts` (aquí, en `themes/`) generan el CSS de la paleta y las fuentes elegidas. `php tests/catalogs.php` comprueba que cada opción tiene todo lo que necesita.

Guía paso a paso: [docs/CONTRIBUTING.md](../docs/CONTRIBUTING.md).
