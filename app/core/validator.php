<?php
namespace App\Core;

/**
 * Simple input validator.  Provides helpers to parse JSON bodies and
 * assert required keys are present.  Extend this class as needed to
 * perform more complex validation and sanitisation.
 */
class Validator
{
    /**
     * Decode the request body as JSON.  Returns an associative array
     * or triggers a 400 error via Response::jsonError on failure.
     */
    public static function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            Response::jsonError('Invalid JSON input', 'INVALID_JSON', 400);
            exit;
        }
        return $data;
    }

    /**
     * Ensure the required keys exist in the data array.  If a key is
     * missing or empty, an error response is returned.
     */
    public static function requireKeys(array $data, array $keys): void
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data) || $data[$key] === '' || $data[$key] === null) {
                Response::jsonError("Missing or empty parameter: $key", 'MISSING_PARAM', 400);
                exit;
            }
        }
    }
}
