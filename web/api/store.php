<?php
// Store API: list items and purchase them using coins or gems.

require_once __DIR__ . '/../../app/core/bootstrap.php';

use App\Core\Auth;
use App\Core\DB;
use App\Core\Validator;
use App\Core\Response;

$method = $_SERVER['REQUEST_METHOD'];
$pdo = DB::pdo();

if ($method === 'GET') {
    // List all active store items.
    $stmt = $pdo->query('SELECT id, sku, name, description, price_coins, price_gems, type FROM items WHERE is_active = TRUE ORDER BY id');
    $items = $stmt->fetchAll();
    Response::json(['items' => $items]);
    return;
}

if ($method === 'POST') {
    $user = Auth::user();
    if (!$user) {
        Response::jsonError('Not authenticated', 'UNAUTHENTICATED', 401);
        return;
    }
    $data = Validator::getJsonInput();
    Validator::requireKeys($data, ['sku', 'currency']);
    $sku = $data['sku'];
    $currency = $data['currency'];
    if (!in_array($currency, ['coins','gems'], true)) {
        Response::jsonError('Invalid currency', 'INVALID_CURRENCY', 400);
        return;
    }
    // Look up item
    $stmt = $pdo->prepare('SELECT id, price_coins, price_gems FROM items WHERE sku = :sku AND is_active = TRUE');
    $stmt->execute([':sku' => $sku]);
    $item = $stmt->fetch();
    if (!$item) {
        Response::jsonError('Item not found', 'NOT_FOUND', 404);
        return;
    }
    $price = $currency === 'coins' ? (int)$item['price_coins'] : (int)$item['price_gems'];
    if ($price < 0) {
        Response::jsonError('Item cannot be purchased with the selected currency', 'INVALID_PURCHASE', 400);
        return;
    }
    // Check balance
    $field = $currency;
    if ($user[$field] < $price) {
        Response::jsonError('Insufficient funds', 'INSUFFICIENT_FUNDS', 402);
        return;
    }
    // Begin transaction
    $pdo->beginTransaction();
    // Deduct balance
    $stmt = $pdo->prepare("UPDATE user_profiles SET $field = $field - :price WHERE user_id = :uid");
    $stmt->execute([':price' => $price, ':uid' => $user['id']]);
    // Add inventory if not exists
    $stmt = $pdo->prepare('INSERT INTO inventories (user_id, item_id, acquired_at) VALUES (:uid, :item_id, NOW()) ON CONFLICT (user_id, item_id) DO NOTHING');
    $stmt->execute([':uid' => $user['id'], ':item_id' => $item['id']]);
    // Log transaction
    $stmt = $pdo->prepare('INSERT INTO transactions (user_id, type, currency, amount, ref, created_at) VALUES (:uid, :type, :currency, :amount, :ref, NOW())');
    $stmt->execute([
        ':uid'     => $user['id'],
        ':type'    => 'spend',
        ':currency'=> $currency,
        ':amount'  => $price,
        ':ref'     => 'store:' . $sku,
    ]);
    $pdo->commit();
    Response::json(['message' => 'Purchase successful']);
    return;
}

Response::jsonError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
