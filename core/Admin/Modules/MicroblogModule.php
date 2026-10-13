<?php
declare(strict_types=1);

namespace Kcg\Core\Admin\Modules;

use Kcg\Core\Admin\AbstractModule;

/**
 * Microblog en el panel: nuevo post, hilos, edición y respuesta. La lógica está en inc/admin-microblog.php.
 */
final class MicroblogModule extends AbstractModule
{
    public function id(): string { return 'microblog'; }
    public function order(): int { return 40; }
    public function menu(): array { return [['id' => 'microblog', 'href' => '/admin.php?microblog=1', 'label' => 'Microblog', 'short' => 'Posts', 'order' => 40]]; }
    public function actions(): array { return ['microblog_save' => 'handle', 'microblog_delete' => 'handle']; }
    public function handle(string $action): void { \admin_microblog_handle($action, (string) $_SESSION['csrf']); }
    public function wantsPage(): bool { return isset($_GET['microblog']); }
    public function page(): void { \admin_microblog_page((string) $_SESSION['csrf']); }
    public function activeMenu(): ?string
    {
        $action = (string) ($_POST['action'] ?? '');
        return isset($_GET['microblog']) || $action === 'microblog_save' || $action === 'microblog_delete' ? 'microblog' : null;
    }
}
