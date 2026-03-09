<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$id = $_GET['id'] ?? 0;

if ($id > 0) {
    try {
        // Iniciamos una transacción para que si algo falla, no se borre nada a medias
        $pdo->beginTransaction();

        // 1. Primero eliminamos el rastro del producto en la tabla inventario
        $stmtInventario = $pdo->prepare("DELETE FROM inventario WHERE producto_id = ?");
        $stmtInventario->execute([$id]);

        // 2. Ahora que no tiene dependencias, eliminamos el producto de su tabla principal
        $stmtProducto = $pdo->prepare("DELETE FROM productos WHERE id = ?");
        $stmtProducto->execute([$id]);

        // Confirmamos los cambios
        $pdo->commit();
        
        // Redirigimos con un mensaje de éxito (opcional)
        header('Location: catalogo_productos.php?ok=3');
        exit;

    } catch (Exception $e) {
        // Si hay un error, deshacemos cualquier borrado previo
        $pdo->rollBack();
        die("Error al eliminar el producto: " . $e->getMessage());
    }
} else {
    header('Location: catalogo_productos.php');
    exit;
}
?>