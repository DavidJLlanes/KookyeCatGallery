<?php
declare(strict_types=1);

require __DIR__ . '/../inc/ai-text.php';

function check_ai(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

foreach (['GALLERY_AI_ENABLED', 'GALLERY_GEMINI_API_KEY', 'GALLERY_OPENROUTER_API_KEY',
    'GALLERY_AI_CASCADE', 'GALLERY_AI_MAX_PER_REQUEST', 'GALLERY_AI_CONTEXT'] as $key) {
    putenv($key);
}

$default = ai_text_config([], 'Galería de ejemplo', 'https://example.com');
check_ai($default['ai_enabled'] === false, 'La IA debe estar desactivada por defecto');
check_ai(($default['gemini_api_key'] ?? '') === '', 'La configuración predeterminada no incluye claves');

$temp = sys_get_temp_dir() . '/gallery-ai-' . bin2hex(random_bytes(4));
mkdir($temp);
$sidecar = $temp . '/photo.txt';
$fallback = new AiTextGenerator($temp, $default);
$written = $fallback->processMissing([[
    'original' => 'bosque-niebla.jpg',
    'sidecar' => $sidecar,
]], 1);
check_ai($written === 1, 'Sin claves debe generarse un texto provisional');
check_ai(str_contains((string) file_get_contents($sidecar), '# auto-filename'), 'Debe marcarse el fallback');
unlink($sidecar);
rmdir($temp);

putenv('GALLERY_AI_ENABLED=1');
putenv('GALLERY_AI_CASCADE=openrouter:openai/gpt-4o-mini,gemini:gemini-2.5-flash');
putenv('GALLERY_AI_MAX_PER_REQUEST=99');
putenv('GALLERY_AI_CONTEXT=Fotografía de naturaleza');
$config = ai_text_config([], 'Galería de ejemplo', 'https://example.com');
check_ai($config['ai_enabled'] === true, 'La variable de entorno debe activar la IA');
check_ai($config['cascade'][0] === ['openrouter', 'openai/gpt-4o-mini'], 'La cascada debe conservar el modelo');
check_ai($config['ai_max_per_request'] === 10, 'El límite por petición debe acotarse');
check_ai($config['site_context'] === 'Fotografía de naturaleza', 'El contexto debe ser configurable');

$generator = new AiTextGenerator(__DIR__, $config);
$prompt = (new ReflectionMethod(AiTextGenerator::class, 'buildPrompt'))->invoke($generator, 'bosque-otoño.jpg');
check_ai(str_contains($prompt, 'Galería de ejemplo'), 'El prompt debe usar la marca local');
check_ai(str_contains($prompt, 'Fotografía de naturaleza'), 'El prompt debe usar el contexto local');

putenv('GALLERY_AI_CASCADE');
putenv('GALLERY_AI_CONTEXT');
$legacy = ai_text_config([
    'ai_enabled' => true,
    'cascade' => [['gemini', 'gemini-2.5-flash']],
    'gemini_api_key' => 'clave-de-prueba',
], 'Sitio existente');
check_ai($legacy['ai_enabled'] === true, 'La configuración existente debe seguir funcionando');
check_ai($legacy['gemini_api_key'] === 'clave-de-prueba', 'La clave externa debe conservarse');

echo "Configuración y prompt de IA verificados.\n";
