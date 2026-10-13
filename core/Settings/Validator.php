<?php
declare(strict_types=1);

namespace Kcg\Core\Settings;

use InvalidArgumentException;

/**
 * Valida y normaliza los ajustes recibidos (formulario, JSON o archivo guardado) según el Schema.
 *
 * Lanza InvalidArgumentException con un mensaje legible si algo no es válido; si todo está bien devuelve siempre un
 * conjunto completo de ajustes, con los valores por defecto donde falte algo. Primero se aplican las migraciones
 * (core/Settings/migrations/), de modo que los formatos antiguos se aceptan y se actualizan.
 *
 * Las comprobaciones se hacen por tipo, en este orden: opciones, interruptores, orden de bloques, columnas, fotos
 * visibles, título, textos, páginas, imágenes, web personal y redes sociales. Si varios ajustes son inválidos a la
 * vez se informa del primero según ese orden.
 */
final class Validator
{
    private const PHASES = ['choice', 'bool', 'order', 'int-range', 'int-choice', 'title', 'text-map', 'html-map', 'path', 'https-url', 'social-list'];

    public static function validate(array $input, bool $fromFile = false): array
    {
        $input = Migrations::apply($input, $fromFile);
        $out = Schema::defaults();
        $out['settings_version'] = Schema::currentVersion();

        // Con una galería premium activa, sus campos ignorados llegan desactivados (no se envían): se dejan como estén.
        $premiumOn = isset($input['gallery_premium']) && is_string($input['gallery_premium']) && $input['gallery_premium'] !== 'none';
        $ignored = $premiumOn ? \site_premium_ignored_settings($input['gallery_premium']) : [];

        foreach (self::PHASES as $type) {
            foreach (Schema::definitions() as $key => $definition) {
                if ($definition['type'] !== $type) continue;
                if (in_array($type, ['choice', 'int-range', 'int-choice'], true) && in_array($key, $ignored, true) && !isset($input[$key])) continue;
                self::check($type, $key, $definition, $input, $out);
            }
        }
        return $out;
    }

    private static function check(string $type, string $key, array $definition, array $input, array &$out): void
    {
        switch ($type) {
            case 'choice':
                $choices = ($definition['choices'])();
                if (!isset($input[$key]) || !is_string($input[$key]) || !isset($choices[$input[$key]])) {
                    throw new InvalidArgumentException($definition['error'] ?? 'Selecciona una opción válida para ' . $key . '.');
                }
                $out[$key] = $input[$key];
                return;
            case 'bool':
                $out[$key] = filter_var($input[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
                return;
            case 'order':
                $out[$key] = \site_section_order_normalize($input[$key] ?? null);
                return;
            case 'int-range':
                $value = filter_var($input[$key] ?? null, FILTER_VALIDATE_INT);
                if ($value === false || $value < $definition['min'] || $value > $definition['max']) {
                    throw new InvalidArgumentException($definition['error']);
                }
                $out[$key] = $value;
                return;
            case 'int-choice':
                $value = filter_var($input[$key] ?? $out[$key], FILTER_VALIDATE_INT);
                if ($value === false || !in_array($value, ($definition['options'])(), true)) {
                    throw new InvalidArgumentException($definition['error']);
                }
                $out[$key] = $value;
                return;
            case 'title':
                // Vacío = el título por defecto del catálogo de textos.
                $title = trim((string) ($input[$key] ?? ''));
                if (!preg_match('//u', $title) || preg_match('/[\x00-\x1F\x7F]/', $title) || \site_utf8_length($title) > 80) {
                    throw new InvalidArgumentException('El título de la web no es válido (máximo 80 caracteres y sin saltos de línea).');
                }
                $out[$key] = $title === \site_title_default() ? '' : $title;
                return;
            case 'text-map':
                if (!isset($input[$key]) || !is_array($input[$key])) {
                    throw new InvalidArgumentException('Los textos no tienen un formato válido.');
                }
                foreach (\site_editable_text_keys() as $textKey) {
                    $entry = \site_text_catalog()[$textKey];
                    $value = $input[$key][$textKey] ?? $entry['default'];
                    if (!is_string($value) || strlen($value) > 20000 || !preg_match('//u', $value)) {
                        throw new InvalidArgumentException('Hay un texto inválido o demasiado largo.');
                    }
                    if ($textKey === 'admin_area_name' && (\site_utf8_length($value) > 120 || preg_match('/[\\x00-\\x1F\\x7F]/u', $value))) {
                        throw new InvalidArgumentException('El nombre del área privada no puede superar 120 caracteres.');
                    }
                    if ($value !== $entry['default']) $out[$key][$textKey] = $value;
                }
                return;
            case 'html-map':
                $pages = $input[$key] ?? [];
                if (!is_array($pages)) throw new InvalidArgumentException('El contenido de las páginas no tiene un formato válido.');
                foreach (\site_editable_page_keys() as $pageKey) {
                    if (!array_key_exists($pageKey, $pages)) continue;
                    if (!is_string($pages[$pageKey])) throw new InvalidArgumentException('El contenido de una página no es válido.');
                    $out[$key][$pageKey] = \site_sanitize_page_html($pages[$pageKey]);
                }
                return;
            case 'path':
                $value = (string) ($input[$key] ?? $definition['default']);
                if (!preg_match('~^/[a-zA-Z0-9._/-]+$~', $value)) {
                    throw new InvalidArgumentException('La ruta de imagen no es válida.');
                }
                $out[$key] = $value;
                return;
            case 'https-url':
                $url = trim((string) ($input[$key] ?? $out[$key]));
                if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('~^https://~i', $url))) {
                    throw new InvalidArgumentException('La dirección de la web personal debe empezar por https://.');
                }
                $out[$key] = $url;
                return;
            case 'social-list':
                $rows = $input[$key] ?? [];
                if (!is_array($rows)) throw new InvalidArgumentException('Las redes sociales no tienen un formato válido.');
                $allowed = array_keys(\site_social_networks());
                $out[$key] = [];
                foreach ($rows as $row) {
                    if (!is_array($row)) continue;
                    $network = strtolower(trim((string) ($row['network'] ?? '')));
                    $url = trim((string) ($row['url'] ?? ''));
                    $handle = trim((string) ($row['handle'] ?? ''));
                    if ($network === '' && $url === '' && $handle === '') continue;
                    if (!in_array($network, $allowed, true)) throw new InvalidArgumentException('Selecciona una red social válida.');
                    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('~^https://~i', $url)) throw new InvalidArgumentException('La URL de una red social no es válida.');
                    if (strlen($handle) > 120 || !preg_match('//u', $handle)) throw new InvalidArgumentException('El usuario de una red social no es válido.');
                    $out[$key][] = ['network' => $network, 'url' => $url, 'handle' => $handle];
                    if (count($out[$key]) >= 12) break;
                }
                return;
            case 'version':
                return;
        }
    }
}
