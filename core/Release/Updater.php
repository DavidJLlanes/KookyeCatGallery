<?php
declare(strict_types=1);

namespace Kcg\Core\Release;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Actualiza el núcleo de una instalación a partir de una versión nueva (carpeta con `core-manifest.json`), con copia previa
 * y vuelta atrás:
 *
 *   plan()      qué se añade, cambia y retira, y qué archivos del núcleo se habían modificado a mano
 *   apply()     copia de seguridad de lo que se va a tocar → aplica → comprueba la sintaxis; si algo falla, deshace solo
 *   rollback()  restaura una copia (la última por defecto)
 *
 * No toca nunca lo propio de la instalación (Manifest::isInstallationPath(): installation.php, fotos, datos, logs…).
 * Los ajustes, el acceso del administrador y el microblog viven en la carpeta privada, fuera del núcleo; la copia
 * guarda además `site-settings.json` y la base del microblog por si una migración nueva los cambiara.
 */
final class Updater
{
    /** @param string $backups carpeta donde se guardan las copias (fuera de la raíz pública, p. ej. dentro de la carpeta privada) */
    public function __construct(
        private readonly string $installDir,
        private readonly string $sourceDir,
        private readonly string $backups,
        private readonly string $privateDir = '',
    ) {}

    private function install(string $path = ''): string
    {
        return rtrim(str_replace('\\', '/', $this->installDir), '/') . ($path === '' ? '' : '/' . $path);
    }

    private function source(string $path = ''): string
    {
        return rtrim(str_replace('\\', '/', $this->sourceDir), '/') . ($path === '' ? '' : '/' . $path);
    }

    /**
     * @return array{from: string, to: string, add: list<string>, change: list<string>, remove: list<string>, modified: list<string>}
     */
    public function plan(): array
    {
        $new = Manifest::read($this->sourceDir);
        if ($new === null) throw new InvalidArgumentException('La versión nueva no trae ' . Manifest::FILE . ': no es un paquete del núcleo.');
        $old = Manifest::read($this->installDir);
        $plan = ['from' => $old['version'] ?? '(desconocida)', 'to' => $new['version'], 'add' => [], 'change' => [], 'remove' => [], 'modified' => []];
        foreach ($new['files'] as $path => $hash) {
            if (Manifest::isInstallationPath($path)) continue;
            $target = $this->install($path);
            if (!is_file($target)) { $plan['add'][] = $path; continue; }
            if (Manifest::hash($target) === $hash) continue;
            $plan['change'][] = $path;
            // Modificado a mano = distinto de lo que instaló la versión anterior (sin manifiesto anterior no se puede saber).
            if ($old !== null && isset($old['files'][$path]) && Manifest::hash($target) !== $old['files'][$path]) $plan['modified'][] = $path;
        }
        foreach ($old['files'] ?? [] as $path => $hash) {
            if (!isset($new['files'][$path]) && is_file($this->install($path)) && !Manifest::isInstallationPath($path)) $plan['remove'][] = $path;
        }
        return $plan;
    }

    /**
     * Aplica la actualización. Devuelve el identificador de la copia de seguridad.
     *
     * @param bool $overwriteModified permite pisar archivos del núcleo que se habían modificado a mano (quedan en la copia)
     */
    public function apply(bool $overwriteModified = false): string
    {
        $plan = $this->plan();
        if ($plan['modified'] && !$overwriteModified) {
            throw new RuntimeException("Hay archivos del núcleo modificados a mano que la actualización sobrescribiría:\n  - " . implode("\n  - ", $plan['modified'])
                . "\nRevísalos (los cambios propios van en installation.php) o repite la orden con --overwrite-modified: quedarán en la copia de seguridad.");
        }
        $id = date('Ymd-His') . '-' . bin2hex(random_bytes(2));
        $backup = rtrim($this->backups, '/\\') . '/' . $id;
        if (!@mkdir($backup . '/files', 0750, true) && !is_dir($backup . '/files')) throw new RuntimeException('No se pudo crear la carpeta de copias de seguridad: ' . $this->backups);

        // 1. Copia de lo que se va a tocar y de los datos que una migración podría cambiar.
        foreach (array_merge($plan['change'], $plan['remove']) as $path) $this->copy($this->install($path), $backup . '/files/' . $path);
        $private = [];
        foreach (['site-settings.json', 'microblog/microblog.sqlite', 'microblog/microblog.sqlite-wal', 'collections.json'] as $name) {
            if ($this->privateDir !== '' && is_file($this->privateDir . '/' . $name)) {
                $this->copy($this->privateDir . '/' . $name, $backup . '/private/' . $name);
                $private[] = $name;
            }
        }
        $versionFile = $this->install('version.json');
        $hadVersion = is_file($versionFile);
        if ($hadVersion) $this->copy($versionFile, $backup . '/version.json');
        $oldManifest = $this->install(Manifest::FILE);
        $hadManifest = is_file($oldManifest);
        if ($hadManifest) $this->copy($oldManifest, $backup . '/' . Manifest::FILE);
        file_put_contents($backup . '/backup.json', json_encode($plan + ['id' => $id, 'created' => date('c'), 'private' => $private, 'had_manifest' => $hadManifest, 'had_version' => $hadVersion],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // 2. Aplicar. Si algo falla (o la sintaxis no es válida), se deshace.
        try {
            foreach (array_merge($plan['add'], $plan['change']) as $path) $this->copy($this->source($path), $this->install($path));
            foreach ($plan['remove'] as $path) @unlink($this->install($path));
            $new = Manifest::read($this->sourceDir);
            Manifest::write($this->installDir, $plan['to'], $new['files']);
            if (preg_match('/^[a-f0-9]{7,40}$/i', $plan['to'])) file_put_contents($versionFile, json_encode(['version' => strtolower($plan['to'])]) . "
");
            $broken = $this->lint(array_merge($plan['add'], $plan['change']));
            if ($broken) throw new RuntimeException("La versión nueva tiene errores de sintaxis:\n  - " . implode("\n  - ", $broken));
        } catch (Throwable $error) {
            self::rollback($this->installDir, $this->backups, $id, $this->privateDir);
            throw new RuntimeException('La actualización se ha deshecho: ' . $error->getMessage(), 0, $error);
        }
        return $id;
    }

    /**
     * Restaura una copia de seguridad (la más reciente si no se indica) y devuelve su identificador.
     */
    public static function rollback(string $installDir, string $backups, ?string $id = null, string $privateDir = ''): string
    {
        $id ??= (self::backups($backups)[0] ?? throw new RuntimeException('No hay copias de seguridad.'));
        $backup = rtrim($backups, '/\\') . '/' . $id;
        $info = json_decode((string) @file_get_contents($backup . '/backup.json'), true);
        if (!is_array($info)) throw new RuntimeException("La copia $id no existe o está dañada.");
        $install = static fn(string $path): string => rtrim(str_replace('\\', '/', $installDir), '/') . '/' . $path;
        foreach ($info['add'] as $path) @unlink($install($path));
        foreach (array_merge($info['change'], $info['remove']) as $path) self::copyFile($backup . '/files/' . $path, $install($path));
        $manifest = $install(Manifest::FILE);
        if (!empty($info['had_manifest'])) self::copyFile($backup . '/' . Manifest::FILE, $manifest);
        else @unlink($manifest);
        if (!empty($info['had_version'])) self::copyFile($backup . '/version.json', $install('version.json'));
        foreach ($info['private'] ?? [] as $name) if ($privateDir !== '') self::copyFile($backup . '/private/' . $name, rtrim($privateDir, '/\\') . '/' . $name);
        return $id;
    }

    /** Identificadores de las copias, de la más reciente a la más antigua. */
    public static function backups(string $backups): array
    {
        $ids = [];
        foreach (glob(rtrim($backups, '/\\') . '/*/backup.json') ?: [] as $file) $ids[] = basename(dirname($file));
        rsort($ids);
        return $ids;
    }

    /** @param list<string> $paths @return list<string> archivos PHP con errores de sintaxis */
    private function lint(array $paths): array
    {
        $broken = [];
        foreach ($paths as $path) {
            if (!str_ends_with($path, '.php') && !str_ends_with($path, '.example')) continue;
            if (str_ends_with($path, '.example')) continue;
            $output = [];
            $status = 0;
            exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($this->install($path)) . ' 2>&1', $output, $status);
            if ($status !== 0) $broken[] = $path . ': ' . trim(implode(' ', array_filter($output, static fn(string $line): bool => !str_contains($line, 'openssl'))));
        }
        return $broken;
    }

    private function copy(string $from, string $to): void
    {
        self::copyFile($from, $to);
    }

    private static function copyFile(string $from, string $to): void
    {
        $directory = dirname($to);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException("No se pudo crear $directory");
        if (!@copy($from, $to)) throw new RuntimeException("No se pudo copiar $from → $to");
    }
}
