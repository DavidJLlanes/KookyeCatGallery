<?php
declare(strict_types=1);

/**
 * Arranque común: registra la carga automática de clases.
 * Lo carga inc/site-settings.php, que ya requieren todos los puntos de entrada.
 */

if (!class_exists(\Kcg\Core\Autoloader::class, false)) {
    require_once __DIR__ . '/Autoloader.php';
}

$kcgRoot = dirname(__DIR__);
\Kcg\Core\Autoloader::addRoot('Kcg\\Core', $kcgRoot . '/core');
\Kcg\Core\Autoloader::addRoot('Kcg\\Modules', $kcgRoot . '/modules');
\Kcg\Core\Autoloader::addRoot('Kcg\\Themes', $kcgRoot . '/themes');
unset($kcgRoot);
