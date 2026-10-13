<?php
declare(strict_types=1);

namespace Kcg\Core\Admin\Modules;

use AiTextGenerator;
use InvalidArgumentException;
use Kcg\Core\Admin\AbstractModule;
use Kcg\Core\Ai\Settings;
use RuntimeException;

/**
 * Pestaña «IA» del panel: activar o desactivar la generación de textos, claves de Gemini y OpenRouter, modelos, orden de los
 * proveedores, límite por visita y el contexto y el estilo con que se redactan los textos. Se guarda con core/Ai/Settings.php;
 * las claves nunca se vuelven a mostrar (solo los últimos cuatro caracteres).
 */
final class AiModule extends AbstractModule
{
    public function id(): string { return 'ai'; }
    public function order(): int { return 45; }
    public function menu(): array { return [['id' => 'ai', 'href' => '/admin.php?ai=1', 'label' => 'Inteligencia artificial', 'short' => 'IA', 'order' => 45]]; }
    public function actions(): array { return ['ai_save' => 'save', 'ai_test' => 'test']; }
    public function wantsPage(): bool { return isset($_GET['ai']); }
    public function page(): void { $this->render(); }
    public function activeMenu(): ?string
    {
        $action = (string) ($_POST['action'] ?? '');
        return isset($_GET['ai']) || $action === 'ai_save' || $action === 'ai_test' ? 'ai' : null;
    }

    /** Guarda los ajustes. */
    protected function save(): void
    {
        try {
            Settings::save($_POST);
            header('Location: /admin.php?ai=1&status=' . rawurlencode('Ajustes de la IA guardados.'), true, 303);
            exit;
        } catch (InvalidArgumentException | RuntimeException $error) {
            $this->render(['error' => $error->getMessage()], 422);
        }
    }

    /** Prueba un proveedor con la clave y el modelo ya guardados. */
    protected function test(): void
    {
        $provider = (string) ($_POST['provider'] ?? '');
        if (!isset(Settings::PROVIDERS[$provider])) $this->render(['error' => 'Proveedor no válido.'], 422);
        $config = \ai_text_config([], \site_text('text_52179dc42df7efe5'), \Kcg\Core\Installation::siteUrl());
        $model = Settings::DEFAULT_MODELS[$provider];
        foreach ($config['cascade'] as [$name, $configured]) {
            if ($name === $provider) $model = (string) $configured;
        }
        $result = (new AiTextGenerator(\Kcg\Core\Installation::root(), $config))->probe($provider, $model);
        $this->render([$result['ok'] ? 'ok' : 'error' => Settings::PROVIDERS[$provider] . ': ' . $result['message']], $result['ok'] ? 200 : 422);
    }

    /** @param array{ok?: string, error?: string} $notice */
    private function render(array $notice = [], int $status = 200): never
    {
        $e = 'uploadEscape';
        $csrf = (string) $_SESSION['csrf'];
        $s = Settings::exists() ? Settings::load() : Settings::defaults();
        $values = $notice && isset($_POST['action']) && $_POST['action'] === 'ai_save' ? array_replace($s, array_intersect_key($_POST, $s), ['enabled' => !empty($_POST['enabled'])]) : $s;
        $locked = Settings::lockedByEnvironment();
        $lock = static fn(string $field): string => in_array($field, $locked, true) ? ' disabled' : '';
        $lockNote = static fn(string $field): string => in_array($field, $locked, true)
            ? '<small class="upload-help">Fijado por una variable de entorno del hosting: no se puede cambiar desde aquí.</small>' : '';

        $message = isset($notice['error']) ? '<p class="upload-message upload-message--error" role="alert">' . $e($notice['error']) . '</p>'
            : (isset($notice['ok']) ? '<p class="upload-message" role="status">' . $e($notice['ok']) . '</p>'
            : (isset($_GET['status']) ? '<p class="upload-message" role="status">' . $e((string) $_GET['status']) . '</p>' : ''));

        $config = \ai_text_config([], \site_text('text_52179dc42df7efe5'), \Kcg\Core\Installation::siteUrl());
        $state = $config['ai_enabled']
            ? ($config['gemini_api_key'] !== '' || $config['openrouter_api_key'] !== '' ? '<strong>activada</strong>: las fotos nuevas sin descripción reciben título y descripción automáticos.'
                : '<strong>activada pero sin clave</strong>: solo se usa el título derivado del nombre del archivo.')
            : '<strong>desactivada</strong>: no se envía nada a ningún proveedor.';

        $provider = function (string $id) use ($e, $s, $values, $locked, $lock, $lockNote, $csrf): string {
            $label = Settings::PROVIDERS[$id];
            $stored = Settings::mask($s[$id . '_api_key']);
            $help = $id === 'gemini'
                ? 'Se crea en <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">Google AI Studio</a>.'
                : 'Se crea en <a href="https://openrouter.ai/keys" target="_blank" rel="noopener">openrouter.ai/keys</a>.';
            $html = '<section class="admin-card"><header class="admin-card__head"><h2>' . $e($label) . '</h2><p>' . $help . '</p></header><div class="admin-card__body">'
                . '<div class="upload-field"><label for="ai-' . $id . '-key">Clave de API</label>'
                . '<input id="ai-' . $id . '-key" name="' . $id . '_api_key" type="password" autocomplete="off" spellcheck="false" maxlength="300"'
                . ' placeholder="' . $e($stored !== '' ? 'Guardada: ' . $stored . ' (déjalo vacío para conservarla)' : 'Pega aquí la clave') . '"' . (in_array($id . '_api_key', $locked, true) ? ' disabled' : '') . '>'
                . ($stored !== '' ? '<label class="site-section-switch"><input type="checkbox" name="clear_' . $id . '_api_key" value="1"><span class="site-section-switch__track" aria-hidden="true"></span><span>Borrar la clave guardada al guardar</span></label>' : '')
                . $lockNote($id . '_api_key') . '</div>'
                . '<div class="upload-field"><label for="ai-' . $id . '-model">Modelo</label>'
                . '<input id="ai-' . $id . '-model" name="' . $id . '_model" type="text" maxlength="100" value="' . $e((string) $values[$id . '_model']) . '" spellcheck="false">'
                . '<small class="upload-help">Por defecto: <code>' . $e(Settings::DEFAULT_MODELS[$id]) . '</code>.</small></div>'
                . ($s[$id . '_api_key'] !== '' || in_array($id . '_api_key', $locked, true)
                    ? '<div class="upload-actions"><button class="upload-submit" type="submit" form="ai-test-' . $id . '">Probar la conexión</button></div>'
                        . '<small class="upload-help">Prueba con la clave y el modelo ya guardados (guarda antes si los has cambiado).</small>' : '')
                . '</div></section>';
            return $html;
        };

        $order = '<div class="upload-field"><label for="ai-first">Proveedor que se prueba primero</label><select id="ai-first" name="first"' . $lock('order') . '>';
        foreach (Settings::PROVIDERS as $id => $label) $order .= '<option value="' . $id . '"' . ($values['first'] === $id ? ' selected' : '') . '>' . $e($label) . '</option>';
        $order .= '</select><small class="upload-help">Si el primero falla o no tiene clave, se prueba el otro. Si ambos fallan se usa el título del nombre del archivo.</small>' . $lockNote('order') . '</div>';

        $generator = new AiTextGenerator(\Kcg\Core\Installation::root(), $config);
        $example = $generator->promptFor('atardecer-en-la-playa.jpg');

        $form = '<form id="ai-form" method="post" action="/admin.php?ai=1" class="upload-form"><input type="hidden" name="action" value="ai_save"><input type="hidden" name="csrf" value="' . $e($csrf) . '">'
            . '<section class="admin-card"><header class="admin-card__head"><h2>Generación de textos</h2><p>La IA propone título y descripción para las fotos que subes sin ellos. Tus textos manuales se conservan siempre.</p></header><div class="admin-card__body">'
            . '<p class="upload-help">Ahora está ' . $state . '</p>'
            . '<label class="site-section-switch"><input type="checkbox" name="enabled" value="1"' . (!empty($values['enabled']) ? ' checked' : '') . $lock('enabled') . '>'
            . '<span class="site-section-switch__track" aria-hidden="true"></span><span>Activar la IA</span></label>' . $lockNote('enabled')
            . $order
            . '<div class="upload-field"><label for="ai-max">Fotos por visita</label><input id="ai-max" name="max_per_request" type="number" min="1" max="10" value="' . (int) $values['max_per_request'] . '"' . $lock('max_per_request') . '>'
            . '<small class="upload-help">Máximo de textos que se generan en cada visita a la portada (1 a 10). Evita esperas largas.</small>' . $lockNote('max_per_request') . '</div>'
            . '</div></section>'
            . '<section class="admin-card"><header class="admin-card__head"><h2>Cómo escribe</h2><p>Estos textos se añaden a la petición que se envía al proveedor.</p></header><div class="admin-card__body">'
            . '<div class="upload-field"><label for="ai-style">Estilo de los textos</label>'
            . '<textarea id="ai-style" name="style" rows="4" maxlength="' . Settings::MAX_TEXT . '" placeholder="Ej.: tono cercano y poético, frases cortas, sin exclamaciones; tutea al lector.">' . $e((string) $values['style']) . '</textarea>'
            . '<small class="upload-help">Cómo debe sonar: tono, longitud, persona, palabras que evitar… Máximo ' . Settings::MAX_TEXT . ' caracteres.</small></div>'
            . '<div class="upload-field"><label for="ai-context">Contexto de la galería</label>'
            . '<textarea id="ai-context" name="context" rows="3" maxlength="' . Settings::MAX_TEXT . '"' . $lock('context') . ' placeholder="Ej.: fotografía de paisaje y arquitectura de la provincia de León.">' . $e((string) $values['context']) . '</textarea>'
            . '<small class="upload-help">De qué trata tu galería, para que los textos encajen.</small>' . $lockNote('context') . '</div>'
            . '<details class="mb-fold"><summary>Ver la petición que se envía (con un nombre de ejemplo)</summary><div class="mb-fold__body"><pre style="white-space:pre-wrap;margin:0">' . $e($example) . '</pre>'
            . '<small class="upload-help">Refleja lo ya guardado: guarda los cambios para ver el efecto del estilo.</small></div></details>'
            . '</div></section>'
            . $provider('gemini') . $provider('openrouter')
            . '<div class="upload-actions"><button class="upload-submit" type="submit">Guardar</button></div></form>';
        // Un formulario aparte por proveedor para «Probar la conexión» (los botones se enlazan con el atributo form).
        $tests = '';
        foreach (array_keys(Settings::PROVIDERS) as $id) {
            $tests .= '<form id="ai-test-' . $id . '" method="post" action="/admin.php?ai=1" hidden><input type="hidden" name="action" value="ai_test">'
                . '<input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="provider" value="' . $id . '"></form>';
        }

        $content = \adminNavigation($csrf) . $message . $form . $tests
            . '<p class="upload-help" style="margin-top:1.5rem">Las claves se guardan en la carpeta privada del servidor (fuera de la web) y no se vuelven a mostrar. Al generar un texto se envía al proveedor el nombre del archivo de la foto y el contexto y el estilo de arriba, nada más. Si tu hosting define variables de entorno de IA, mandan sobre estos ajustes.</p>';
        \uploadPage('Inteligencia artificial', $content, $status, true);
        exit;
    }
}
