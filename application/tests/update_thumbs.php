<?php
$pdo = new PDO('mysql:host=localhost;dbname=shopkart;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->prepare('UPDATE products SET thumbnail = ? WHERE id = ?');
$stmt->execute(['assets/uploads/products/products_6acc6eeb541a4.jpg', 1]);
$stmt->execute(['assets/uploads/products/products_banarasi_royal_blue.jpg', 2]);

// Also for any other sarees that have non-existent files, point to valid photos
$stmt->execute(['assets/uploads/products/products_banarasi_royal_blue.jpg', 3]);
$stmt->execute(['assets/uploads/products/products_6acc6eeb541a4.jpg', 4]);
$stmt->execute(['assets/uploads/products/products_banarasi_royal_blue.jpg', 5]);
$stmt->execute(['assets/uploads/products/products_6acc6eeb541a4.jpg', 6]);
$stmt->execute(['assets/uploads/products/products_banarasi_royal_blue.jpg', 7]);
$stmt->execute(['assets/uploads/products/products_6acc6eeb541a4.jpg', 8]);
$stmt->execute(['assets/uploads/products/products_banarasi_royal_blue.jpg', 9]);
$stmt->execute(['assets/uploads/products/products_6acc6eeb541a4.jpg', 10]);
$stmt->execute(['assets/uploads/products/products_banarasi_royal_blue.jpg', 11]);
$stmt->execute(['assets/uploads/products/products_6acc6eeb541a4.jpg', 12]);
$stmt->execute(['assets/uploads/products/products_6acc6eeb541a4.jpg', 16]);

echo "Updated all 13 sarees to real existing image files!\n";
