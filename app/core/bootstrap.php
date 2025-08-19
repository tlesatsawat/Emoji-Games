<?php
/**
 * Global bootstrap script.
 *
 * This file is included at the top of the front controller and all API
 * scripts.  It loads Composer's autoloader, populates the $_ENV
 * array from a `.env` file if present, starts a secure session and
 * initialises the database connection.
 */

declare(strict_types=1);

namespace App\Core;

// Load Composer autoloader for PSR‑4 classes.
require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Load environment variables from .env into $_ENV.  Values in
 * existing environment variables are not overridden.  A very small
 * parser is used here to avoid pulling in external dependencies.
 */
(function () {
    $envFile = dirname(__DIR__, 2) . '/.env';
    if (!file_exists($envFile)) {
        return;
    }
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments and malformed lines
        if (str_starts_with(trim($line), '#') || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key !== '' && !array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
    }
})();

// Start session with secure defaults.  HTTPS detection based on
// server variables; secure cookies are disabled when running via the
// CLI built‑in server.
if (php_sapi_name() !== 'cli') {
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('emoji_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (!session_id()) {
        session_start();
    }
}

// Initialise the database connection.
DB::init();
