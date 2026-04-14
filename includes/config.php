<?php
$host = "mysql.railway.internal";
$db   = "railway";
$user = "root";
$pass = "rclNByooYtLqkTnHFgfGwITfXXvsLovN";
$port = 3306;

try {
     $pdo = new PDO("mysql:host=$host;dbname=$db;port=$port;charset=utf8mb4", $user, $pass);
     $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
     die("Error: " . $e->getMessage());
}
