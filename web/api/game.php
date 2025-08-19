<?php
// Game API: start and submit runs for any game.

require_once __DIR__ . '/../../app/core/bootstrap.php';

use App\Core\Auth;
use App\Core\DB;
use App\Core\Validator;
use App\Core\Response;
use App\Core\Security;

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($uri, '/'));
// segments[0]=api, [1]=game, [2]=action
$action = $segments[2] ?? '';

$pdo = DB::pdo();
$user = Auth::user();

switch ($action) {
    case 'start':
        if ($method !== 'POST') {
            Response::jsonError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
            break;
        }
        $data = Validator::getJsonInput();
        Validator::requireKeys($data, ['game']);
        $slug = $data['game'];
        // Check that game exists and is active
        $stmt = $pdo->prepare('SELECT id, is_active FROM games WHERE slug = :slug');
        $stmt->execute([':slug' => $slug]);
        $game = $stmt->fetch();
        if (!$game || !$game['is_active']) {
            Response::jsonError('Game not found or inactive', 'NOT_FOUND', 404);
            break;
        }
        // Create a nonce and server seed.  Expire in 5 minutes.
        $nonce = Security::generateNonce();
        $serverSeed = bin2hex(random_bytes(8));
        $expiresAt = time() + 300;
        Response::json([
            'nonce'      => $nonce,
            'server_seed'=> $serverSeed,
            'expires_at' => $expiresAt,
        ]);
        break;

    case 'submit':
        if ($method !== 'POST') {
            Response::jsonError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
            break;
        }
        if (!$user) {
            Response::jsonError('Not authenticated', 'UNAUTHENTICATED', 401);
            break;
        }
        $data = Validator::getJsonInput();
        Validator::requireKeys($data, ['game','score','duration_ms','nonce']);
        $slug  = $data['game'];
        $score = (int)$data['score'];
        $duration = (int)$data['duration_ms'];
        $nonce = (string)$data['nonce'];
        $clientSig = isset($data['client_sig']) ? (string)$data['client_sig'] : '';
        // Validate game
        $stmt = $pdo->prepare('SELECT id, is_active FROM games WHERE slug = :slug');
        $stmt->execute([':slug' => $slug]);
        $game = $stmt->fetch();
        if (!$game || !$game['is_active']) {
            Response::jsonError('Game not found or inactive', 'NOT_FOUND', 404);
            break;
        }
        $gameId = (int)$game['id'];
        // Simple sanity check: scores should be non‑negative and not too large
        if ($score < 0 || $score > 1000000) {
            Response::jsonError('Invalid score', 'INVALID_SCORE', 400);
            break;
        }
        if ($duration < 0 || $duration > 3600000) { // one hour
            Response::jsonError('Invalid duration', 'INVALID_DURATION', 400);
            break;
        }
        // Verify signature if provided
        if ($clientSig && !Security::verifySignature($nonce, $score, $duration, $clientSig)) {
            // Flag potential cheating attempt
            $stmt = $pdo->prepare('INSERT INTO anti_cheat_flags (user_id, game_id, reason, detail, created_at) VALUES (:uid, :gid, :reason, :detail, NOW())');
            $stmt->execute([
                ':uid'    => $user['id'],
                ':gid'    => $gameId,
                ':reason' => 'INVALID_SIGNATURE',
                ':detail' => $nonce,
            ]);
            Response::jsonError('Invalid signature', 'INVALID_SIGNATURE', 400);
            break;
        }
        // Ensure nonce has not been used before
        $stmt = $pdo->prepare('SELECT 1 FROM game_runs WHERE nonce = :nonce');
        $stmt->execute([':nonce' => $nonce]);
        if ($stmt->fetch()) {
            Response::jsonError('Duplicate nonce', 'DUPLICATE_NONCE', 400);
            break;
        }
        // Insert run
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO game_runs (user_id, game_id, score, duration_ms, max_combo, accuracy, created_at, nonce, server_sig) VALUES (:uid, :gid, :score, :duration, :max_combo, :accuracy, NOW(), :nonce, :sig)');
        $stmt->execute([
            ':uid'      => $user['id'],
            ':gid'      => $gameId,
            ':score'    => $score,
            ':duration' => $duration,
            ':max_combo'=> ($data['stats']['max_combo'] ?? 0),
            ':accuracy' => ($data['stats']['accuracy'] ?? 0),
            ':nonce'    => $nonce,
            ':sig'      => Security::signRun($nonce, $score, $duration),
        ]);
        // Update leaderboards; compute period keys
        $periods = ['alltime', 'daily', 'weekly'];
        foreach ($periods as $period) {
            // Determine if the run falls in the current daily/weekly period
            if ($period === 'daily') {
                $isValid = true;
                // Only include runs from the last 24 hours; left as simplified
            } elseif ($period === 'weekly') {
                $isValid = true;
            } else {
                $isValid = true;
            }
            if (!$isValid) {
                continue;
            }
            $stmt = $pdo->prepare('INSERT INTO leaderboards (game_id, user_id, period, score, updated_at) VALUES (:gid, :uid, :period, :score, NOW()) ON CONFLICT (game_id, user_id, period) DO UPDATE SET score = GREATEST(leaderboards.score, EXCLUDED.score), updated_at = NOW()');
            $stmt->execute([
                ':gid'    => $gameId,
                ':uid'    => $user['id'],
                ':period' => $period,
                ':score'  => $score,
            ]);
        }
        // Award coins proportional to score (simple formula)
        $coinsEarned = max(1, (int)floor($score / 10));
        $stmt = $pdo->prepare('UPDATE user_profiles SET coins = coins + :coins WHERE user_id = :uid');
        $stmt->execute([':coins' => $coinsEarned, ':uid' => $user['id']]);
        $pdo->commit();
        Response::json(['message' => 'Run submitted', 'coins_earned' => $coinsEarned]);
        break;

    default:
        Response::jsonError('Unknown game action', 'NOT_FOUND', 404);
        break;
}
