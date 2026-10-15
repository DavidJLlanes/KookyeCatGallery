<?php
declare(strict_types=1);

/**
 * Instalador de línea de comandos: lo que usa `instalar.sh` en un VPS o servidor dedicado, y también sirve solo.
 *
 *   php scripts/install-cli.php --check
 *       Solo comprueba el servidor (PHP, extensiones, carpetas). Sale con 0 si se puede instalar y con 1 si no.
 *   php scripts/install-cli.php --url=https://tudominio.com --username=admin < contraseña.txt
 *       Instala. La contraseña se lee de la entrada estándar (o de la variable KCG_ADMIN_PASSWORD), nunca de un argumento:
 *       así no queda en el historial de la terminal ni en la lista de procesos.
 *
 * Debe ejecutarse con el usuario con el que corre PHP en el servidor web (p. ej. www-data), para que los archivos que cree
 * puedan leerse y escribirse luego desde la web. `instalar.sh` ya lo hace. Usa la misma lógica que install.php (core/Installer.php).
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once dirname(__DIR__) . '/core/bootstrap.php';

use Kcg\Core\Installer;

$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/s', $argument, $match)) $options[$match[1]] = $match[2] ?? '1';
}

$installer = new Installer(dirname(__DIR__));
$icons = ['ok' => '  OK    ', 'warn' => '  AVISO ', 'fail' => '  FALLO '];
$show = static function (array $checks) use ($icons): void {
    foreach ($checks as $check) {
        echo $icons[$check['status']] . $check['label'] . "\n";
        if ($check['hint'] !== '' && $check['status'] !== 'ok') echo '          ' . $check['hint'] . "\n";
    }
};

if (isset($options['check'])) {
    $checks = $installer->checks();
    $show($checks);
    exit($installer->blocked($checks) ? 1 : 0);
}

$url = (string) ($options['url'] ?? '');
$username = (string) ($options['username'] ?? '');
$password = getenv('KCG_ADMIN_PASSWORD') !== false ? (string) getenv('KCG_ADMIN_PASSWORD') : rtrim((string) stream_get_contents(STDIN), "\r\n");
if ($url === '' || $username === '') {
    fwrite(STDERR, "Uso: php scripts/install-cli.php --url=https://tudominio.com --username=admin  (contraseña por la entrada estándar)\n     php scripts/install-cli.php --check\n");
    exit(2);
}

try {
    if ($installer->hasAdministrator()) {
        fwrite(STDERR, "Esta web ya tiene un administrador registrado, así que no se puede registrar otro.\n");
        exit(3);
    }
    [$prepared, $warnings] = $installer->install($url, $username, $password);
} catch (InvalidArgumentException | DomainException | RuntimeException $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}

echo "Instalación completada.\n";
foreach ($prepared as $line) echo '  - ' . $line . "\n";
foreach ($warnings as $line) echo '  ! ' . $line . "\n";
echo '  Entra en ' . rtrim($url, '/') . "/admin.php\n";
