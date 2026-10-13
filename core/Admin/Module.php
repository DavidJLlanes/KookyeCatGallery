<?php
declare(strict_types=1);

namespace Kcg\Core\Admin;

/**
 * Un módulo del panel de administración: un grupo de pantallas y acciones con su entrada (o entradas) del menú.
 *
 * Los módulos viven en core/Admin/Modules/<Nombre>Module.php y el Router los descubre solos: añadir un módulo es crear un
 * archivo. Se recomienda heredar de AbstractModule, que ya trae valores por defecto para todo lo opcional.
 */
interface Module
{
    /** Identificador del módulo (minúsculas). */
    public function id(): string;

    /** Posición al recorrer los módulos (menor primero). Los menús se ordenan por su propio `order`. */
    public function order(): int;

    /**
     * Entradas del menú: cada una con id (también el de su icono), href, label (menú lateral), short (barra inferior
     * del móvil) y order (posición en el menú).
     *
     * @return list<array{id:string, href:string, label:string, short:string, order:int}>
     */
    public function menu(): array;

    /**
     * Acciones (POST) que atiende: nombre de la acción => método que la ejecuta. Toda acción termina la petición
     * (redirección, página o JSON) y exige token CSRF.
     *
     * @return array<string, string>
     */
    public function actions(): array;

    /** Acciones que se pueden pedir sin haber iniciado sesión (solo el inicio de sesión). */
    public function publicActions(): array;

    /** Ejecuta una de sus acciones. */
    public function handle(string $action): void;

    /** ¿Dibuja este módulo la pantalla (GET) de la petición actual? */
    public function wantsPage(): bool;

    /** Dibuja la pantalla y termina la petición. */
    public function page(): void;

    /** Entrada del menú que se resalta en esta petición (GET o POST), o null si no es de este módulo. */
    public function activeMenu(): ?string;
}
