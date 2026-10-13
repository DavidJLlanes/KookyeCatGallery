# modules/

Funcionalidades completas y desactivables del CMS (microblog, mapa, categorías…). Cada módulo vive en su carpeta y se carga con el espacio de nombres `Kcg\Modules\<Nombre>\`:

```
modules/Microblog/PostRepository.php   →  Kcg\Modules\Microblog\PostRepository
```

Un módulo trae sus clases PHP y, si las tiene, sus hojas de estilo y scripts, que solo se cargan si el módulo está activo.

## Microblog (`Microblog/`)

Almacén de los posts. `inc/microblog.php` (reglas, validación, imágenes y pintado) lo usa a través de `microblog_repository()`.

| Clase | Papel |
| --- | --- |
| `PostRepository` | Interfaz: `all`, `find`, `mutate` (transacción), `publicFeed`, `publicThread`, `threads`. |
| `SqlitePostRepository` | El almacén normal (PDO + SQLite). Importa el `posts.json` antiguo la primera vez. |
| `JsonPostRepository` | Reserva si no hay `pdo_sqlite`; lee y escribe el archivo entero. |
| `AbstractPostRepository` | Las consultas derivadas de `all()` en memoria: la referencia que SQLite debe igualar. |
| `Posts` | Reglas de lista: qué es público y el orden. |
| `Repositories` | Elige y guarda el almacén en uso. |

Detalle de la migración y la copia de seguridad en [../docs/microblog.md](../docs/microblog.md).
