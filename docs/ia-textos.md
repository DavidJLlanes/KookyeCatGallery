# Textos de fotografías con IA

Ambas instalaciones usan `inc/ai-text.php` para proponer títulos y descripciones. La función está desactivada por defecto. Los textos manuales se conservan; las propuestas automáticas se marcan con `# auto-gemini/...`, `# auto-openrouter/...` o `# auto-filename`.

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
