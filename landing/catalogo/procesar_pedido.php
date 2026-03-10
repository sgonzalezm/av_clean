<?php
session_start();
require_once '../includes/conexion.php';

// Verificamos que vengan datos del formulario POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_SESSION['carrito'])) {
    
    $email = $_POST['email'];
    $telefono = $_POST['telefono'];
    $domicilio = $_POST['domicilio'];
    
    // Capturamos si viene de un cliente logueado
    $cliente_id = $_SESSION['cliente_id'] ?? null;
    $tipo_usuario = $_SESSION['tipo_cliente'] ?? 0;

    try {
        $pdo->beginTransaction();

        // --- BLOQUE NUEVO: ACTUALIZAR PERFIL DEL CLIENTE ---
        if ($cliente_id) {
            // Actualizamos la tabla clientes con el teléfono y dirección recibidos
            // Usamos 'direccion' porque así aparece en tu captura de base de datos
            $stmt_perfil = $pdo->prepare("UPDATE clientes SET telefono = ?, direccion = ? WHERE id = ?");
            $stmt_perfil->execute([$telefono, $domicilio, $cliente_id]);
        }

        // 1. Insertar el pedido con los datos del formulario
        $stmt = $pdo->prepare("INSERT INTO pedidos (fecha_pedido, total, status, email, telefono, domicilio) VALUES (NOW(), 0, 'pendiente', ?, ?, ?)");
        $stmt->execute([$email, $telefono, $domicilio]);
        $id_pedido = $pdo->lastInsertId();

        $total_bruto = 0;

        // 2. Insertar los detalles
        foreach ($_SESSION['carrito'] as $id_producto => $cantidad) {
            $stmt_p = $pdo->prepare("SELECT precio, nombre FROM productos WHERE id = ?");
            $stmt_p->execute([$id_producto]);
            $prod = $stmt_p->fetch();

            if ($prod) {
                $subtotal = $prod['precio'] * $cantidad;
                $total_bruto += $subtotal;

                $stmt_det = $pdo->prepare("INSERT INTO detalle_pedido (pedido_id, producto_id, producto_nombre, cantidad, precio_unitario) VALUES (?, ?, ?, ?, ?)");
                $stmt_det->execute([$id_pedido, $id_producto, $prod['nombre'], $cantidad, $prod['precio']]);
            }
        }

        // --- BLOQUE NUEVO: CALCULAR DESCUENTO REAL ---
        $porcentaje_descuento = 0;
        if ($cliente_id) {
            switch ($tipo_usuario) {
                case 1: $porcentaje_descuento = 0.05; break;
                case 2: $porcentaje_descuento = 0.10; break;
                case 3: $porcentaje_descuento = 0.20; break;
            }
        }
        $total_con_descuento = $total_bruto - ($total_bruto * $porcentaje_descuento);

        // 3. Actualizar total final (con descuento aplicado si aplica)
        $stmt_upd = $pdo->prepare("UPDATE pedidos SET total = ? WHERE id = ?");
        $stmt_upd->execute([$total_con_descuento, $id_pedido]);

        $pdo->commit();
        
        // Vaciamos el carrito
        unset($_SESSION['carrito']); 

        header("Location: ver_carrito.php?orden_ok=$id_pedido");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error en AHD Clean: " . $e->getMessage());
    }
} else {
    header('Location: ver_carrito.php');
}