<?php
declare(strict_types=1);

// Comprueba que docs/catalogos.md refleja lo que hay en themes/ (se genera con scripts/build-catalogs.php).
// Uso: php tests/catalog-docs.php

$root = dirname(__DIR__);
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/scripts/build-catalogs.php') . ' --check 2>&1';
exec($command, $output, $status);
$output = implode("\n", array_filter($output, static fn(string $line): bool => !str_contains($line, 'openssl') && trim($line) !== ''));
if ($status !== 0) {
    fwrite(STDERR, $output . "\n");
    exit(1);
}
echo $output . "\n";
