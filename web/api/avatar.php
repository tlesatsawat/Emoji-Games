<?php
// Avatar API: view and update the layered emoji avatar.

require_once __DIR__ . '/../../app/core/bootstrap.php';

use App\Core\Auth;
use App\Core\DB;
use App\Core\Response;
use App\Core\Validator;

$user = Auth::user();
if (!$user) {
    Response::jsonError('Not authenticated', 'UNAUTHENTICATED', 401);
    return;
}

$pdo = DB::pdo();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Retrieve the avatar record; create a default if none exists.
    $stmt = $pdo->prepare('SELECT * FROM user_avatar WHERE user_id = :id');
    $stmt->execute([':id' => $user['id']]);
    $avatar = $stmt->fetch();
    if (!$avatar) {
        $avatar = [
            'user_id'    => $user['id'],
            'base_emoji' => $user['avatar_emoji'],
            'head'       => null,
            'eyes'       => null,
            'mouth'      => null,
            'hand'       => null,
            'background' => null,
        ];
    }
    Response::json(['avatar' => $avatar]);
    return;
}

if ($method === 'PUT') {
    $data = Validator::getJsonInput();
    // Accept only expected fields
    $allowed = ['base_emoji','head','eyes','mouth','hand','background'];
    $updates = [];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $data)) {
            $updates[$field] = $data[$field] ?: null;
        }
    }
    if (!$updates) {
        Response::jsonError('No valid avatar fields provided', 'INVALID_INPUT', 400);
        return;
    }
    // TODO: verify ownership of accessories before assignment.
    // Upsert the avatar record.
    $placeholders = [];
    $params = [':user_id' => $user['id']];
    foreach ($updates as $field => $value) {
        $placeholders[] = "$field = :$field";
        $params[":" . $field] = $value;
    }
    $setClause = implode(', ', $placeholders);
    $sql = "INSERT INTO user_avatar (user_id, base_emoji, head, eyes, mouth, hand, background) VALUES (:user_id, :base_emoji, :head, :eyes, :mouth, :hand, :background) ON CONFLICT (user_id) DO UPDATE SET $setClause";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    Response::json(['message' => 'Avatar updated']);
    return;
}

Response::jsonError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
