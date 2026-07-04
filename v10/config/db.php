<?php
// config/db.php
// Connects the PHP code to your MySQL Database

$host = 'localhost';
$db   = 'susumura_warehouse_system';
$user = 'susumura_MMSadmin';     // Default XAMPP username
$pass = 'MmS620@tmE';         // Default XAMPP password (leave empty)
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // If connection fails, stop everything and show error
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>