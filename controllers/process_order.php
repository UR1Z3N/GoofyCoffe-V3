<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_login(true);
require_valid_csrf(true);

require_once '../config/database.php';
require_once '../models/Order.php';

// Get POST data
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

if (!$input) {
    echo json_encode(["success" => false, "message" => "Invalid input"]);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$orderModel = new Order($db);

$total_amount = isset($input['total_amount']) ? (float) $input['total_amount'] : 0;
$payment_method_id = isset($input['payment_method_id']) ? (int) $input['payment_method_id'] : 0;
$items = $input['items'] ?? [];

if ($total_amount <= 0 || $payment_method_id <= 0) {
    echo json_encode(["success" => false, "message" => "Total atau metode pembayaran tidak valid"]);
    exit;
}

if (empty($items)) {
    echo json_encode(["success" => false, "message" => "Cart is empty"]);
    exit;
}


$result = $orderModel->createOrder($total_amount, $payment_method_id, $items);

echo json_encode($result);
?>
