<?php
$host = 'localhost';
$dbname = 'u918498641_catalogo_db';
$username = 'u918498641_sgonzalezm';
$password = '3lR10Quefluye$';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>