<?php
/**
 * Procesador de imágenes
 * - Lee originales de /img/
 * - Genera WebP en /imagenes/desktop/ (1600px max) y /imagenes/mobile/ (800px max)
 * - Solo procesa imágenes nuevas o modificadas (cache por mtime)
 * - Mantiene la orientación EXIF (no rota fotos verticales)
 */

declare(strict_types=1);

/**
 * Parsea el nombre del archivo para extraer (Categoría) y (lat,lng)
 * Ejemplo: "catedral-leon(Catedral)(40.6270,-5.5898).jpg" →
 *   ['clean' => 'catedral-leon', 'category' => 'Catedral', 'lat' => 40.6270, 'lng' => -5.5898]
 */
function parseFilenameMetadata(string $filename): array
{
    $out = ['clean' => '', 'category' => '', 'lat' => 0, 'lng' => 0];

    // Extrae la parte sin extensión
    $base = pathinfo($filename, PATHINFO_FILENAME);

    // Busca (Categoría)(lat,lng) al final
    if (preg_match('/^(.+?)\(([^)]+)\)\((\d+\.\d+),\s*(-?\d+\.\d+)\)$/', $base, $m)) {
        $out['clean'] = $m[1];
        $out['category'] = trim($m[2]);
        $out['lat'] = (float) $m[3];
        $out['lng'] = (float) $m[4];
    } else {
        $out['clean'] = $base;
    }

    return $out;
}

class ImageProcessor
{
    private string $sourceDir;
    private string $desktopDir;
    private string $mobileDir;
    private string $cachePath;
    private array $cache = [];

    private const DESKTOP_MAX_WIDTH = 1600;
    private const MOBILE_MAX_WIDTH  = 800;
    private const DESKTOP_QUALITY   = 88;
    private const MOBILE_QUALITY    = 82;

    private const SUPPORTED_EXT = ['jpg', 'jpeg', 'png'];

    public function __construct(string $baseDir)
    {
        $this->sourceDir  = $baseDir . '/img';
        $this->desktopDir = $baseDir . '/imagenes/desktop';
        $this->mobileDir  = $baseDir . '/imagenes/mobile';
        $this->cachePath  = $baseDir . '/data/cache.json';

        $this->loadCache();
    }

    private function loadCache(): void
    {
        if (is_file($this->cachePath)) {
            $raw = file_get_contents($this->cachePath);
            $decoded = json_decode($raw ?: '[]', true);
            $this->cache = is_array($decoded) ? $decoded : [];
        }
    }

    private function saveCache(): void
    {
        @file_put_contents(
            $this->cachePath,
            json_encode($this->cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    public function processAll(): array
    {
        if (!is_dir($this->sourceDir)) {
            return [];
        }

        $items = [];
        $cacheDirty = false;
        $foundOriginals = [];

        $files = scandir($this->sourceDir) ?: [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $lowerFile = strtolower($file);
            if (str_ends_with($lowerFile, '.edit.jpg')) continue;
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, self::SUPPORTED_EXT, true)) continue;

            $originalPath = $this->sourceDir . '/' . $file;
            if (!is_file($originalPath)) continue;

            $base = pathinfo($file, PATHINFO_FILENAME);
            $editedPath = $this->sourceDir . '/' . $base . '.edit.jpg';
            $hasEditedVersion = is_file($editedPath);
            $sourcePath = $hasEditedVersion ? $editedPath : $originalPath;
            $foundOriginals[] = $file;

            $desktopFile = $this->desktopDir . '/' . $base . '.webp';
            $mobileFile  = $this->mobileDir  . '/' . $base . '.webp';

            $mtime = filemtime($originalPath) ?: 0;
            $editMtime = $hasEditedVersion ? (filemtime($editedPath) ?: 0) : 0;
            $editSize = $hasEditedVersion ? (filesize($editedPath) ?: 0) : 0;
            $cached = $this->cache[$file] ?? null;

            $needsProcess = !$cached
                || ($cached['mtime'] ?? 0) !== $mtime
                || ($cached['edit_mtime'] ?? 0) !== $editMtime
                || ($cached['edit_size'] ?? 0) !== $editSize
                || !is_file($desktopFile)
                || !is_file($mobileFile);

            if ($needsProcess) {
                $result = $this->generate($sourcePath, $desktopFile, $mobileFile, $originalPath);
                if ($result === null) continue;

                $this->cache[$file] = [
                    'mtime'         => $mtime,
                    'edit_mtime'    => $editMtime,
                    'edit_size'     => $editSize,
                    'desktop_w'     => $result['desktop_w'],
                    'desktop_h'     => $result['desktop_h'],
                    'mobile_w'      => $result['mobile_w'],
                    'mobile_h'      => $result['mobile_h'],
                    'aspect'        => $result['aspect'],
                    'exif_date'     => $result['exif_date'],
                    'desktop_file'  => $base . '.webp',
                    'mobile_file'   => $base . '.webp',
                ];
                $cacheDirty = true;
            }

            // Parsea metadatos del nombre: (Categoría)(lat,lng)
            $meta = parseFilenameMetadata($file);

            $items[] = [
                'original'  => $file,
                'base'      => $base,
                'mtime'     => $mtime,
                'desktop'   => 'imagenes/desktop/' . $base . '.webp',
                'mobile'    => 'imagenes/mobile/' . $base . '.webp',
                'aspect'    => $this->cache[$file]['aspect'] ?? 1.5,
                'desktop_w' => $this->cache[$file]['desktop_w'] ?? 0,
                'desktop_h' => $this->cache[$file]['desktop_h'] ?? 0,
                'exif_date' => $this->cache[$file]['exif_date'] ?? null,
                'sidecar'   => $this->sourceDir . '/' . $base . '.txt',
                'filename_clean' => $meta['clean'],        // Nombre limpio para la IA
                'filename_category' => $meta['category'],   // Categoría manual si existe
                'filename_lat'      => $meta['lat'],        // Lat manual si existe
                'filename_lng'      => $meta['lng'],        // Lng manual si existe
            ];
        }

        // Limpieza: borrar de cache y de /imagenes/ los archivos cuyo original ya no existe
        foreach (array_keys($this->cache) as $cachedName) {
            if (!in_array($cachedName, $foundOriginals, true)) {
                $base = pathinfo($cachedName, PATHINFO_FILENAME);
                @unlink($this->desktopDir . '/' . $base . '.webp');
                @unlink($this->mobileDir  . '/' . $base . '.webp');
                @unlink($this->sourceDir . '/' . $base . '.edit.jpg');
                unset($this->cache[$cachedName]);
                $cacheDirty = true;
            }
        }

        if ($cacheDirty) {
            $this->saveCache();
        }

        return $items;
    }

    private function generate(string $source, string $desktopOut, string $mobileOut, ?string $metadataSource = null): ?array
    {
        $info = @getimagesize($source);
        if (!$info) return null;

        [$w, $h, $type] = $info;
        $metadataSource ??= $source;

        $exifDate = null;
        $orientation = 1;
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($metadataSource);
            if (realpath($metadataSource) === realpath($source) && $type === IMAGETYPE_JPEG && $exif) {
                $orientation = (int)($exif['Orientation'] ?? 1);
            }
            if ($exif) {
                if (!empty($exif['DateTimeOriginal'])) {
                    $exifDate = $exif['DateTimeOriginal'];
                } elseif (!empty($exif['DateTime'])) {
                    $exifDate = $exif['DateTime'];
                }
            }
        }

        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG  => @imagecreatefrompng($source),
            default        => false,
        };

        if (!$src) return null;

        // Corregir orientación EXIF
        $src = $this->applyOrientation($src, $orientation);
        $w = imagesx($src);
        $h = imagesy($src);
        $aspect = $h > 0 ? $w / $h : 1.5;

        $desktop = $this->resizeAndWebp($src, $w, $h, self::DESKTOP_MAX_WIDTH, $desktopOut, self::DESKTOP_QUALITY);
        $mobile  = $this->resizeAndWebp($src, $w, $h, self::MOBILE_MAX_WIDTH,  $mobileOut,  self::MOBILE_QUALITY);

        imagedestroy($src);

        if (!$desktop || !$mobile) return null;

        return [
            'desktop_w' => $desktop[0],
            'desktop_h' => $desktop[1],
            'mobile_w'  => $mobile[0],
            'mobile_h'  => $mobile[1],
            'aspect'    => $aspect,
            'exif_date' => $exifDate,
        ];
    }

    private function resizeAndWebp(\GdImage $src, int $w, int $h, int $maxW, string $outPath, int $quality): ?array
    {
        if ($w <= $maxW) {
            $newW = $w;
            $newH = $h;
        } else {
            $newW = $maxW;
            $newH = (int) round($h * ($maxW / $w));
        }

        $dst = imagecreatetruecolor($newW, $newH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

        $ok = imagewebp($dst, $outPath, $quality);
        imagedestroy($dst);

        return $ok ? [$newW, $newH] : null;
    }

    private function applyOrientation(\GdImage $img, int $orientation): \GdImage
    {
        return match ($orientation) {
            3 => imagerotate($img, 180, 0) ?: $img,
            6 => imagerotate($img, -90, 0) ?: $img,
            8 => imagerotate($img,  90, 0) ?: $img,
            default => $img,
        };
    }
}
