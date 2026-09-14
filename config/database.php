<?php
// config/database.php

$host     = '127.0.0.1';
$port     = '8889';              // Puerto MySQL de MAMP
$dbname   = 'Sistema_SIGE_COBAEP';
$username = 'root';
$password = 'root';              // MAMP usa root por defecto

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error crítico de conexión a la base de datos: " . $e->getMessage());
}
