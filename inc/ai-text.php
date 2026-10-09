<?php
/**
 * Generador de títulos/descripciones con IA en cascada.
 *
 * Estrategia: prueba varios modelos/proveedores en orden. Si todos fallan,
 * usa un fallback determinista basado en el nombre del archivo.
 *
 * Marca cada .txt con un comentario al final (# auto-gemini/..., # auto-openrouter/...
 * o # auto-filename) para que sepas qué fuente generó el texto.
 *
 * Para regenerar: borra el .txt y la próxima visita lo vuelve a generar.
 */

declare(strict_types=1);

class AiTextGenerator
{
    private array  $config;
    private string $sourceDir;
    private int    $lastHttpCode = 0;
    private float  $startTime    = 0.0;

    private const PER_CALL_TIMEOUT      = 5;    // segundos por llamada API
    private const TOTAL_BUDGET_SECONDS  = 25;   // tiempo máximo total para toda la IA en una request
    private const RETRY_COOLDOWN_SECONDS = 300; // 5 min antes de reintentar un fallback del nombre

    public function __construct(string $baseDir, array $config)
    {
        $this->config    = $config;
        $this->sourceDir = $baseDir . '/img';
    }

    /**
     * Procesa fotos nuevas (sin .txt) y reintenta los fallbacks del nombre.
     * Devuelve nº de textos generados/mejorados.
     */
    public function processMissing(array $items, int $maxPerRequest): int
    {
        $this->startTime = microtime(true);
        $generated = 0;
        $cascade   = $this->config['cascade'] ?? [];

        // Recopilar objetivos con prioridad
        // 0 = foto nueva sin .txt (alta prioridad)
        // 1 = .txt existente con marca "# auto-filename" (mejorable)
        $targets = [];
        foreach ($items as $item) {
            $sidecar = $item['sidecar'] ?? '';
            if ($sidecar === '') continue;

            if (!is_file($sidecar)) {
                $targets[] = ['item' => $item, 'priority' => 0, 'isRetry' => false, 'isDescriptionOnly' => false];
                continue;
            }

            // Fallbacks automáticos antiguos y descripciones pendientes de fotos subidas.
            $content = @file_get_contents($sidecar);
            if ($content === false) continue;
            $isDescriptionOnly = str_contains($content, '# auto-description-pending')
                || str_contains($content, '# auto-description-retry');
            $isFilenameRetry = str_contains($content, '# auto-filename');
            if (!$isDescriptionOnly && !$isFilenameRetry) continue;

            $age = time() - (int) @filemtime($sidecar);
            $isRetryMarker = str_contains($content, '# auto-description-retry');
            if (($isRetryMarker || $isFilenameRetry) && $age < self::RETRY_COOLDOWN_SECONDS) continue;

            $targets[] = [
                'item' => $item,
                'priority' => $isDescriptionOnly ? 0 : 1,
                'isRetry' => true,
                'isDescriptionOnly' => $isDescriptionOnly,
            ];
        }

        // Fotos nuevas primero
        usort($targets, fn($a, $b) => $a['priority'] <=> $b['priority']);

        foreach ($targets as $t) {
            if ($generated >= $maxPerRequest) break;
            if ($this->timeExhausted()) break;

            $item     = $t['item'];
            $isRetry  = $t['isRetry'];
            $isDescriptionOnly = $t['isDescriptionOnly'] ?? false;
            $original = $item['original'] ?? '';
            $sidecar  = $item['sidecar']  ?? '';
            if ($original === '' || $sidecar === '') continue;

            // Usa el nombre limpio si existe (sin paréntesis de categoría/coords)
            $filenameForAI = $item['filename_clean'] ?? $original;

            // Probar cascada
            $result     = null;
            $usedSource = null;
            foreach ($cascade as $step) {
                [$provider, $model] = $step;
                $result = $this->callProvider($provider, $model, $filenameForAI);
                if ($result !== null) {
                    $usedSource = "{$provider}/{$model}";
                    break;
                }
                if ($this->timeExhausted()) break; // solo salir de la cascada, no del foreach externo
            }

            if ($result !== null && $usedSource !== null) {
                if ($isDescriptionOnly) {
                    $existingMeta = function_exists('read_sidecar') ? read_sidecar($sidecar) : [];
                    $preservedTitle = trim((string) ($existingMeta['title'] ?? ''));
                    $this->writeSidecar(
                        $sidecar,
                        $preservedTitle !== '' ? $preservedTitle : $result['title'],
                        $result['description'],
                        $usedSource
                    );
                } else {
                    $this->writeSidecar($sidecar, $result['title'], $result['description'], $usedSource);
                }
                $generated++;
            } elseif ($isDescriptionOnly) {
                $pending = @file_get_contents($sidecar);
                if (is_string($pending)) {
                    $pending = str_replace('# auto-description-pending', '# auto-description-retry', $pending);
                    @file_put_contents($sidecar, $pending);
                }
            } elseif (!$isRetry) {
                // Foto nueva y IA falló: usar fallback del nombre LIMPIO (sin paréntesis
                // de categoría/coords) para que la foto tenga texto provisional.
                // (en visitas posteriores se intentará mejorar con IA real)
                $fallback = $this->filenameFallback($filenameForAI);
                $this->writeSidecar($sidecar, $fallback['title'], $fallback['description'], 'filename');
                $generated++;
            }
            // Si era un reintento y la IA volvió a fallar, no tocamos nada (mantiene fallback antiguo)
        }

        return $generated;
    }

    private function timeExhausted(): bool
    {
        return (microtime(true) - $this->startTime) > self::TOTAL_BUDGET_SECONDS;
    }

    /* ===== PROVEEDORES ===== */

    private function callProvider(string $provider, string $model, string $filename): ?array
    {
        $prompt = $this->buildPrompt($filename);

        return match ($provider) {
            'gemini'     => $this->callGemini($prompt, $model),
            'openrouter' => $this->callOpenRouter($prompt, $model),
            default      => null,
        };
    }

    private function callGemini(string $prompt, string $model): ?array
    {
        $apiKey = $this->config['gemini_api_key'] ?? '';
        if ($apiKey === '' || str_starts_with($apiKey, 'PEGA_')) return null;

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature'      => 0.85,
                'maxOutputTokens'  => 400,
                'responseMimeType' => 'application/json',
                'responseSchema'   => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'title'       => ['type' => 'STRING'],
                        'description' => ['type' => 'STRING'],
                    ],
                    'required' => ['title', 'description'],
                ],
            ],
        ];

        $raw = $this->httpPost($url, $payload, [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $apiKey,
        ]);
        if ($raw === null) return null;

        $decoded = json_decode($raw, true);
        $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
        return is_string($text) ? $this->parseJsonResponse($text) : null;
    }

    private function callOpenRouter(string $prompt, string $model): ?array
    {
        $apiKey = $this->config['openrouter_api_key'] ?? '';
        if ($apiKey === '' || str_starts_with($apiKey, 'PEGA_')) return null;

        $url = 'https://openrouter.ai/api/v1/chat/completions';
        $payload = [
            'model'    => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.85,
            'max_tokens'  => 400,
        ];

        $raw = $this->httpPost($url, $payload, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'HTTP-Referer: https://davidjimenezllanes.es',
            'X-Title: Fotos de Leon',
        ]);
        if ($raw === null) return null;

        $decoded = json_decode($raw, true);
        $text = $decoded['choices'][0]['message']['content'] ?? null;
        return is_string($text) ? $this->parseJsonResponse($text) : null;
    }

    /* ===== HTTP ===== */

    private function httpPost(string $url, array $payload, array $headers): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::PER_CALL_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->lastHttpCode = (int) $httpCode;

        if ($httpCode !== 200 || $raw === false) return null;
        return (string) $raw;
    }

    /* ===== PARSING ===== */

    private function parseJsonResponse(string $response): ?array
    {
        $response = trim($response);

        // Quitar code fences ```json ... ```
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $response, $m)) {
            $response = trim($m[1]);
        }

        // Extraer el primer objeto JSON
        if (preg_match('/\{[\s\S]*\}/', $response, $m)) {
            $response = $m[0];
        }

        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['title'])) return null;

        $title       = trim((string) $data['title']);
        $description = trim((string) ($data['description'] ?? ''));

        if ($title === '') return null;
        return ['title' => $title, 'description' => $description];
    }

    /* ===== FALLBACK DEL NOMBRE ===== */

    private function filenameFallback(string $filename): array
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $clean = trim(preg_replace('/[-_]+/', ' ', $base) ?? $base);

        // Capitalizar como título, dejando preposiciones comunes en minúscula
        $stopwords = ['de', 'del', 'la', 'el', 'los', 'las', 'y', 'en', 'a', 'al', 'con', 'por', 'para'];
        $words = explode(' ', $clean);
        $out = [];
        foreach ($words as $i => $w) {
            $lw = mb_strtolower($w, 'UTF-8');
            if ($i === 0 || !in_array($lw, $stopwords, true)) {
                $out[] = mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($w, 1, null, 'UTF-8');
            } else {
                $out[] = $lw;
            }
        }
        $title = trim(implode(' ', $out));
        if ($title === '') $title = 'Fotografía de León';

        return ['title' => $title, 'description' => ''];
    }

    /* ===== ESCRITURA Y PROMPT ===== */

    private function writeSidecar(string $path, string $title, string $description, string $source): void
    {
        // Preservar metadatos manuales del archivo anterior (categoría, coordenadas, slug)
        $existingSlug        = '';
        $existingCategoria   = '';
        $existingCoordenadas = '';
        if (is_file($path)) {
            $prev = file_get_contents($path) ?: '';
            if (preg_match('/^# Slug:\s*([a-z0-9-]+)$/m', $prev, $m))         $existingSlug        = trim($m[1]);
            if (preg_match('/^# Categoría:\s*(.+?)$/m', $prev, $m))            $existingCategoria   = trim($m[1]);
            if (preg_match('/^# Coordenadas:\s*[\d\.\-]+,[\d\.\-]+$/m', $prev, $m)) $existingCoordenadas = trim($m[0]);
        }

        $content = $title;
        if ($description !== '') {
            $content .= "\n---\n" . $description;
        }
        $content .= "\n\n# auto-{$source}\n";

        // Slug: solo para títulos generados por IA real (no fallback de nombre de archivo)
        if ($source !== 'filename') {
            $slug = $existingSlug !== '' ? $existingSlug : generate_slug($title);
            $content .= "# Slug: {$slug}\n";
        }

        if ($existingCategoria   !== '') $content .= "# Categoría: {$existingCategoria}\n";
        if ($existingCoordenadas !== '') $content .= "{$existingCoordenadas}\n";

        @file_put_contents($path, $content);
    }

    private function buildPrompt(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $readable = trim(preg_replace('/[-_]+/', ' ', $base) ?? $base);

        return <<<PROMPT
Eres un fotógrafo evocador escribiendo para una galería online llamada "Fotos de León" sobre la provincia de León (España).

NOMBRE DEL ARCHIVO DE LA FOTO: "{$filename}"
NOMBRE LEGIBLE: "{$readable}"

Tu tarea:
1. Si el nombre describe un lugar/tema concreto (ej: "catedral-leon-amanecer", "palacio-gaudi-astorga", "picos-europa-niebla"), genera un título corto evocador (3-7 palabras) y una descripción atmosférica de 1-2 frases en español, sugiriendo la imagen.

2. Si el nombre NO es descriptivo (ej: "IMG_4521", "DSC0123", "foto1"), genera un título y descripción genéricos pero bonitos para "una fotografía de León", SIN inventar lugar específico ni datos.

REGLAS ESTRICTAS:
- NUNCA inventes fechas, autores, eventos históricos o datos verificables falsos.
- Tono evocador, atmosférico, casi poético. NUNCA cliché turístico.
- No uses comillas dobles dentro del título ni de la descripción.
- Idioma: español (España).

Responde SOLO con JSON válido en este formato exacto:
{"title": "...", "description": "..."}
PROMPT;
    }
}
