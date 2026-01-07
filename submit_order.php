<?php
// submit_order.php - VERSION FIXED
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ==================== KONFIGURASI ====================
$host = "localhost";
$username = "root";
$password = "";  // Laragon default
$database = "sehstdamäkat";  // Nama database dari file .sql
$port = 3306;

// ==================== FUNGSI DEBUG ====================
function debugLog($message, $data = null) {
    $logFile = 'debug_' . date('Y-m-d') . '.log';
    $timestamp = date('H:i:s');
    $logEntry = "[$timestamp] $message";
    
    if ($data !== null) {
        $logEntry .= " | Data: " . json_encode($data);
    }
    
    $logEntry .= "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    
    // Juga tampilkan di response untuk debugging
    if (isset($_GET['debug'])) {
        echo "<!-- DEBUG: $message -->\n";
    }
}

// ==================== MULAI ====================
debugLog("=== START ORDER SUBMISSION ===");

// Ambil data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    debugLog("ERROR: No valid JSON data received");
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit;
}

debugLog("Data parsed", ['order_id' => $data['order_id'] ?? 'N/A', 'customer' => $data['customer_name'] ?? 'N/A']);

// ==================== KONEKSI DATABASE ====================
debugLog("Connecting to database: $database");
$conn = mysqli_connect($host, $username, $password, $database, $port);

if (!$conn) {
    $error = mysqli_connect_error();
    debugLog("DATABASE CONNECTION FAILED", ['error' => $error]);
    
    // Coba tanpa database dulu
    $conn_temp = mysqli_connect($host, $username, $password, '', $port);
    if ($conn_temp) {
        debugLog("Connected to MySQL server");
        
        // Cek database
        $dbCheck = mysqli_query($conn_temp, "SHOW DATABASES LIKE '$database'");
        if (mysqli_num_rows($dbCheck) == 0) {
            debugLog("Database does not exist, creating...");
            mysqli_query($conn_temp, "CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
        
        mysqli_select_db($conn_temp, $database);
        $conn = $conn_temp;
        debugLog("Database selected");
    }
}

if (!$conn) {
    debugLog("FATAL: Cannot connect to database");
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database connection failed',
        'error' => mysqli_connect_error()
    ]);
    exit;
}

debugLog("Database connected successfully");

// Set charset
mysqli_set_charset($conn, "utf8mb4");

// ==================== CEK STRUKTUR TABEL ====================
debugLog("Checking table structure...");

// Cek tabel orders
$tableCheck = mysqli_query($conn, "DESCRIBE orders");
if (!$tableCheck) {
    debugLog("Table 'orders' doesn't exist or cannot be accessed");
    
    // Buat tabel sesuai struktur yang benar
    $createSQL = "CREATE TABLE IF NOT EXISTS `orders` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `user_id` int(11) DEFAULT NULL,
        `customer_name` varchar(100) DEFAULT NULL,
        `table_number` int(11) DEFAULT NULL,
        `invoice_no` varchar(50) DEFAULT NULL,
        `total_price` decimal(12,2) DEFAULT 0.00,
        `payment_method` enum('cash','qris') DEFAULT 'cash',
        `status` enum('pending','cooking','ready','completed','cancelled') DEFAULT 'pending',
        `transaction_date` date DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp()
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if (mysqli_query($conn, $createSQL)) {
        debugLog("Table 'orders' created successfully");
    } else {
        debugLog("ERROR creating table", ['error' => mysqli_error($conn)]);
    }
} else {
    debugLog("Table 'orders' exists");
    
    // Cek kolom yang ada
    $columns = [];
    while ($row = mysqli_fetch_assoc($tableCheck)) {
        $columns[] = $row['Field'];
    }
    debugLog("Columns in orders table", $columns);
}

// ==================== PREPARE DATA SESUAI TABEL ====================
// Sesuaikan dengan struktur tabel yang ada
$invoice_no = mysqli_real_escape_string($conn, $data['invoice_no'] ?? ($data['order_id'] ?? 'INV-' . time()));
$customer_name = mysqli_real_escape_string($conn, $data['customer_name'] ?? '');
$table_number = intval($data['table_number'] ?? 0);
$total_price = floatval($data['total_price'] ?? 0);
$payment_method = mysqli_real_escape_string($conn, $data['payment_method'] ?? 'cash');

// Validasi payment_method sesuai enum
if (!in_array($payment_method, ['cash', 'qris'])) {
    $payment_method = 'cash';
}

$status = 'pending';
$transaction_date = date('Y-m-d');
$user_id = NULL; // Karena tidak ada user login

debugLog("Data prepared for insert", [
    'invoice_no' => $invoice_no,
    'customer_name' => $customer_name,
    'table_number' => $table_number,
    'total_price' => $total_price,
    'payment_method' => $payment_method
]);

// ==================== INSERT KE DATABASE ====================
$sql = "INSERT INTO orders (
    user_id,
    customer_name,
    table_number,
    invoice_no,
    total_price,
    payment_method,
    status,
    transaction_date,
    created_at
) VALUES (
    NULL,
    '$customer_name',
    $table_number,
    '$invoice_no',
    $total_price,
    '$payment_method',
    '$status',
    '$transaction_date',
    NOW()
)";

debugLog("SQL Query", ['query' => $sql]);

$result = mysqli_query($conn, $sql);

if ($result) {
    $insert_id = mysqli_insert_id($conn);
    $affected_rows = mysqli_affected_rows($conn);
    
    debugLog("INSERT SUCCESSFUL!", [
        'insert_id' => $insert_id,
        'affected_rows' => $affected_rows,
        'invoice_no' => $invoice_no
    ]);
    
    // ==================== SIMPAN ITEMS (jika ada tabel order_items) ====================
    $items_saved = false;
    $items_count = 0;
    
    // Cek tabel order_items
    $itemsTableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'order_items'");
    $items = $data['items'] ?? [];
    
    if (mysqli_num_rows($itemsTableCheck) > 0 && count($items) > 0) {
        debugLog("Saving items to order_items table");
        
        foreach ($items as $item) {
            $item_name = mysqli_real_escape_string($conn, $item['name'] ?? '');
            $item_price = floatval($item['price'] ?? 0);
            $item_qty = intval($item['qty'] ?? 1);
            $item_total = $item_price * $item_qty;
            
            $itemSQL = "INSERT INTO order_items (order_id, item_name, price, quantity, total_price) 
                       VALUES ($insert_id, '$item_name', $item_price, $item_qty, $item_total)";
            
            if (mysqli_query($conn, $itemSQL)) {
                $items_count++;
            }
        }
        
        if ($items_count > 0) {
            $items_saved = true;
            debugLog("Items saved", ['count' => $items_count]);
        }
    } else {
        debugLog("Items not saved to database (no order_items table or empty items)");
    }
    
    // ==================== VERIFIKASI DATA MASUK ====================
    $verifySQL = "SELECT * FROM orders WHERE id = $insert_id";
    $verifyResult = mysqli_query($conn, $verifySQL);
    
    if ($verifyResult && mysqli_num_rows($verifyResult) > 0) {
        $row = mysqli_fetch_assoc($verifyResult);
        debugLog("VERIFICATION PASSED: Data found in database", $row);
        
        // Response sukses
        echo json_encode([
            'success' => true,
            'message' => 'Order saved to database successfully',
            'database_id' => $insert_id,
            'invoice_no' => $invoice_no,
            'customer_name' => $customer_name,
            'table_number' => $table_number,
            'total_price' => $total_price,
            'items_saved' => $items_saved,
            'items_count' => $items_count,
            'verification' => 'passed',
            'debug' => [
                'affected_rows' => $affected_rows,
                'sql_state' => mysqli_sqlstate($conn)
            ]
        ]);
        
    } else {
        debugLog("VERIFICATION FAILED: Data not found after insert!");
        
        echo json_encode([
            'success' => false,
            'message' => 'Data not found in database after insert',
            'database_id' => $insert_id,
            'verification' => 'failed'
        ]);
    }
    
} else {
    $error = mysqli_error($conn);
    $errno = mysqli_errno($conn);
    
    debugLog("INSERT FAILED!", [
        'error' => $error,
        'errno' => $errno,
        'sql_state' => mysqli_sqlstate($conn)
    ]);
    
    // Response error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database insert failed',
        'error' => $error,
        'errno' => $errno,
        'sql_state' => mysqli_sqlstate($conn),
        'debug' => [
            'invoice_no' => $invoice_no,
            'sql_query' => $sql
        ]
    ]);
}

// ==================== CLEANUP ====================
mysqli_close($conn);
debugLog("=== END ORDER SUBMISSION ===");

// ==================== BACKUP KE FILE ====================
$backup_data = [
    'order_data' => $data,
    'server_data' => [
        'invoice_no' => $invoice_no,
        'database_id' => $insert_id ?? null,
        'timestamp' => date('Y-m-d H:i:s'),
        'success' => isset($result) ? $result : false
    ]
];

if (!is_dir('orders_backup')) {
    mkdir('orders_backup', 0777, true);
}

$backup_file = 'orders_backup/backup_' . date('Y-m-d_H-i-s') . '.json';
file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT));
debugLog("Backup saved to: $backup_file");
?>