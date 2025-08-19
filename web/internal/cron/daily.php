<?php
// Internal daily cron endpoint.  Triggered by GitHub Actions.
require_once __DIR__ . '/../../../app/core/bootstrap.php';

use App\Core\Response;

// Authenticate using Bearer token
$headers = getallheaders();
$auth = $headers['Authorization'] ?? ($headers['authorization'] ?? '');
if (!str_starts_with($auth, 'Bearer ')) {
    Response::jsonError('Unauthorized', 'UNAUTHORIZED', 401);
    return;
}
$token = substr($auth, 7);
if ($token !== ($_ENV['CRON_TOKEN'] ?? '')) {
    Response::jsonError('Forbidden', 'FORBIDDEN', 403);
    return;
}
// Execute the daily cron script
require_once __DIR__ . '/../../../scripts/cron_daily.php';
Response::json(['message' => 'Daily cron completed']);
