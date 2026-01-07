<?php
session_start();
require_once 'config/database.php'; // File koneksi database

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan']);
    exit;
}

// Ambil data dari POST request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['items']) || !isset($data['customer_name']) || !isset($data['table_number'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

try {
    $conn = getConnection();
    
    // Generate invoice number
    $invoice_no = 'INV-' . date('Ymd-His') . '-' . rand(100, 999);
    $total_price = $data['total_price'];
    $customer_name = $conn->real_escape_string($data['customer_name']);
    $table_number = intval($data['table_number']);
    $payment_method = isset($data['payment_method']) ? $conn->real_escape_string($data['payment_method']) : 'cash';
    
    // 1. Insert ke tabel orders
    $sql = "INSERT INTO orders (customer_name, table_number, invoice_no, total_price, payment_method, status, transaction_date) 
            VALUES ('$customer_name', $table_number, '$invoice_no', $total_price, '$payment_method', 'pending', CURDATE())";
    
    if ($conn->query($sql)) {
        $order_id = $conn->insert_id;
        
        // 2. Insert items ke order_items
        foreach ($data['items'] as $item) {
            $product_id = intval($item['id']);
            $qty = intval($item['qty']);
            $price = floatval($item['price']);
            
            $sql_item = "INSERT INTO order_items (order_id, product_id, qty, price) 
                         VALUES ($order_id, $product_id, $qty, $price)";
            $conn->query($sql_item);
            
            // 3. Kurangi stock bahan baku (optional)
            updateMaterialsStock($conn, $product_id, $qty);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Pesanan berhasil disimpan',
            'order_id' => $order_id,
            'invoice_no' => $invoice_no
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan pesanan: ' . $conn->error]);
    }
    
    $conn->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// Fungsi untuk update stock bahan baku
function updateMaterialsStock($conn, $product_id, $qty) {
    // Ambil bahan baku yang dibutuhkan untuk produk ini
    $sql = "SELECT material_id, quantity_needed FROM product_material WHERE product_id = $product_id";
    $result = $conn->query($sql);
    
    while ($row = $result->fetch_assoc()) {
        $material_id = $row['material_id'];
        $quantity_needed = floatval($row['quantity_needed']) * $qty;
        
        // Update stock
        $update_sql = "UPDATE materials SET stock = stock - $quantity_needed WHERE id = $material_id";
        $conn->query($update_sql);
    }
}
?>