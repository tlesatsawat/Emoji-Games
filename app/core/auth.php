<?php
namespace App\Core;

use PDO;

/**
 * Authentication helper.  Provides methods for registering users,
 * logging in/out and retrieving the current authenticated user.
 */
class Auth
{
    /**
     * Register a new user.  Throws an exception or returns an error
     * response if the email or username already exists.
     */
    public static function register(string $email, string $password, string $username): array
    {
        $pdo = DB::pdo();
        // Check uniqueness
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email OR username = :username');
        $stmt->execute([':email' => $email, ':username' => $username]);
        if ($stmt->fetch()) {
            Response::jsonError('Email or username already exists', 'USER_EXISTS', 409);
            exit;
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, username, created_at) VALUES (:email, :hash, :username, NOW()) RETURNING id');
        $stmt->execute([':email' => $email, ':hash' => $hash, ':username' => $username]);
        $userId = (int)$stmt->fetchColumn();
        // Insert profile with defaults
        $stmt = $pdo->prepare('INSERT INTO user_profiles (user_id, display_name, avatar_emoji, coins, gems) VALUES (:id, :display_name, :avatar_emoji, 0, 0)');
        $stmt->execute([':id' => $userId, ':display_name' => $username, ':avatar_emoji' => '😀']);
        $pdo->commit();
        // Log in the user automatically
        $_SESSION['user_id'] = $userId;
        return self::getUserById($userId);
    }

    /**
     * Log in an existing user using email and password.  Returns the
     * user record on success or sends an error response on failure.
     */
    public static function login(string $email, string $password): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !password_verify($password, $row['password_hash'])) {
            Response::jsonError('Invalid email or password', 'INVALID_CREDENTIALS', 401);
            exit;
        }
        $_SESSION['user_id'] = (int)$row['id'];
        return self::getUserById((int)$row['id']);
    }

    /**
     * Log out the current session.  Clears the session cookie.
     */
    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        Response::json(['message' => 'Logged out']);
    }

    /**
     * Return the currently authenticated user record or null if not
     * authenticated.
     */
    public static function user(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        return self::getUserById((int)$_SESSION['user_id']);
    }

    /**
     * Fetch a user record and join the profile information.
     */
    public static function getUserById(int $id): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT u.id, u.email, u.username, u.created_at, p.display_name, p.avatar_emoji, p.coins, p.gems FROM users u JOIN user_profiles p ON u.id = p.user_id WHERE u.id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
