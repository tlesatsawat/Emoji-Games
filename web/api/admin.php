<?php
// Admin API: minimal management endpoints.

require_once __DIR__ . '/../../app/core/bootstrap.php';

use App\Core\DB;
use App\Core\Validator;
use App\Core\Response;

// Authenticate admin via Basic auth header.
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? null);
$isAdmin = false;
if ($authHeader && str_starts_with($authHeader, 'Basic ')) {
    $encoded = substr($authHeader, 6);
    [$usern, $passw] = explode(':', base64_decode($encoded), 2);
    if ($usern === ($_ENV['ADMIN_EMAIL'] ?? '') && $passw === ($_ENV['ADMIN_PASS'] ?? '')) {
        $isAdmin = true;
    }
}
if (!$isAdmin) {
    Response::jsonError('Admin authentication required', 'FORBIDDEN', 403);
    return;
}

$pdo = DB::pdo();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $resource = $_GET['resource'] ?? '';
    switch ($resource) {
        case 'purchases':
            $stmt = $pdo->query('SELECT id, order_no, user_id, amount_decimal, currency, status, created_at, approved_at FROM purchases ORDER BY created_at DESC');
            $rows = $stmt->fetchAll();
            Response::json(['purchases' => $rows]);
            return;
        case 'flags':
            $stmt = $pdo->query('SELECT * FROM anti_cheat_flags ORDER BY created_at DESC');
            $rows = $stmt->fetchAll();
            Response::json(['flags' => $rows]);
            return;
        default:
            Response::jsonError('Unknown resource', 'NOT_FOUND', 404);
            return;
    }
}

if ($method === 'POST') {
    $data = Validator::getJsonInput();
    $action = $data['action'] ?? '';
    switch ($action) {
        case 'toggle-game':
            Validator::requireKeys($data, ['slug', 'is_active']);
            $slug = $data['slug'];
            $isActive = (bool)$data['is_active'];
            $stmt = $pdo->prepare('UPDATE games SET is_active = :active WHERE slug = :slug');
            $stmt->execute([':active' => $isActive, ':slug' => $slug]);
            Response::json(['message' => 'Game status updated']);
            return;
        default:
            Response::jsonError('Unknown admin action', 'NOT_FOUND', 404);
            return;
    }
}

Response::jsonError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
