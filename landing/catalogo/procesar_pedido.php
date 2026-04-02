<?php
session_start();
require_once '../includes/conexion.php';

// Verificamos que vengan datos del formulario POST y que el carrito no esté vacío
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_SESSION['carrito'])) {
    
    $email = $_POST['email'];
    $telefono = $_POST['telefono'];
    $domicilio = $_POST['domicilio'];
    
    // Capturamos si viene de un cliente logueado
    $cliente_id = $_SESSION['cliente_id'] ?? null;
    $tipo_usuario = $_SESSION['tipo_cliente'] ?? 0;

    try {
        $pdo->beginTransaction();

        // 1. ACTUALIZAR PERFIL DEL CLIENTE (Si está logueado)
        if ($cliente_id) {
            $stmt_perfil = $pdo->prepare("UPDATE clientes SET telefono = ?, direccion = ? WHERE id = ?");
            $stmt_perfil->execute([$telefono, $domicilio, $cliente_id]);
        }

        // 2. INSERTAR EL PEDIDO MAESTRO
        $stmt = $pdo->prepare("INSERT INTO pedidos (fecha_pedido, total, status, email, telefono, domicilio) VALUES (NOW(), 0, 'Pendiente', ?, ?, ?)");
        $stmt->execute([$email, $telefono, $domicilio]);
        $id_pedido = $pdo->lastInsertId();

        $total_bruto = 0;

        // 3. PROCESAR DETALLES E INVENTARIO POR FÓRMULA
        foreach ($_SESSION['carrito'] as $id_producto => $cantidad) {
            // Obtenemos datos del producto incluyendo su fórmula y volumen por unidad
            $stmt_p = $pdo->prepare("SELECT precio, nombre, id_formula_maestra, volumen_valor FROM productos WHERE id = ?");
            $stmt_p->execute([$id_producto]);
            $prod = $stmt_p->fetch();

            if ($prod) {
                $subtotal = $prod['precio'] * $cantidad;
                $total_bruto += $subtotal;

                // A. Insertar el detalle del pedido
                $stmt_det = $pdo->prepare("INSERT INTO detalle_pedido (pedido_id, producto_id, producto_nombre, cantidad, precio_unitario) VALUES (?, ?, ?, ?, ?)");
                $stmt_det->execute([$id_pedido, $id_producto, $prod['nombre'], $cantidad, $prod['precio']]);

                // B. DESCUENTO DE INVENTARIO POR FÓRMULA (Granel)
                // Solo si el producto tiene una fórmula asociada
                if (!empty($prod['id_formula_maestra'])) {
                    // Calculamos: Litros por unidad * cantidad vendida
                    $litros_a_restar = $prod['volumen_valor'] * $cantidad;

                    // Restamos del stock disponible en la fórmula maestra
                    $stmt_inv = $pdo->prepare("UPDATE formulas_maestras SET stock_litros_disponibles = stock_litros_disponibles - ? WHERE id = ?");
                    $stmt_inv->execute([$litros_a_restar, $prod['id_formula_maestra']]);
                }
            }
        }

        // 4. CALCULAR DESCUENTO POR NIVEL DE CLIENTE
        $porcentaje_descuento = 0;
        if ($cliente_id) {
            switch ($tipo_usuario) {
                case 1: $porcentaje_descuento = 0.05; break;
                case 2: $porcentaje_descuento = 0.10; break;
                case 3: $porcentaje_descuento = 0.20; break;
            }
        }
        $total_con_descuento = $total_bruto - ($total_bruto * $porcentaje_descuento);

        // 5. ACTUALIZAR TOTAL FINAL DEL PEDIDO
        $stmt_upd = $pdo->prepare("UPDATE pedidos SET total = ? WHERE id = ?");
        $stmt_upd->execute([$total_con_descuento, $id_pedido]);

        // Si todo salió bien, confirmamos los cambios en la DB
        $pdo->commit();
        
        // Vaciamos el carrito de la sesión
        unset($_SESSION['carrito']); 

        // Redirigimos con confirmación
        header("Location: ver_carrito.php?orden_ok=$id_pedido");
        exit;

    } catch (Exception $e) {
        // Si algo falla, deshacemos todo para no dejar datos inconsistentes
        $pdo->rollBack();
        die("Error crítico en el inventario de AHD Clean: " . $e->getMessage());
    }
} else {
    header('Location: ver_carrito.php');
}