<?php
declare(strict_types=1);

namespace Kcg\Core;

/**
 * Registro de opciones declaradas una por archivo.
 *
 * Cada opción es un archivo `<carpeta>/<id>.php` que devuelve un array con su definición. El identificador es el nombre
 * del archivo (minúsculas con guiones). El orden de presentación lo da la clave `order` (menor primero; a igual orden,
 * por identificador), de modo que añadir una opción nueva es crear un archivo, sin tocar el resto del código.
 *
 *   $palettes = Registry::for('palettes');   // themes/palettes/*.php
 *   $palettes->get('ocean')['name'];
 *   $palettes->choices();                    // id => nombre, para formularios y validación
 */
final class Registry
{
    /** @var array<string, self> */
    private static array $instances = [];

    /** @var array<string, array<string, mixed>>|null */
    private ?array $definitions = null;

    private function __construct(
        private readonly string $type,
        private readonly string $directory,
        /** @var list<string> claves obligatorias en cada definición */
        private readonly array $required = ['name'],
    ) {
    }

    /** Registro de un tipo de `themes/` (paletas, fuentes, cabeceras…). */
    public static function for(string $type): self
    {
        return self::$instances[$type] ??= new self($type, dirname(__DIR__) . '/themes/' . $type, self::requiredFor($type));
    }

    /** Registro sobre una carpeta cualquiera (pruebas y módulos). */
    public static function at(string $type, string $directory, array $required = ['name']): self
    {
        return new self($type, $directory, $required);
    }

    /** Descarta lo cargado (pruebas). */
    public static function reset(): void
    {
        self::$instances = [];
    }

    /** Claves obligatorias de cada tipo de `themes/`. */
    public static function requiredFor(string $type): array
    {
        return match ($type) {
            'palettes' => ['name', 'mode', 'vars'],
            'fonts' => ['name', 'weights'],
            'pairs' => ['name', 'heading', 'body', 'group'],
            'galleries' => ['name', 'devices', 'capabilities'],
            'badges', 'socials' => ['name', 'svg'],
            'project-layouts' => ['name', 'media'],
            default => ['name'],
        };
    }

    public function type(): string
    {
        return $this->type;
    }

    /** @return array<string, array<string, mixed>> id => definición, ya ordenadas */
    public function all(): array
    {
        if ($this->definitions !== null) return $this->definitions;
        $found = [];
        foreach (glob($this->directory . '/*.php') ?: [] as $file) {
            $id = basename($file, '.php');
            if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $id)) {
                throw new \RuntimeException("{$this->type}: «{$id}» no es un identificador válido (minúsculas con guiones): $file");
            }
            $definition = require $file;
            if (!is_array($definition)) throw new \RuntimeException("{$this->type}/{$id}.php debe devolver un array.");
            foreach ($this->required as $key) {
                if (!array_key_exists($key, $definition)) throw new \RuntimeException("{$this->type}/{$id}.php: falta la clave «{$key}».");
            }
            $definition['id'] = $id;
            $definition['order'] = (int) ($definition['order'] ?? 1000);
            $found[$id] = $definition;
        }
        uasort($found, static fn(array $a, array $b): int => [$a['order'], $a['id']] <=> [$b['order'], $b['id']]);
        return $this->definitions = $found;
    }

    public function has(string $id): bool
    {
        return isset($this->all()[$id]);
    }

    /** Definición de una opción, o null si no existe. */
    public function get(string $id): ?array
    {
        return $this->all()[$id] ?? null;
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_keys($this->all());
    }

    /** @return array<string, string> id => nombre */
    public function choices(?callable $filter = null): array
    {
        $choices = [];
        foreach ($this->all() as $id => $definition) {
            if ($filter === null || $filter($definition)) $choices[$id] = (string) $definition['name'];
        }
        return $choices;
    }
}
