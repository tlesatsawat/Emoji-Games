<?php
namespace App\Core;

/**
 * Helper for consistent JSON responses.  All API scripts should use
 * these methods to produce responses.  Errors are returned with
 * `ok: false`, while successful responses use `ok: true`.
 */
class Response
{
    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'ok'   => true,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function jsonError(string $message, string $code = 'ERROR', int $statusCode = 400): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'ok'    => false,
            'error' => $message,
            'code'  => $code,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
