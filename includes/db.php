<?php
/**
 * Purble Pairs — Shared Database Connection
 * Require this file wherever a DB connection is needed.
 * Also loads .env variables and exposes $encryptionKey.
 */

// Load .env into $_ENV (safe to call multiple times — skips if already loaded)
if (empty($_ENV['_ENV_LOADED'])) {
    $envFile = dirname(__DIR__) . '/.env';
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
            [$envKey, $envVal] = explode('=', $line, 2);
            $_ENV[trim($envKey)] = trim($envVal);
        }
    }
    $_ENV['_ENV_LOADED'] = true;
}

$encryptionKey = $_ENV['ENCRYPTION_KEY'] ?? 'PURBLEAC';

$conn = new mysqli(
    $_ENV['DB_HOST'] ?? 'localhost',
    $_ENV['DB_USER'] ?? 'root',
    $_ENV['DB_PASS'] ?? '',
    $_ENV['DB_NAME'] ?? 'purble_pairs'
);

if ($conn->connect_error) {
    die(
        '<div style="font-family:sans-serif;padding:2rem;background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;max-width:500px;margin:4rem auto;">'
        . '<h2 style="color:#dc2626;margin:0 0 .5rem">Database Connection Failed</h2>'
        . '<p style="color:#7f1d1d;margin:0 0 1rem">' . htmlspecialchars($conn->connect_error) . '</p>'
        . '<ol style="color:#7f1d1d;margin:0;padding-left:1.2rem;line-height:1.8">'
        . '<li>Open <strong>phpMyAdmin</strong> at <a href="http://localhost/phpmyadmin">localhost/phpmyadmin</a></li>'
        . '<li>Click <strong>SQL</strong> and paste the contents of <code>setup.sql</code></li>'
        . '<li>Click <strong>Go</strong> to create the database and tables</li>'
        . '</ol>'
        . '</div>'
    );
}
