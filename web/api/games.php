<?php
// Games API: list all active games.  Each game has a slug and title.

require_once __DIR__ . '/../../app/core/bootstrap.php';

use App\Core\DB;
use App\Core\Response;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::jsonError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
    return;
}
$pdo = DB::pdo();
$stmt = $pdo->query('SELECT slug, title FROM games WHERE is_active = TRUE ORDER BY id');
$games = $stmt->fetchAll();
Response::json(['games' => $games]);
