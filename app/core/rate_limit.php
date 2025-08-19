<?php
namespace App\Core;

/**
 * Very naive rate limiter.  Stores counters in the session to limit
 * how many times a given key can be called within a sliding window.
 * In production you should replace this with a shared store such as
 * Redis to ensure limits are enforced across multiple instances.
 */
class RateLimit
{
    /**
     * Check a rate limit for the given key.  Increments the counter
     * and returns true if the call is allowed, false otherwise.
     *
     * @param string $key A unique key representing the action and user/IP
     * @param int $limit Maximum number of calls in the window
     * @param int $window Duration of the window in seconds
     */
    public static function check(string $key, int $limit, int $window): bool
    {
        $now = time();
        if (!isset($_SESSION['rate_limit'])) {
            $_SESSION['rate_limit'] = [];
        }
        if (!isset($_SESSION['rate_limit'][$key])) {
            $_SESSION['rate_limit'][$key] = [];
        }
        // Remove expired timestamps
        $_SESSION['rate_limit'][$key] = array_filter(
            $_SESSION['rate_limit'][$key],
            static function ($timestamp) use ($now, $window) {
                return ($now - $timestamp) < $window;
            }
        );
        if (count($_SESSION['rate_limit'][$key]) >= $limit) {
            return false;
        }
        $_SESSION['rate_limit'][$key][] = $now;
        return true;
    }
}
