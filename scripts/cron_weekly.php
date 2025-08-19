<?php
// Weekly cron script: reset weekly leaderboards and distribute weekly rewards.

require_once __DIR__ . '/../app/core/bootstrap.php';

use App\Core\DB;

$pdo = DB::pdo();

$pdo->beginTransaction();
// Clear weekly leaderboard entries
$pdo->exec("DELETE FROM leaderboards WHERE period = 'weekly'");
// Grant weekly bonus: +50 coins and +5 gems to all users
$pdo->exec('UPDATE user_profiles SET coins = coins + 50, gems = gems + 5');
$pdo->commit();

echo "Weekly cron executed.\n";
