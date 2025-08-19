<?php
// Manual payments (PromptPay) API.

require_once __DIR__ . '/../../app/core/bootstrap.php';

use App\Core\Auth;
use App\Core\DB;
use App\Core\Validator;
use App\Core\Response;

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($uri, '/'));
// segments[0]=api, [1]=payments, [2]=action
$action = $segments[2] ?? '';

$pdo = DB::pdo();
$user = Auth::user();

switch ($action) {
    case 'create':
        if ($method !== 'POST') {
            Response::jsonError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
            break;
        }
        if (!$user) {
            Response::jsonError('Not authenticated', 'UNAUTHENTICATED', 401);
            break;
        }
        $data = Validator::getJsonInput();
        Validator::requireKeys($data, ['plan']);
        // For demonstration, we define a simple mapping of plans to price and gems.
        $plans = [
            'small'  => ['amount' => 50,  'gems' => 50],
            'medium' => ['amount' => 100, 'gems' => 110],
            'large'  => ['amount' => 200, 'gems' => 230],
        ];
        $plan = $data['plan'];
        if (!isset($plans[$plan])) {
            Response::jsonError('Invalid plan', 'INVALID_PLAN', 400);
            break;
        }
        $orderNo = 'ORD' . time() . rand(1000, 9999);
        // Insert purchase record
        $stmt = $pdo->prepare('INSERT INTO purchases (user_id, order_no, method, amount_decimal, currency, status, created_at) VALUES (:uid, :order_no, :method, :amount, :currency, :status, NOW())');
        $stmt->execute([
            ':uid'      => $user['id'],
            ':order_no' => $orderNo,
            ':method'   => 'promptpay',
            ':amount'   => $plans[$plan]['amount'],
            ':currency' => 'THB',
            ':status'   => 'pending',
        ]);
        // Return instructions (in a real implementation you'd generate a QR code)
        $instructions = 'Please scan the QR code for PromptPay and transfer ' . $plans[$plan]['amount'] . ' THB. Use your order number as reference.';
        Response::json([
            'order_no'           => $orderNo,
            'payment_instructions' => $instructions,
        ]);
        break;

    case 'upload-slip':
        if ($method !== 'POST') {
            Response::jsonError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
            break;
        }
        if (!$user) {
            Response::jsonError('Not authenticated', 'UNAUTHENTICATED', 401);
            break;
        }
        $data = Validator::getJsonInput();
        Validator::requireKeys($data, ['order_no', 'slip_url']);
        $stmt = $pdo->prepare('UPDATE purchases SET slip_url = :slip, status = CASE WHEN status = \'pending\' THEN \'pending\' ELSE status END WHERE order_no = :order_no AND user_id = :uid');
        $stmt->execute([
            ':slip'     => $data['slip_url'],
            ':order_no' => $data['order_no'],
            ':uid'      => $user['id'],
        ]);
        Response::json(['message' => 'Slip uploaded']);
        break;

    case 'review':
        // Only admin can call this endpoint.  Authenticate via basic admin auth.
        if ($method !== 'POST') {
            Response::jsonError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
            break;
        }
        // Check admin credentials from basic auth headers
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? null);
        $isAdmin = false;
        if ($authHeader && str_starts_with($authHeader, 'Basic ')) {
            $encoded = substr($authHeader, 6);
            [$usern, $passw] = explode(':', base64_decode($encoded), 2);
            if ($usern === ($_ENV['ADMIN_EMAIL'] ?? '') && $passw === ($_ENV['ADMIN_PASS'] ?? '')) {
                $isAdmin = true;
            }
        }
        if (!$isAdmin) {
            Response::jsonError('Admin authentication required', 'FORBIDDEN', 403);
            break;
        }
        $data = Validator::getJsonInput();
        Validator::requireKeys($data, ['order_no', 'status']);
        $status = $data['status'];
        if (!in_array($status, ['approved','rejected'], true)) {
            Response::jsonError('Invalid status', 'INVALID_STATUS', 400);
            break;
        }
        // Load purchase
        $stmt = $pdo->prepare('SELECT id, user_id, amount_decimal, status FROM purchases WHERE order_no = :order_no');
        $stmt->execute([':order_no' => $data['order_no']]);
        $purchase = $stmt->fetch();
        if (!$purchase) {
            Response::jsonError('Order not found', 'NOT_FOUND', 404);
            break;
        }
        if ($purchase['status'] !== 'pending') {
            Response::jsonError('Purchase already processed', 'ALREADY_PROCESSED', 409);
            break;
        }
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE purchases SET status = :status, approved_at = NOW() WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $purchase['id']]);
        if ($status === 'approved') {
            // Credit gems based on a conversion rate (1 THB = 1 gem for simplicity)
            $gems = (int)$purchase['amount_decimal'];
            $stmt = $pdo->prepare('UPDATE user_profiles SET gems = gems + :gems WHERE user_id = :uid');
            $stmt->execute([':gems' => $gems, ':uid' => $purchase['user_id']]);
            // Log transaction
            $stmt = $pdo->prepare('INSERT INTO transactions (user_id, type, currency, amount, ref, created_at) VALUES (:uid, :type, :currency, :amount, :ref, NOW())');
            $stmt->execute([
                ':uid'      => $purchase['user_id'],
                ':type'     => 'purchase',
                ':currency' => 'gems',
                ':amount'   => $gems,
                ':ref'      => 'payment:' . $data['order_no'],
            ]);
        }
        $pdo->commit();
        Response::json(['message' => 'Purchase ' . $status]);
        break;

    default:
        Response::jsonError('Unknown payments endpoint', 'NOT_FOUND', 404);
        break;
}
