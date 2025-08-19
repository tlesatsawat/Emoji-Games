<?php
namespace App\Core;

/**
 * Security utilities for HMAC signing and nonce generation.
 *
 * All games must include a nonce provided by the server in the
 * `startRun` response.  Clients submit the nonce alongside their
 * score and duration.  The server recalculates the HMAC signature
 * to ensure that the run has not been tampered with.
 */
class Security
{
    /**
     * Generate a cryptographically secure random nonce.
     *
     * @param int $bytes Number of random bytes to use
     */
    public static function generateNonce(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /**
     * Compute the server signature (HMAC) for a game run.  The
     * message is composed of the nonce, score and duration separated
     * by vertical bars.  The secret key comes from SERVER_SECRET.
     */
    public static function signRun(string $nonce, int $score, int $durationMs): string
    {
        $secret = $_ENV['SERVER_SECRET'] ?? '';
        $message = $nonce . '|' . $score . '|' . $durationMs;
        return hash_hmac('sha256', $message, $secret);
    }

    /**
     * Verify a client signature by re‑calculating the HMAC and
     * comparing it using a constant‑time comparison.
     */
    public static function verifySignature(string $nonce, int $score, int $durationMs, string $sig): bool
    {
        $expected = self::signRun($nonce, $score, $durationMs);
        return hash_equals($expected, $sig);
    }
}
