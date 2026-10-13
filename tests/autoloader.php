<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

use Kcg\Core\Autoloader;

function autoloader_assert(bool $condition, string $message): void
{
    if ($condition) return;
    fwrite(STDERR, "FALLO: $message\n");
    exit(1);
}

// Las tres raíces del proyecto están registradas y apuntan a carpetas que existen.
$roots = Autoloader::roots();
foreach (['Kcg\\Core\\' => 'core', 'Kcg\\Modules\\' => 'modules', 'Kcg\\Themes\\' => 'themes'] as $prefix => $folder) {
    autoloader_assert(isset($roots[$prefix]), "falta la raíz $prefix");
    autoloader_assert(is_dir($roots[$prefix]), "la carpeta de $prefix no existe: {$roots[$prefix]}");
    autoloader_assert(basename($roots[$prefix]) === $folder, "$prefix debe apuntar a $folder/");
}

// Correspondencia espacio de nombres → ruta.
$core = $roots['Kcg\\Core\\'];
autoloader_assert(Autoloader::pathFor('Kcg\\Core\\Autoloader') === $core . '/Autoloader.php', 'ruta de Kcg\\Core\\Autoloader');
autoloader_assert(Autoloader::pathFor('\\Kcg\\Modules\\Microblog\\Repository') === $roots['Kcg\\Modules\\'] . '/Microblog/Repository.php', 'ruta con subcarpeta y «\\» inicial');
autoloader_assert(Autoloader::pathFor('Otro\\Paquete\\Clase') === null, 'un espacio ajeno no debe resolverse');
autoloader_assert(Autoloader::pathFor('Kcg\\CoreExtra\\Clase') === null, 'el prefijo debe coincidir con la barra final (Kcg\\Core ≠ Kcg\\CoreExtra)');

// Carga real desde una raíz temporal.
$temp = sys_get_temp_dir() . '/kcg-autoload-' . bin2hex(random_bytes(6));
mkdir($temp . '/Sub', 0755, true);
file_put_contents($temp . '/Hola.php', "<?php\nnamespace Prueba\\Carga;\nfinal class Hola { public static function di(): string { return 'hola'; } }\n");
file_put_contents($temp . '/Sub/Adios.php', "<?php\nnamespace Prueba\\Carga\\Sub;\nfinal class Adios { public static function di(): string { return 'adios'; } }\n");
Autoloader::addRoot('Prueba\\Carga', $temp);
autoloader_assert(\Prueba\Carga\Hola::di() === 'hola', 'carga de clase en la raíz');
autoloader_assert(\Prueba\Carga\Sub\Adios::di() === 'adios', 'carga de clase en subcarpeta');
autoloader_assert(!class_exists('Prueba\\Carga\\NoExiste'), 'una clase inexistente no debe romper nada');

unlink($temp . '/Sub/Adios.php');
unlink($temp . '/Hola.php');
rmdir($temp . '/Sub');
rmdir($temp);
echo "OK: autocargador (raíces, rutas y carga real).\n";
