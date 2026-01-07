<?php
// test_database.php
$host = "localhost";
$username = "root";
$password = "";
$database = "db_sehatdansikat";

echo "<h1>Database Test</h1>";

// Test connection
$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    echo "<p style='color: red;'>❌ Connection failed: " . mysqli_connect_error() . "</p>";
    exit;
}

echo "<p style='color: green;'>✅ Connected successfully</p>";
echo "<p>Host info: " . mysqli_get_host_info($conn) . "</p>";

// Check database
$result = mysqli_query($conn, "SELECT DATABASE()");
$row = mysqli_fetch_row($result);
echo "<p>Current database: " . $row[0] . "</p>";

// Check tables
$tables = mysqli_query($conn, "SHOW TABLES");
echo "<h2>Tables in database:</h2>";
echo "<ul>";
while ($table = mysqli_fetch_row($tables)) {
    echo "<li>" . $table[0] . "</li>";
    
    // Show table structure
    $desc = mysqli_query($conn, "DESCRIBE " . $table[0]);
    echo "<ul>";
    while ($col = mysqli_fetch_assoc($desc)) {
        echo "<li>" . $col['Field'] . " (" . $col['Type'] . ")</li>";
    }
    echo "</ul>";
}
echo "</ul>";

// Test insert
echo "<h2>Test Insert:</h2>";
$test_sql = "INSERT INTO orders (customer_name, table_number, invoice_no, total_price, payment_method, transaction_date) 
            VALUES ('Test Customer', 1, 'TEST-" . time() . "', 50000, 'cash', CURDATE())";

if (mysqli_query($conn, $test_sql)) {
    echo "<p style='color: green;'>✅ Test insert successful</p>";
    echo "<p>Insert ID: " . mysqli_insert_id($conn) . "</p>";
    
    // Show last 5 orders
    $select = mysqli_query($conn, "SELECT * FROM orders ORDER BY id DESC LIMIT 5");
    echo "<h3>Last 5 orders:</h3>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Invoice</th><th>Customer</th><th>Table</th><th>Total</th><th>Created</th></tr>";
    while ($row = mysqli_fetch_assoc($select)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['invoice_no'] . "</td>";
        echo "<td>" . $row['customer_name'] . "</td>";
        echo "<td>" . $row['table_number'] . "</td>";
        echo "<td>" . $row['total_price'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ Test insert failed: " . mysqli_error($conn) . "</p>";
}

mysqli_close($conn);
?>