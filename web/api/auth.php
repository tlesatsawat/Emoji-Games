<?php
// Authentication endpoints.
//
// Handles user registration, login and logout.  The path determines
// the action (`/api/auth/register`, `/api/auth/login`, `/api/auth/logout`).

require_once __DIR__ . '/../../app/core/bootstrap.php';

use App\Core\Validator;
use App\Core\Auth;
use App\Core\Response;

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($uri, '/'));
// segments[0] = 'api', [1] = 'auth', [2] = action
$action = $segments[2] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'register':
        if ($method !== 'POST') {
            Response::jsonError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
            break;
        }
        $data = Validator::getJsonInput();
        Validator::requireKeys($data, ['email', 'password', 'username']);
        $user = Auth::register(trim($data['email']), (string)$data['password'], trim($data['username']));
        Response::json(['user' => $user]);
        break;

    case 'login':
        if ($method !== 'POST') {
            Response::jsonError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
            break;
        }
        $data = Validator::getJsonInput();
        Validator::requireKeys($data, ['email', 'password']);
        $user = Auth::login(trim($data['email']), (string)$data['password']);
        Response::json(['user' => $user]);
        break;

    case 'logout':
        if ($method !== 'POST') {
            Response::jsonError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
            break;
        }
        Auth::logout();
        break;

    default:
        Response::jsonError('Unknown auth endpoint', 'NOT_FOUND', 404);
        break;
}
