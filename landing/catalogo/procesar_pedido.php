<?php
session_start();
require_once '../includes/conexion.php';

if (!isset($_SESSION['carrito']) || empty($_SESSION['carrito'])) {
    header('Location: ver_carrito.php'); // Corregido el nombre del archivo
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Insertar el encabezado
    $stmt = $pdo->prepare("INSERT INTO pedidos (fecha_pedido, total, status) VALUES (NOW(), 0, 'pendiente')");
    $stmt->execute();
    $id_pedido = $pdo->lastInsertId();

    $total_general = 0;

    // 2. Insertar los detalles
    foreach ($_SESSION['carrito'] as $id_producto => $cantidad) {
        $stmt_p = $pdo->prepare("SELECT precio FROM productos WHERE id = ?");
        $stmt_p->execute([$id_producto]);
        $prod = $stmt_p->fetch();

        if ($prod) {
            $subtotal = $prod['precio'] * $cantidad;
            $total_general += $subtotal;

            $stmt_det = $pdo->prepare("INSERT INTO detalle_pedido (pedido_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
            $stmt_det->execute([$id_pedido, $id_producto, $cantidad, $prod['precio']]);
        }
    }

    // 3. Actualizar el total real
    $stmt_upd = $pdo->prepare("UPDATE pedidos SET total = ? WHERE id = ?");
    $stmt_upd->execute([$total_general, $id_pedido]);

    $pdo->commit();
    
    // REDIRECCIÓN CORREGIDA: nombre de archivo y variable coherentes
    header("Location: ver_carrito.php?orden_ok=$id_pedido");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error al registrar el pedido: " . $e->getMessage());
}