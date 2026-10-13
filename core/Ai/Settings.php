<?php
declare(strict_types=1);

namespace Kcg\Core\Ai;

use InvalidArgumentException;
use Kcg\Core\Installation;
use RuntimeException;

/**
 * Ajustes de la IA que se editan en el panel (pestaña «IA»): activar o desactivar, claves de Gemini y OpenRouter, modelos,
 * orden de los proveedores, límite por visita y el contexto y el estilo de los textos que se piden.
 *
 * Se guardan en `ai-settings.json` de la carpeta privada (fuera de la raíz pública, permisos 0640), nunca en
 * site-settings.json. Prioridad al usarlos (ver ai_text_config()): variables de entorno > panel > config.php antiguo > valores por defecto.
 */
final class Settings
{
    public const FILE = 'ai-settings.json';
    public const MAX_TEXT = 600;
    public const PROVIDERS = ['gemini' => 'Gemini (Google)', 'openrouter' => 'OpenRouter'];
    public const DEFAULT_MODELS = ['gemini' => 'gemini-2.5-flash', 'openrouter' => 'openai/gpt-4o-mini'];

    public static function path(): string
    {
        return Installation::privateDir() . '/' . self::FILE;
    }

    /** Valores por defecto: IA apagada, sin claves, Gemini primero. */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'gemini_api_key' => '',
            'openrouter_api_key' => '',
            'gemini_model' => self::DEFAULT_MODELS['gemini'],
            'openrouter_model' => self::DEFAULT_MODELS['openrouter'],
            'first' => 'gemini',
            'max_per_request' => 5,
            'context' => '',
            'style' => '',
        ];
    }

    /** ¿Se ha guardado alguna vez desde el panel? (si no, manda el config.php antiguo). */
    public static function exists(): bool
    {
        return is_file(self::path());
    }

    /** Ajustes guardados completados con los valores por defecto (un archivo dañado equivale a no tener ninguno). */
    public static function load(): array
    {
        $settings = self::defaults();
        $decoded = self::exists() ? json_decode((string) @file_get_contents(self::path()), true) : null;
        if (!is_array($decoded)) return $settings;
        try {
            return self::validate($decoded + $settings, $settings);
        } catch (InvalidArgumentException) {
            return $settings;
        }
    }

    /**
     * Valida una entrada (formulario o archivo). Las claves vacías conservan la actual (`$current`), de modo que el panel
     * nunca tiene que mostrar las claves guardadas; `clear_gemini_api_key` / `clear_openrouter_api_key` las borran.
     *
     * @throws InvalidArgumentException con un mensaje para el usuario
     */
    public static function validate(array $input, array $current): array
    {
        $out = $current + self::defaults();
        $out['enabled'] = !empty($input['enabled']);
        foreach (array_keys(self::PROVIDERS) as $provider) {
            $keyField = $provider . '_api_key';
            $key = trim((string) ($input[$keyField] ?? ''));
            if (!empty($input['clear_' . $keyField])) $key = '';
            elseif ($key === '') $key = (string) ($current[$keyField] ?? '');
            if ($key !== '' && !preg_match('/^[A-Za-z0-9._:~\\/+=-]{16,300}$/', $key)) {
                throw new InvalidArgumentException('La clave de ' . self::PROVIDERS[$provider] . ' no parece válida (16 a 300 caracteres, sin espacios).');
            }
            $out[$keyField] = $key;
            $model = trim((string) ($input[$provider . '_model'] ?? ''));
            if ($model === '') $model = self::DEFAULT_MODELS[$provider];
            if (!preg_match('/^[a-zA-Z0-9._\\/:-]{1,100}$/', $model)) {
                throw new InvalidArgumentException('El modelo de ' . self::PROVIDERS[$provider] . ' solo admite letras, números y . _ / : -');
            }
            $out[$provider . '_model'] = $model;
        }
        $first = (string) ($input['first'] ?? 'gemini');
        if (!isset(self::PROVIDERS[$first])) throw new InvalidArgumentException('El proveedor que se prueba primero no es válido.');
        $out['first'] = $first;
        $max = filter_var($input['max_per_request'] ?? 5, FILTER_VALIDATE_INT);
        if ($max === false || $max < 1 || $max > 10) throw new InvalidArgumentException('Las fotos por visita deben estar entre 1 y 10.');
        $out['max_per_request'] = $max;
        foreach (['context' => 'El contexto', 'style' => 'El estilo'] as $field => $label) {
            $text = trim(str_replace(["\r\n", "\r"], "\n", (string) ($input[$field] ?? '')));
            $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
            if (self::length($text) > self::MAX_TEXT) throw new InvalidArgumentException($label . ' no puede pasar de ' . self::MAX_TEXT . ' caracteres.');
            $out[$field] = $text;
        }
        if ($out['enabled'] && $out['gemini_api_key'] === '' && $out['openrouter_api_key'] === '' && !self::keyFromEnvironment()) {
            throw new InvalidArgumentException('Para activar la IA añade al menos una clave (Gemini u OpenRouter). Sin clave solo se usaría el título derivado del nombre del archivo.');
        }
        return $out;
    }

    /** Valida y guarda de forma atómica (archivo temporal + rename, permisos 0640). */
    public static function save(array $input): array
    {
        $settings = self::validate($input, self::load());
        $path = self::path();
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) throw new RuntimeException('No se pudo crear la carpeta privada.');
        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $temporary = @tempnam($directory, '.ai-');
        if ($temporary === false || @file_put_contents($temporary, $json . "\n", LOCK_EX) === false || !@chmod($temporary, 0640) || !@rename($temporary, $path)) {
            if (is_string($temporary)) @unlink($temporary);
            throw new RuntimeException('No se pudo guardar la configuración de la IA. Comprueba los permisos de la carpeta privada.');
        }
        return $settings;
    }

    /** Clave con los últimos cuatro caracteres a la vista, para mostrar que hay una guardada sin revelarla. */
    public static function mask(string $key): string
    {
        return $key === '' ? '' : str_repeat('•', 8) . substr($key, -4);
    }

    /** Ajustes que fija una variable de entorno del hosting (el panel los muestra bloqueados). @return list<string> */
    public static function lockedByEnvironment(): array
    {
        $map = ['enabled' => 'GALLERY_AI_ENABLED', 'gemini_api_key' => 'GALLERY_GEMINI_API_KEY', 'openrouter_api_key' => 'GALLERY_OPENROUTER_API_KEY',
            'max_per_request' => 'GALLERY_AI_MAX_PER_REQUEST', 'context' => 'GALLERY_AI_CONTEXT', 'order' => 'GALLERY_AI_CASCADE'];
        $locked = [];
        foreach ($map as $field => $variable) {
            $value = getenv($variable);
            if (is_string($value) && trim($value) !== '') $locked[] = $field;
        }
        return $locked;
    }

    private static function keyFromEnvironment(): bool
    {
        return in_array('gemini_api_key', self::lockedByEnvironment(), true) || in_array('openrouter_api_key', self::lockedByEnvironment(), true);
    }

    private static function length(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    }
}
