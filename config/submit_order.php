<?php
header('Content-Type: application/json');
require_once 'config.php';

// Ambil data dari POST request
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit;
}

// Data dari request
$order_id = $data['order_id'] ?? '';
$invoice_no = $data['invoice_no'] ?? '';
$customer_name = $data['customer_name'] ?? '';
$table_number = $data['table_number'] ?? '';
$notes = $data['notes'] ?? '';
$items = json_encode($data['items'] ?? []);
$total_price = $data['total_price'] ?? 0;
$payment_method = $data['payment_method'] ?? 'cash';
$status = $data['status'] ?? 'pending';

// Insert ke database
$sql = "INSERT INTO orders (order_id, invoice_no, customer_name, table_number, notes, items, total_price, payment_method, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssssssdss", 
    $order_id, 
    $invoice_no, 
    $customer_name, 
    $table_number, 
    $notes, 
    $items, 
    $total_price, 
    $payment_method, 
    $status
);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true, 'message' => 'Order saved successfully', 'order_id' => $order_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save order: ' . mysqli_error($conn)]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>