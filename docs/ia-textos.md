# Textos de fotografías con IA

Ambas instalaciones usan `inc/ai-text.php` para proponer títulos y descripciones. La función está desactivada por defecto. Los textos manuales se conservan; las propuestas automáticas se marcan con `# auto-gemini/...`, `# auto-openrouter/...` o `# auto-filename`.

## Desde el panel

La pestaña **Inteligencia artificial** del panel (`/admin.php?ai=1`) reúne todo lo de la IA, sin tocar archivos:

- **Activar o desactivar** la generación con un interruptor. Desactivada no se envía nada a ningún proveedor.
- **Claves de API** de Gemini y de OpenRouter, cada una con su **modelo**. Las claves se guardan en `ai-settings.json`, dentro de la carpeta privada (fuera de la web, permisos 0640), y no se vuelven a mostrar: solo los últimos cuatro caracteres. Una clave vacía conserva la guardada; «Borrar la clave guardada» la elimina.
- **Probar la conexión** con cada proveedor (con la clave y el modelo guardados): dice si la clave es válida, si el modelo existe o si hay límite de peticiones.
- **Proveedor que se prueba primero** y **fotos por visita** (1 a 10).
- **Estilo de los textos**: un campo de texto (máx. 600 caracteres) que describe cómo deben sonar los títulos y descripciones (tono, longitud, persona, palabras que evitar). Se añade a la petición como «ESTILO DE REDACCIÓN», antes de las reglas, que siguen mandando (no inventar datos, devolver JSON…). Junto a él, el **contexto de la galería**.
- Un desplegable muestra la petición exacta que se enviaría con un nombre de ejemplo.

Para activarla hace falta al menos una clave. Prioridad cuando hay varias fuentes: variables de entorno > panel > `config.php` antiguo > valores por defecto; lo que fija una variable de entorno aparece bloqueado en el panel.

## Con variables de entorno

Configura estas variables en el entorno de PHP, fuera del repositorio:

| Variable | Uso |
| --- | --- |
| `GALLERY_AI_ENABLED=1` | Activa la generación. |
| `GALLERY_GEMINI_API_KEY` | Clave de Gemini, opcional si se usa OpenRouter. |
| `GALLERY_OPENROUTER_API_KEY` | Clave de OpenRouter, opcional si se usa Gemini. |
| `GALLERY_AI_CASCADE` | Orden opcional: `gemini:gemini-2.5-flash,openrouter:openai/gpt-4o-mini`. |
| `GALLERY_AI_MAX_PER_REQUEST` | Fotos por visita, de 1 a 10; por defecto 5. |
| `GALLERY_AI_CONTEXT` | Contexto opcional del portfolio. |

El nombre y la URL se toman de cada instalación. Si faltan las claves o fallan los proveedores, se usa un título derivado del archivo y se puede reintentar después. La instalación privada también acepta su `config.php` externo; las variables de entorno prevalecen. El proyecto no carga archivos `.env` automáticamente.

El panel intenta generar la descripción al publicar una foto sin ella. El índice procesa los textos pendientes hasta el límite configurado. El nombre del archivo se envía al proveedor de IA elegido; evita incluir datos privados en él.
