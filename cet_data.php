<?php
// cet_data.php
require_once 'config/database.php';

echo "<h2>Data Orders di Database:</h2>";

try {
    $stmt = $pdo->query("
        SELECT o.*, 
               COUNT(oi.id) as item_count,
               SUM(oi.qty * oi.price) as subtotal
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        GROUP BY o.id
        ORDER BY o.created_at DESC 
        LIMIT 10
    ");
    
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($orders)) {
        echo "<p>Tidak ada data order</p>";
    } else {
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr style='background: #f2f2f2;'>
                <th>ID</th><th>Invoice</th><th>Customer</th>
                <th>Table</th><th>Total</th><th>Status</th>
                <th>Items</th><th>Tanggal</th>
              </tr>";
        
        foreach ($orders as $order) {
            $statusColor = match($order['status']) {
                'completed' => 'green',
                'pending' => 'orange',
                'cancelled' => 'red',
                default => 'black'
            };
            
            echo "<tr>";
            echo "<td>{$order['id']}</td>";
            echo "<td><strong>{$order['invoice_no']}</strong></td>";
            echo "<td>{$order['customer_name']}</td>";
            echo "<td>{$order['table_number']}</td>";
            echo "<td>Rp " . number_format($order['total_price'], 0, ',', '.') . "</td>";
            echo "<td style='color: {$statusColor};'>{$order['status']}</td>";
            echo "<td>{$order['item_count']} items</td>";
            echo "<td>" . date('d/m/Y H:i', strtotime($order['created_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>