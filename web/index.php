<?php
// Front controller for the Emoji Games platform.
//
// This file routes requests to the appropriate handlers.  It handles a
// health probe (`/healthz`), forwards API calls under `/api/` to the
// corresponding PHP script in `web/api/`, and lets the built‑in PHP
// server or Apache serve static assets and game pages directly.

declare(strict_types=1);

require_once __DIR__ . '/../app/core/bootstrap.php';
use App\Core\Response;

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

// Health check endpoint
if ($requestUri === '/healthz') {
    header('Content-Type: text/plain');
    echo 'OK';
    return;
}

// Route API requests to individual scripts.
if (strpos($requestUri, '/api/') === 0) {
    // Remove query string and `.php` suffix if present.
    $scriptPath = __DIR__ . $requestUri;
    if (substr($scriptPath, -1) === '/') {
        $scriptPath = rtrim($scriptPath, '/') . '/index.php';
    } else if (file_exists($scriptPath . '.php')) {
        $scriptPath .= '.php';
    }
    if (file_exists($scriptPath) && is_file($scriptPath)) {
        require $scriptPath;
    } else {
        Response::jsonError('Endpoint not found', 'NOT_FOUND', 404);
    }
    return;
}

// For non‑API requests we simply let Apache or the PHP built‑in server
// serve static files (HTML, CSS, JS) from the `web/` directory.  If
// a directory index isn't found, fall back to `index.html` inside the
// requested directory if it exists.

// Check if the requested file exists; if not, attempt to serve index.html.
$fullPath = __DIR__ . $requestUri;
if (is_file($fullPath)) {
    return false; // let the server serve the file
}
if (is_dir($fullPath) && is_file($fullPath . '/index.html')) {
    // When using the built‑in PHP server, require() is needed to serve
    // HTML files.  Apache will pick up index.html automatically.
    require $fullPath . '/index.html';
    return;
}

// If nothing matches, show a simple 404 message.
Response::jsonError('Resource not found', 'NOT_FOUND', 404);
