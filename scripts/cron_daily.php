<?php
// Daily cron script: reset daily leaderboards and distribute login bonuses.

require_once __DIR__ . '/../app/core/bootstrap.php';

use App\Core\DB;

$pdo = DB::pdo();

$pdo->beginTransaction();
// Clear daily leaderboard entries older than a day
$pdo->exec("DELETE FROM leaderboards WHERE period = 'daily'");
// Grant daily login bonus: +10 coins to all users
$pdo->exec('UPDATE user_profiles SET coins = coins + 10');
$pdo->commit();

echo "Daily cron executed.\n";
