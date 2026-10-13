<?php
declare(strict_types=1);

// Pruebas de los ajustes de IA del panel (core/Ai/Settings.php): validación, claves que no se muestran ni se pierden,
// prioridad entorno > panel > config.php antiguo, y el estilo dentro del prompt. No hace llamadas de red.
// Uso: php tests/ai-settings.php

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai-settings-' . bin2hex(random_bytes(4));
mkdir($tmp, 0775, true);
putenv('SITE_CONFIG_DIR=' . $tmp);
foreach (['GALLERY_AI_ENABLED', 'GALLERY_GEMINI_API_KEY', 'GALLERY_OPENROUTER_API_KEY', 'GALLERY_AI_CASCADE', 'GALLERY_AI_MAX_PER_REQUEST', 'GALLERY_AI_CONTEXT'] as $variable) putenv($variable);

require __DIR__ . '/../core/bootstrap.php';
require __DIR__ . '/../inc/ai-text.php';

use Kcg\Core\Ai\Settings;

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function rejects(callable $call, string $contains, string $message): void
{
    try { $call(); } catch (InvalidArgumentException $error) {
        check(str_contains($error->getMessage(), $contains), $message . ' (mensaje: ' . $error->getMessage() . ')');
        return;
    }
    throw new RuntimeException($message . ' (no se rechazó)');
}

// 1. Sin guardar nada: IA apagada, sin claves; y ai_text_config respeta el config.php antiguo.
check(!Settings::exists() && Settings::load() === Settings::defaults(), 'Sin archivo se usan los valores por defecto.');
check(Settings::defaults()['enabled'] === false, 'La IA está desactivada por defecto.');
$legacy = ai_text_config(['ai_enabled' => true, 'gemini_api_key' => 'clave-antigua-0123456789', 'site_context' => 'Contexto antiguo']);
check($legacy['ai_enabled'] === true && $legacy['gemini_api_key'] === 'clave-antigua-0123456789' && $legacy['site_context'] === 'Contexto antiguo', 'Sin ajustes del panel manda el config.php antiguo.');

// 2. Validación.
$none = Settings::defaults();
rejects(fn() => Settings::validate(['enabled' => '1'], $none), 'al menos una clave', 'Activar sin clave debe rechazarse.');
rejects(fn() => Settings::validate(['gemini_api_key' => 'corta'], $none), 'no parece válida', 'Una clave muy corta no es válida.');
rejects(fn() => Settings::validate(['gemini_api_key' => 'clave con espacios 0123456789'], $none), 'no parece válida', 'Una clave con espacios no es válida.');
rejects(fn() => Settings::validate(['gemini_model' => 'modelo raro!'], $none), 'modelo', 'Un modelo con caracteres raros no es válido.');
rejects(fn() => Settings::validate(['first' => 'otro'], $none), 'primero', 'Un proveedor desconocido no es válido.');
rejects(fn() => Settings::validate(['max_per_request' => '0'], $none), 'entre 1 y 10', 'El mínimo es 1.');
rejects(fn() => Settings::validate(['max_per_request' => '11'], $none), 'entre 1 y 10', 'El máximo es 10.');
rejects(fn() => Settings::validate(['style' => str_repeat('a', 601)], $none), '600', 'El estilo tiene un máximo.');
$clean = Settings::validate(['style' => "  Tono\x00 cercano\r\ny directo  ", 'max_per_request' => '4'], $none);
check($clean['style'] === "Tono cercano\ny directo" && $clean['max_per_request'] === 4, 'El estilo se limpia (espacios, caracteres de control, saltos de línea).');

// 3. Guardar: permisos, claves que se conservan o se borran, y nunca se pierden por un guardado sin clave.
$key = 'AIzaSy-clave-de-prueba-0123456789';
$saved = Settings::save(['enabled' => '1', 'gemini_api_key' => $key, 'first' => 'openrouter', 'max_per_request' => '3', 'style' => 'Poético y breve', 'context' => 'Paisaje']);
check(Settings::exists() && $saved['gemini_api_key'] === $key && Settings::load()['first'] === 'openrouter', 'Se guarda y se vuelve a leer.');
if (DIRECTORY_SEPARATOR === '/') check((fileperms(Settings::path()) & 0777) === 0640, 'El archivo de ajustes es privado (0640).');
check(Settings::mask($key) === '••••••••6789' && !str_contains(Settings::mask($key), 'AIza'), 'La clave se enmascara con los últimos cuatro caracteres.');
Settings::save(['enabled' => '1', 'first' => 'openrouter', 'max_per_request' => '3', 'style' => '']);
check(Settings::load()['gemini_api_key'] === $key && Settings::load()['style'] === '', 'Una clave vacía conserva la guardada y el estilo vacío lo borra.');
Settings::save(['enabled' => '1', 'openrouter_api_key' => 'sk-or-v1-clave-de-prueba-0123456789', 'first' => 'gemini', 'max_per_request' => '3']);
Settings::save(['first' => 'gemini', 'max_per_request' => '3', 'clear_gemini_api_key' => '1']);
check(Settings::load()['gemini_api_key'] === '' && Settings::load()['openrouter_api_key'] !== '' && Settings::load()['enabled'] === false, '«Borrar la clave» vacía solo esa y, sin marcar «Activar», la IA queda apagada.');
check(count(glob($tmp . '/.ai-*') ?: []) === 0, 'No quedan archivos temporales.');

// 4. Prioridad: el panel sustituye al config.php antiguo y el entorno manda sobre el panel.
Settings::save(['enabled' => '1', 'gemini_api_key' => $key, 'first' => 'openrouter', 'gemini_model' => 'gemini-2.0-flash', 'max_per_request' => '2', 'style' => 'Poético y breve', 'context' => 'Paisaje de montaña']);
$config = ai_text_config(['ai_enabled' => false, 'gemini_api_key' => 'clave-antigua-0123456789', 'site_context' => 'Contexto antiguo'], 'Mi galería', 'https://example.com');
check($config['ai_enabled'] === true && $config['gemini_api_key'] === $key && $config['site_context'] === 'Paisaje de montaña' && $config['style'] === 'Poético y breve', 'El panel manda sobre el config.php antiguo.');
check($config['ai_max_per_request'] === 2 && $config['cascade'][0] === ['openrouter', 'openai/gpt-4o-mini'] && $config['cascade'][1] === ['gemini', 'gemini-2.0-flash'], 'El orden y los modelos del panel forman la cascada.');
putenv('GALLERY_AI_ENABLED=0');
putenv('GALLERY_GEMINI_API_KEY=clave-del-entorno-0123456789');
putenv('GALLERY_AI_CASCADE=gemini:gemini-2.5-flash');
check(Settings::lockedByEnvironment() === ['enabled', 'gemini_api_key', 'order'] || array_diff(['enabled', 'gemini_api_key', 'order'], Settings::lockedByEnvironment()) === [], 'El panel sabe qué ajustes fija el entorno.');
$config = ai_text_config([], 'Mi galería', 'https://example.com');
check($config['ai_enabled'] === false && $config['gemini_api_key'] === 'clave-del-entorno-0123456789' && $config['cascade'] === [['gemini', 'gemini-2.5-flash']], 'El entorno manda sobre el panel.');
foreach (['GALLERY_AI_ENABLED', 'GALLERY_GEMINI_API_KEY', 'GALLERY_AI_CASCADE'] as $variable) putenv($variable);
putenv('GALLERY_GEMINI_API_KEY=clave-del-entorno-0123456789');
check(Settings::validate(['enabled' => '1', 'first' => 'gemini', 'max_per_request' => '3'], Settings::defaults())['enabled'] === true, 'Con una clave en el entorno se puede activar sin clave en el panel.');
putenv('GALLERY_GEMINI_API_KEY');

// 5. El estilo entra en el prompt, y sin estilo el prompt no cambia.
$withStyle = (new AiTextGenerator($tmp, ai_text_config([], 'Mi galería', 'https://example.com')))->promptFor('atardecer-en-la-playa.jpg');
check(str_contains($withStyle, 'ESTILO DE REDACCIÓN') && str_contains($withStyle, 'Poético y breve') && str_contains($withStyle, 'Paisaje de montaña') && str_contains($withStyle, 'atardecer-en-la-playa.jpg'), 'El prompt incluye estilo, contexto y nombre de archivo.');
check(strpos($withStyle, 'ESTILO DE REDACCIÓN') < strpos($withStyle, 'REGLAS:'), 'El estilo va antes de las reglas, que siguen mandando.');
$plain = (new AiTextGenerator($tmp, ['site_name' => 'Mi galería', 'cascade' => []]))->promptFor('atardecer-en-la-playa.jpg');
check(!str_contains($plain, 'ESTILO DE REDACCIÓN') && str_contains($plain, 'REGLAS:'), 'Sin estilo el prompt es el de siempre.');
$probe = (new AiTextGenerator($tmp, ['site_name' => 'x', 'cascade' => []]))->probe('gemini', 'gemini-2.5-flash');
check($probe['ok'] === false && str_contains($probe['message'], 'No hay clave'), 'Probar sin clave no hace ninguna llamada y lo explica.');

foreach (glob($tmp . '/*') ?: [] as $file) @unlink($file);
@rmdir($tmp);
echo "AI settings tests passed.\n";
