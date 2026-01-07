<?php
// submit_order.php
require_once 'config/database.php';

// Enable error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Log request
$rawInput = file_get_contents('php://input');
error_log("[" . date('Y-m-d H:i:s') . "] Request received: " . $rawInput . "\n", 3, "php_log.txt");

$data = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $response = [
        'success' => false,
        'message' => 'Invalid JSON data: ' . json_last_error_msg(),
        'saved_to_db' => false
    ];
    echo json_encode($response);
    exit;
}

if (!$data) {
    $response = [
        'success' => false,
        'message' => 'No data received',
        'saved_to_db' => false
    ];
    echo json_encode($response);
    exit;
}

try {
    // BEGIN TRANSACTION
    $pdo->beginTransaction();
    
    // 1. GENERATE INVOICE NO
    $invoiceNo = 'INV-' . date('YmdHis') . '-' . rand(100, 999);
    
    // 2. INSERT KE TABLE ORDERS
    $stmt = $pdo->prepare("
        INSERT INTO orders 
        (user_id, customer_name, table_number, invoice_no, total_price, 
         payment_method, status, transaction_date, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    // Data untuk execute
    $orderData = [
        1, // user_id (default)
        $data['customer_name'] ?? 'Guest',
        $data['table_number'] ?? 1,
        $invoiceNo,
        $data['total'] ?? 0,
        $data['payment_method'] ?? 'cash',
        'pending',
        date('Y-m-d')
    ];
    
    $stmt->execute($orderData);
    $orderId = $pdo->lastInsertId();
    
    // 3. INSERT KE TABLE ORDER_ITEMS
    $itemsSaved = 0;
    if (!empty($data['items']) && is_array($data['items'])) {
        $itemStmt = $pdo->prepare("
            INSERT INTO order_items 
            (order_id, product_id, qty, price) 
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($data['items'] as $item) {
            $itemData = [
                $orderId,
                $item['product_id'] ?? 0,
                $item['quantity'] ?? 1,
                $item['price'] ?? 0
            ];
            $itemStmt->execute($itemData);
            $itemsSaved++;
        }
    }
    
    // 4. COMMIT TRANSACTION
    $pdo->commit();
    
    // 5. SIMPAN BACKUP FILE
    $backupData = [
        'order_id' => $orderId,
        'invoice_no' => $invoiceNo,
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => $data,
        'items_count' => $itemsSaved
    ];
    
    $backupDir = 'orders_backup/';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0777, true);
    }
    
    $backupFile = $backupDir . 'order_' . $orderId . '.json';
    file_put_contents($backupFile, json_encode($backupData, JSON_PRETTY_PRINT));
    
    // 6. RESPONSE SUCCESS
    $response = [
        'success' => true,
        'message' => 'Pesanan berhasil disimpan!',
        'order_id' => $orderId,
        'invoice_no' => $invoiceNo,
        'items_saved' => $itemsSaved,
        'saved_to_db' => true,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    error_log("[" . date('Y-m-d H:i:s') . "] Order saved: Order ID {$orderId}, Invoice {$invoiceNo}\n", 3, "php_log.txt");
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // ROLLBACK jika error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log error
    $errorMsg = "Database Error: " . $e->getMessage();
    error_log("[" . date('Y-m-d H:i:s') . "] " . $errorMsg . "\n", 3, "php_log.txt");
    
    $response = [
        'success' => false,
        'message' => 'Gagal menyimpan pesanan: ' . $e->getMessage(),
        'saved_to_db' => false,
        'error_details' => $errorMsg
    ];
    
    echo json_encode($response);
}
?>