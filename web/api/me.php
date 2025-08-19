<?php
// Current user profile endpoint.

require_once __DIR__ . '/../../app/core/bootstrap.php';

use App\Core\Auth;
use App\Core\Response;

$user = Auth::user();
if (!$user) {
    Response::jsonError('Not authenticated', 'UNAUTHENTICATED', 401);
    return;
}
Response::json(['user' => $user]);
