<?php
// Seed script for initial data.

require_once __DIR__ . '/../app/core/bootstrap.php';

use App\Core\DB;

$pdo = DB::pdo();

// Helper to insert user and profile
function createUser(PDO $pdo, string $email, string $password, string $username, bool $isAdmin = false): int {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, username, created_at) VALUES (:email, :hash, :username, NOW()) RETURNING id');
    $stmt->execute([':email' => $email, ':hash' => $hash, ':username' => $username]);
    $userId = (int)$stmt->fetchColumn();
    $stmt = $pdo->prepare('INSERT INTO user_profiles (user_id, display_name, avatar_emoji, coins, gems) VALUES (:uid, :display_name, :avatar, 100, 10)');
    $stmt->execute([':uid' => $userId, ':display_name' => $username, ':avatar' => '😀']);
    return $userId;
}

$pdo->beginTransaction();

// Admin account
$adminEmail = $_ENV['ADMIN_EMAIL'] ?? 'admin@example.com';
$adminPass  = $_ENV['ADMIN_PASS'] ?? 'changeme';
createUser($pdo, $adminEmail, $adminPass, 'admin');

// Sample user
createUser($pdo, 'user@example.com', 'password', 'sample');

// Insert games based on folders in web/games/
$gamesDir = __DIR__ . '/../web/games';
$entries = scandir($gamesDir);
foreach ($entries as $entry) {
    if ($entry === '.' || $entry === '..') continue;
    $slug = $entry;
    // Format title from slug
    $title = ucwords(str_replace(['emoji','-'], ['',' '], $slug));
    $stmt = $pdo->prepare('INSERT INTO games (slug, title, is_active) VALUES (:slug, :title, TRUE) ON CONFLICT (slug) DO NOTHING');
    $stmt->execute([':slug' => $slug, ':title' => trim($title)]);
}

// Insert a few store items
$items = [
    ['sku' => 'boost_coins_10', 'name' => 'Coin Booster +10%', 'description' => 'Gain 10% more coins for 15 minutes.', 'price_coins' => 500, 'price_gems' => 0, 'type' => 'boost'],
    ['sku' => 'theme_dark', 'name' => 'Dark Theme', 'description' => 'Switch to the dark theme.', 'price_coins' => 300, 'price_gems' => 0, 'type' => 'theme'],
    ['sku' => 'skin_gold', 'name' => 'Golden Avatar Skin', 'description' => 'Shine bright like gold.', 'price_coins' => 0, 'price_gems' => 50, 'type' => 'skin'],
];
foreach ($items as $item) {
    $stmt = $pdo->prepare('INSERT INTO items (sku, name, description, price_coins, price_gems, type, is_active) VALUES (:sku, :name, :desc, :pc, :pg, :type, TRUE) ON CONFLICT (sku) DO NOTHING');
    $stmt->execute([
        ':sku'  => $item['sku'],
        ':name' => $item['name'],
        ':desc' => $item['description'],
        ':pc'   => $item['price_coins'],
        ':pg'   => $item['price_gems'],
        ':type' => $item['type'],
    ]);
}

// Insert a handful of accessories per category
$accessories = [
    ['category' => 'head', 'symbol' => '🎩', 'name' => 'Top Hat', 'rarity' => 'common', 'price_coins' => 200, 'price_gems' => 5],
    ['category' => 'eyes', 'symbol' => '😎', 'name' => 'Sunglasses', 'rarity' => 'rare', 'price_coins' => 500, 'price_gems' => 10],
    ['category' => 'mouth', 'symbol' => '😄', 'name' => 'Smiling Mouth', 'rarity' => 'common', 'price_coins' => 100, 'price_gems' => 2],
    ['category' => 'hand', 'symbol' => '⚔️', 'name' => 'Sword', 'rarity' => 'epic', 'price_coins' => 1000, 'price_gems' => 20],
    ['category' => 'background', 'symbol' => '🌅', 'name' => 'Sunset', 'rarity' => 'legendary', 'price_coins' => 0, 'price_gems' => 50],
];
foreach ($accessories as $acc) {
    $stmt = $pdo->prepare('INSERT INTO emoji_accessories (category, emoji_symbol, name, rarity, price_coins, price_gems, is_active) VALUES (:cat, :sym, :name, :rarity, :pc, :pg, TRUE)');
    $stmt->execute([
        ':cat'   => $acc['category'],
        ':sym'   => $acc['symbol'],
        ':name'  => $acc['name'],
        ':rarity'=> $acc['rarity'],
        ':pc'    => $acc['price_coins'],
        ':pg'    => $acc['price_gems'],
    ]);
}

$pdo->commit();

echo "Seeding completed.\n";
