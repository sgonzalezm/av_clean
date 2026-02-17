<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
$stmt->execute([$id]);

header('Location: productos.php');
exit;
?>