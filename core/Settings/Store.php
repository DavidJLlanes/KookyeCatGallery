<?php
declare(strict_types=1);

namespace Kcg\Core\Settings;

use InvalidArgumentException;
use RuntimeException;

/**
 * Almacén de los ajustes: dónde está el archivo, cómo se lee y cómo se guarda.
 *
 * El archivo es JSON en la carpeta de configuración privada (fuera del directorio público: un despliegue no lo pisa).
 * Se lee siempre pasando por el Validator, así que lo que sale de aquí es un conjunto completo y válido de ajustes.
 */
final class Store
{
    /** Carpeta de la configuración privada de la instalación (ver Installation::privateDir()). */
    public static function configDir(): string
    {
        return \Kcg\Core\Installation::privateDir();
    }

    public static function path(): string
    {
        return self::configDir() . '/site-settings.json';
    }

    /** Lee los ajustes guardados. Si el archivo no existe o está dañado, devuelve los valores por defecto. */
    public static function load(?string $path = null): array
    {
        $path ??= self::path();
        $settings = Schema::defaults();
        if (!is_file($path)) return $settings;
        $raw = @file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) return $settings;
        try {
            $settings = Validator::validate(array_replace($settings, $decoded), true);
        } catch (InvalidArgumentException $error) {
            error_log('Invalid site settings: ' . $error->getMessage());
        }
        return $settings;
    }

    /**
     * Valida y guarda los ajustes de forma atómica (archivo temporal + rename). Lanza InvalidArgumentException si algo
     * no es válido y RuntimeException si no puede escribir.
     */
    public static function save(array $input, ?string $path = null): void
    {
        $path ??= self::path();
        if (is_file($path)) $input = \site_gallery_preserve_inactive($input, self::load($path));
        $settings = Validator::validate($input);
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo crear la carpeta de configuración.');
        }
        $json = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $temporary = tempnam($directory, '.site-settings-');
        if ($temporary === false) throw new RuntimeException('No se pudo preparar el guardado.');
        try {
            if (file_put_contents($temporary, $json . "\n", LOCK_EX) === false || !chmod($temporary, 0640) || !rename($temporary, $path)) {
                throw new RuntimeException('No se pudo guardar la configuración. Comprueba los permisos de la carpeta config.');
            }
        } finally {
            if (is_file($temporary)) unlink($temporary);
        }
    }
}
