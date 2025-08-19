<?php
// Database migration script.
//
// Run this script with `php scripts/migrate.php` to create all
// necessary tables and indices in your PostgreSQL database.  It
// connects using the DB_URL environment variable.

require_once __DIR__ . '/../app/core/bootstrap.php';

use App\Core\DB;

$pdo = DB::pdo();

// Drop existing tables in reverse dependency order (optional).  For
// development convenience this script attempts to drop tables if they
// exist before creating them.  Comment out the drops in production.
$tables = [
    'anti_cheat_flags',
    'leaderboards',
    'game_runs',
    'user_avatar',
    'user_accessories',
    'emoji_accessories',
    'transactions',
    'inventories',
    'items',
    'purchases',
    'user_profiles',
    'games',
    'users',
];
foreach ($tables as $tbl) {
    $pdo->exec("DROP TABLE IF EXISTS $tbl CASCADE");
}

// Users table
$pdo->exec(
    'CREATE TABLE users (
        id SERIAL PRIMARY KEY,
        email TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        username TEXT UNIQUE NOT NULL,
        created_at TIMESTAMP NOT NULL
    )'
);

// User profiles
$pdo->exec(
    'CREATE TABLE user_profiles (
        user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
        display_name TEXT NOT NULL,
        avatar_emoji TEXT NOT NULL,
        coins INTEGER NOT NULL DEFAULT 0,
        gems INTEGER NOT NULL DEFAULT 0
    )'
);

// Games
$pdo->exec(
    'CREATE TABLE games (
        id SERIAL PRIMARY KEY,
        slug TEXT UNIQUE NOT NULL,
        title TEXT NOT NULL,
        is_active BOOLEAN NOT NULL DEFAULT TRUE
    )'
);

// Game runs
$pdo->exec(
    'CREATE TABLE game_runs (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        game_id INTEGER NOT NULL REFERENCES games(id) ON DELETE CASCADE,
        score INTEGER NOT NULL,
        duration_ms INTEGER NOT NULL,
        max_combo INTEGER NOT NULL DEFAULT 0,
        accuracy NUMERIC(5,2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL,
        nonce TEXT UNIQUE NOT NULL,
        server_sig TEXT NOT NULL
    )'
);

// Leaderboards
$pdo->exec(
    'CREATE TABLE leaderboards (
        id SERIAL PRIMARY KEY,
        game_id INTEGER NOT NULL REFERENCES games(id) ON DELETE CASCADE,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        period TEXT NOT NULL,
        score INTEGER NOT NULL,
        updated_at TIMESTAMP NOT NULL,
        UNIQUE (game_id, user_id, period)
    )'
);

// Items
$pdo->exec(
    'CREATE TABLE items (
        id SERIAL PRIMARY KEY,
        sku TEXT UNIQUE NOT NULL,
        name TEXT NOT NULL,
        description TEXT,
        price_coins INTEGER NOT NULL DEFAULT 0,
        price_gems INTEGER NOT NULL DEFAULT 0,
        type TEXT NOT NULL,
        is_active BOOLEAN NOT NULL DEFAULT TRUE
    )'
);

// Inventories
$pdo->exec(
    'CREATE TABLE inventories (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        item_id INTEGER NOT NULL REFERENCES items(id) ON DELETE CASCADE,
        acquired_at TIMESTAMP NOT NULL,
        UNIQUE (user_id, item_id)
    )'
);

// Transactions
$pdo->exec(
    'CREATE TABLE transactions (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        type TEXT NOT NULL,
        currency TEXT NOT NULL,
        amount INTEGER NOT NULL,
        ref TEXT,
        created_at TIMESTAMP NOT NULL
    )'
);

// Purchases (manual payments)
$pdo->exec(
    'CREATE TABLE purchases (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        order_no TEXT UNIQUE NOT NULL,
        method TEXT NOT NULL,
        amount_decimal NUMERIC NOT NULL,
        currency TEXT NOT NULL,
        status TEXT NOT NULL,
        slip_url TEXT,
        created_at TIMESTAMP NOT NULL,
        approved_at TIMESTAMP
    )'
);

// Emoji accessories
$pdo->exec(
    'CREATE TABLE emoji_accessories (
        id SERIAL PRIMARY KEY,
        category TEXT NOT NULL,
        emoji_symbol TEXT NOT NULL,
        name TEXT NOT NULL,
        rarity TEXT NOT NULL,
        price_coins INTEGER NOT NULL DEFAULT 0,
        price_gems INTEGER NOT NULL DEFAULT 0,
        is_active BOOLEAN NOT NULL DEFAULT TRUE
    )'
);

// User accessories
$pdo->exec(
    'CREATE TABLE user_accessories (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        accessory_id INTEGER NOT NULL REFERENCES emoji_accessories(id) ON DELETE CASCADE,
        acquired_at TIMESTAMP NOT NULL,
        UNIQUE (user_id, accessory_id)
    )'
);

// User avatar
$pdo->exec(
    'CREATE TABLE user_avatar (
        user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
        base_emoji TEXT NOT NULL,
        head INTEGER REFERENCES emoji_accessories(id),
        eyes INTEGER REFERENCES emoji_accessories(id),
        mouth INTEGER REFERENCES emoji_accessories(id),
        hand INTEGER REFERENCES emoji_accessories(id),
        background INTEGER REFERENCES emoji_accessories(id)
    )'
);

// Anti cheat flags
$pdo->exec(
    'CREATE TABLE anti_cheat_flags (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        game_id INTEGER NOT NULL REFERENCES games(id) ON DELETE CASCADE,
        reason TEXT NOT NULL,
        detail TEXT,
        created_at TIMESTAMP NOT NULL
    )'
);

echo "Migration completed.\n";
