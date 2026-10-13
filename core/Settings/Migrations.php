<?php
declare(strict_types=1);

namespace Kcg\Core\Settings;

/**
 * Migraciones de settings.json: ajustes guardados con formatos antiguos que se actualizan al leerlos o validarlos.
 *
 * Cada migración es un archivo core/Settings/migrations/NNNN-nombre.php que devuelve:
 *   'description'  qué cambió y por qué
 *   'when'         'always' (al validar y al cargar) o 'load' (solo al cargar un archivo guardado)
 *   'apply'        function (array $data): array  — recibe los ajustes en bruto y devuelve los ajustes actualizados
 *
 * Reglas: NNNN es el número de versión (consecutivo, desde 0001), y cada migración tiene que ser **idempotente**
 * (aplicarla dos veces da lo mismo que una) y no fallar con datos incompletos o mal formados: de la validación se
 * encarga el Validator después. El archivo guardado lleva `settings_version` = la última migración aplicada.
 */
final class Migrations
{
    /** @var array<int, array{version:int, name:string, description:string, when:string, apply:callable}>|null */
    private static ?array $migrations = null;

    private static function directory(): string
    {
        return __DIR__ . '/migrations';
    }

    /** @return array<int, array{version:int, name:string, description:string, when:string, apply:callable}> */
    public static function all(): array
    {
        if (self::$migrations !== null) return self::$migrations;
        $found = [];
        foreach (glob(self::directory() . '/*.php') ?: [] as $file) {
            if (!preg_match('/^(\d{4})-([a-z0-9-]+)\.php$/', basename($file), $match)) {
                throw new \RuntimeException('Nombre de migración no válido (NNNN-nombre.php): ' . basename($file));
            }
            $definition = require $file;
            if (!is_array($definition) || !is_callable($definition['apply'] ?? null) || !in_array($definition['when'] ?? null, ['always', 'load'], true)) {
                throw new \RuntimeException('La migración ' . basename($file) . ' debe devolver description, when (always|load) y apply.');
            }
            $version = (int) $match[1];
            $found[$version] = ['version' => $version, 'name' => $match[2], 'description' => (string) ($definition['description'] ?? ''),
                'when' => $definition['when'], 'apply' => $definition['apply']];
        }
        ksort($found);
        $expected = 1;
        foreach (array_keys($found) as $version) {
            if ($version !== $expected++) throw new \RuntimeException('Las migraciones deben numerarse sin huecos desde 0001 (falla la ' . sprintf('%04d', $expected - 1) . ').');
        }
        return self::$migrations = $found;
    }

    /** Número de la última migración (= versión actual del formato de ajustes). */
    public static function latest(): int
    {
        return count(self::all());
    }

    /**
     * Aplica las migraciones en orden. `$fromFile` = true cuando los datos vienen de un archivo guardado (se aplican
     * también las de tipo 'load').
     */
    public static function apply(array $data, bool $fromFile = false): array
    {
        foreach (self::all() as $migration) {
            if ($migration['when'] === 'load' && !$fromFile) continue;
            $data = ($migration['apply'])($data);
        }
        return $data;
    }
}
