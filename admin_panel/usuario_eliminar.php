<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();
// Solo admin puede eliminar usuarios
verificarRol(['admin']);

$id = $_GET['id'] ?? 0;

// No permitir eliminarse a sí mismo
if ($id == $_SESSION['admin_id']) {
    header('Location: usuarios.php?error=noautoeliminar');
    exit;
}

$stmt = $pdo->prepare("DELETE FROM usuarios_admin WHERE id = ?");
$stmt->execute([$id]);

header('Location: usuarios.php?ok=3');
exit;
?>