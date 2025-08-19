<?php
// Leaderboard API: fetch a leaderboard for a given game and period.

require_once __DIR__ . '/../../app/core/bootstrap.php';

use App\Core\DB;
use App\Core\Response;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::jsonError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
    return;
}

$slug   = $_GET['game'] ?? null;
$period = $_GET['period'] ?? 'alltime';
$limit  = min(max((int)($_GET['limit'] ?? 50), 1), 100);
$offset = max((int)($_GET['offset'] ?? 0), 0);

if (!$slug) {
    Response::jsonError('Missing game parameter', 'MISSING_PARAM', 400);
    return;
}
// Validate period
if (!in_array($period, ['daily', 'weekly', 'alltime'], true)) {
    Response::jsonError('Invalid period', 'INVALID_PARAM', 400);
    return;
}

$pdo = DB::pdo();
// Fetch game id
$stmt = $pdo->prepare('SELECT id FROM games WHERE slug = :slug');
$stmt->execute([':slug' => $slug]);
$gameId = $stmt->fetchColumn();
if (!$gameId) {
    Response::jsonError('Game not found', 'NOT_FOUND', 404);
    return;
}
// Retrieve leaderboard entries
$stmt = $pdo->prepare('SELECT l.user_id, l.score, l.updated_at, p.display_name, p.avatar_emoji FROM leaderboards l JOIN user_profiles p ON l.user_id = p.user_id WHERE l.game_id = :gid AND l.period = :period ORDER BY l.score DESC, l.updated_at ASC LIMIT :limit OFFSET :offset');
$stmt->bindValue(':gid', (int)$gameId, PDO::PARAM_INT);
$stmt->bindValue(':period', $period);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$entries = $stmt->fetchAll();
Response::json(['entries' => $entries]);
