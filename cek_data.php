<?php
// check_data.php
header('Content-Type: application/json');

$host = "localhost";
$username = "root";
$password = "";
$database = "restaurant_db";

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get total orders count
$count_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders");
$count_row = mysqli_fetch_assoc($count_result);
$total_orders = $count_row['total'];

// Get last 10 orders
$orders_result = mysqli_query($conn, "SELECT * FROM orders ORDER BY id DESC LIMIT 10");
$orders = [];
while ($row = mysqli_fetch_assoc($orders_result)) {
    $orders[] = $row;
}

echo json_encode([
    'success' => true,
    'total_orders' => $total_orders,
    'orders' => $orders
]);

mysqli_close($conn);
?>