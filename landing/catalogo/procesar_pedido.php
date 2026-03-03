<?php
session_start();
require_once '../includes/conexion.php';

// Verificamos que vengan datos del formulario POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_SESSION['carrito'])) {
    
    $email = $_POST['email'];
    $telefono = $_POST['telefono'];
    $domicilio = $_POST['domicilio'];

    try {
        $pdo->beginTransaction();

        // 1. Insertar con los nuevos datos
        $stmt = $pdo->prepare("INSERT INTO pedidos (fecha_pedido, total, status, email, telefono, domicilio) VALUES (NOW(), 0, 'pendiente', ?, ?, ?)");
        $stmt->execute([$email, $telefono, $domicilio]);
        $id_pedido = $pdo->lastInsertId();

        $total_general = 0;

        // 2. Insertar los detalles
        foreach ($_SESSION['carrito'] as $id_producto => $cantidad) {
            $stmt_p = $pdo->prepare("SELECT precio, nombre FROM productos WHERE id = ?");
            $stmt_p->execute([$id_producto]);
            $prod = $stmt_p->fetch();

            if ($prod) {
                $subtotal = $prod['precio'] * $cantidad;
                $producto_nombre = $prod['nombre'];
                $total_general += $subtotal;

                $stmt_det = $pdo->prepare("INSERT INTO detalle_pedido (pedido_id, producto_id, producto_nombre, cantidad, precio_unitario) VALUES (?, ?, ?, ?, ?)");
                $stmt_det->execute([$id_pedido, $id_producto, $producto_nombre, $cantidad, $prod['precio']]);
            }
        }

        // 3. Actualizar total
        $stmt_upd = $pdo->prepare("UPDATE pedidos SET total = ? WHERE id = ?");
        $stmt_upd->execute([$total_general, $id_pedido]);

        $pdo->commit();
        
        // --- AQUÍ VACIAMOS EL CARRITO ---
        unset($_SESSION['carrito']); 

        header("Location: ver_carrito.php?orden_ok=$id_pedido");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
} else {
    header('Location: ver_carrito.php');
}