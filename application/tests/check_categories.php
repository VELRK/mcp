<?php
define('BASEPATH', true);
define('FCPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
require FCPATH . 'application/config/database.php';
$dbCfg = $db['default'];
$conn = mysqli_connect($dbCfg['hostname'], $dbCfg['username'], $dbCfg['password'], $dbCfg['database']);

echo "=== CATEGORIES IN DATABASE ===\n";
$res = mysqli_query($conn, "SELECT c.id, c.name, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON p.category_id = c.id AND p.vendor_id = 2 
    GROUP BY c.id HAVING product_count > 0");
while ($row = mysqli_fetch_assoc($res)) {
    echo "Category #{$row['id']}: {$row['name']} ({$row['product_count']} products)\n";
}

echo "\n=== ALL CATEGORIES ===\n";
$res2 = mysqli_query($conn, "SELECT id, name FROM categories LIMIT 20");
while ($row = mysqli_fetch_assoc($res2)) {
    echo "ID: {$row['id']} - Name: {$row['name']}\n";
}

echo "\n=== PRODUCTS FOR VENDOR 2 ===\n";
$res3 = mysqli_query($conn, "SELECT p.id, p.name, p.category_id, c.name as cat_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.vendor_id = 2");
while ($row = mysqli_fetch_assoc($res3)) {
    echo "Prod #{$row['id']}: {$row['name']} | Cat: {$row['cat_name']} (ID {$row['category_id']})\n";
}
