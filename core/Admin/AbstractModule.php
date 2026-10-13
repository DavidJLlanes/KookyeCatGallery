<?php
declare(strict_types=1);

namespace Kcg\Core\Admin;

/**
 * Base de los módulos del panel: valores por defecto para todo lo opcional.
 *
 * Un módulo mínimo solo define id() y lo que ofrece: menu(), actions() (nombre => método protegido), wantsPage() y page().
 */
abstract class AbstractModule implements Module
{
    public function order(): int
    {
        return 100;
    }

    public function menu(): array
    {
        return [];
    }

    public function actions(): array
    {
        return [];
    }

    public function publicActions(): array
    {
        return [];
    }

    public function handle(string $action): void
    {
        $method = $this->actions()[$action] ?? null;
        if ($method !== null) $this->{$method}();
    }

    public function wantsPage(): bool
    {
        return false;
    }

    public function page(): void
    {
    }

    public function activeMenu(): ?string
    {
        return null;
    }
}
