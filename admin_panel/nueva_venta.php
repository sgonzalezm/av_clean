<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// 1. Obtener productos
$productos = $pdo->query("SELECT id, nombre, precio FROM productos ORDER BY nombre ASC")->fetchAll();

// 2. Obtener clientes con su descuento según el tipo
$sql_clientes = "SELECT c.id, c.nombre_completo, c.email, tc.nombre as tipo, tc.descuento_porcentaje 
                 FROM clientes c 
                 INNER JOIN tipos_cliente tc ON c.tipo_cliente_id = tc.id 
                 WHERE c.estatus = 'Activo' ORDER BY c.nombre_completo ASC";
$clientes = $pdo->query($sql_clientes)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();

        $usuario_id = $_SESSION['admin_id']; 
        $cliente_id = $_POST['cliente_id'];
        $total_antes_descuento = 0;

        // --- NUEVA LÓGICA: Obtener datos completos del cliente ---
        $stmt_c = $pdo->prepare("SELECT c.nombre_completo, c.email, c.telefono, c.direccion, tc.descuento_porcentaje 
                                 FROM clientes c 
                                 INNER JOIN tipos_cliente tc ON c.tipo_cliente_id = tc.id 
                                 WHERE c.id = ?");
        $stmt_c->execute([$cliente_id]);
        $info_cliente = $stmt_c->fetch();

        if (!$info_cliente) throw new Exception("Cliente no encontrado.");

        $nombre_cliente = $info_cliente['nombre_completo'];
        $email_cliente = $info_cliente['email'];
        $telefono_cliente = $info_cliente['telefono'];
        $direccion_cliente = $info_cliente['direccion'];
        $desc_cliente = $info_cliente['descuento_porcentaje'] / 100;

        // 3. Crear pedido base incluyendo NOMBRE y EMAIL (asumiendo que las columnas existen en 'pedidos')
        // Si tu columna de nombre se llama diferente (ej. 'cliente_nombre'), ajusta el campo abajo.
        $stmt = $pdo->prepare("INSERT INTO pedidos (usuario_id, cliente_id, nombre, email, telefono, domicilio, total, status, fecha_pedido) VALUES (?, ?, ?, ?, ?, ?, 0, 'Confirmado', NOW())");
        $stmt->execute([$usuario_id, $cliente_id, $nombre_cliente, $email_cliente, $telefono_cliente, $direccion_cliente]);
        $pedido_id = $pdo->lastInsertId();

        foreach ($_POST['productos'] as $item) {
            $cantidad = intval($item['cantidad']);
            if ($cantidad > 0) {
                $p_id = $item['id'];
                $p_info = $pdo->prepare("SELECT nombre, precio FROM productos WHERE id = ?");
                $p_info->execute([$p_id]);
                $prod = $p_info->fetch();

                if ($prod) {
                    $subtotal = $prod['precio'] * $cantidad;
                    $total_antes_descuento += $subtotal;

                    $ins = $pdo->prepare("INSERT INTO detalle_pedido (pedido_id, producto_id, cantidad, producto_nombre, precio_unitario) VALUES (?, ?, ?, ?, ?)");
                    $ins->execute([$pedido_id, $p_id, $cantidad, $prod['nombre'], $prod['precio']]);
                }
            }
        }

        if ($total_antes_descuento > 0) {
            $total_final = $total_antes_descuento * (1 - $desc_cliente);
            $pdo->prepare("UPDATE pedidos SET total = ? WHERE id = ?")->execute([$total_final, $pedido_id]);
            $pdo->commit();
            header("Location: index.php?msj=Venta exitosa");
        } else {
            $pdo->rollBack();
            $error = "Debe seleccionar al menos un producto.";
        }
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Nueva Venta | Punto de Venta</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header">
            <h1><i class="fas fa-cash-register"></i> Punto de Venta Interno</h1>
            <div class="user-badge"><i class="fas fa-user"></i> <?php echo $_SESSION['admin_nombre']; ?></div>
        </div>

        <div class="form-container">
            <?php if(isset($error)): ?>
                <div style="background:#fee2e2; color:#b91c1c; padding:15px; border-radius:8px; margin-bottom:20px;"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" class="slide-in" id="formVenta">
                <div class="form-group">
                    <label><i class="fas fa-user-tag"></i> Seleccionar Cliente</label>
                    <select name="cliente_id" id="cliente_id" class="form-control" required style="height: 45px;">
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" data-descuento="<?php echo $c['descuento_porcentaje']; ?>">
                                <?php echo htmlspecialchars($c['nombre_completo']); ?> (<?php echo $c['tipo']; ?> - <?php echo (int)$c['descuento_porcentaje']; ?>% Desc.)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="total-bar" style="background:#2d3748; color:white; padding:20px; margin-top:20px; border-radius:10px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <span style="font-size:0.9rem; color:#cbd5e0; display:block;">Subtotal: <span id="subtotalTxt">$0.00</span></span>
                            <span style="font-size:0.9rem; color:#a0aec0; display:block;">Descuento aplicado: <span id="descTxt">0%</span></span>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-size:1.2rem; font-weight:bold;">Total a Cobrar:</span>
                            <span id="granTotal" style="font-size:2.5rem; font-weight:900; display:block; color:#4fd1c5;">$0.00</span>
                        </div>
                    </div>
                </div>

                <div class="search-container" style="margin-top:20px;">
                    <div style="position:relative;">
                        <i class="fas fa-search" style="position:absolute; left:15px; top:18px; color:#aaa;"></i>
                        <input type="text" id="buscador" class="form-control" placeholder="Filtrar productos..." style="padding-left:45px; height:50px; font-size:1.1rem;">
                    </div>
                </div>

                <div class="tabla-contenedor" style="margin-top:20px; border:1px solid #eee; border-radius:10px; max-height: 450px; overflow-y: auto;">
                    <table style="width:100%; border-collapse: collapse;" id="tablaProductos">
                        <thead style="background:#f9f9f9; position: sticky; top: 0; z-index: 1;">
                            <tr>
                                <th style="padding:15px; text-align:left;">Producto</th>
                                <th style="padding:15px; text-align:right;">Precio Unit.</th>
                                <th style="padding:15px; width:120px;">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $i => $p): ?>
                            <tr class="fila-producto" style="border-top:1px solid #eee;">
                                <td style="padding:15px;">
                                    <strong class="nombre-producto"><?php echo htmlspecialchars($p['nombre']); ?></strong>
                                    <input type="hidden" name="productos[<?php echo $i; ?>][id]" value="<?php echo $p['id']; ?>">
                                </td>
                                <td style="padding:15px; text-align:right;">
                                    $<span class="precio-unitario"><?php echo number_format($p['precio'], 2, '.', ''); ?></span>
                                </td>
                                <td style="padding:15px;">
                                    <input type="number" name="productos[<?php echo $i; ?>][cantidad]" 
                                           class="form-control input-cantidad" min="0" value="0">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="button-group" style="margin-top:20px;">
                    <button type="submit" class="btn-guardar" style="width:100%; height:60px; font-size:1.3rem;">
                        <i class="fas fa-check-double"></i> Registrar Venta
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const buscador = document.getElementById('buscador');
        const selectCliente = document.getElementById('cliente_id');
        const displayTotal = document.getElementById('granTotal');
        const displaySubtotal = document.getElementById('subtotalTxt');
        const displayDesc = document.getElementById('descTxt');
        const filas = document.querySelectorAll('.fila-producto');
        const inputs = document.querySelectorAll('.input-cantidad');

        function calcularVenta() {
            let subtotal = 0;
            // Obtener descuento del atributo data del select
            const descuentoPorcentaje = parseFloat(selectCliente.options[selectCliente.selectedIndex].getAttribute('data-descuento')) || 0;
            
            filas.forEach(fila => {
                const precio = parseFloat(fila.querySelector('.precio-unitario').innerText);
                const cantidad = parseInt(fila.querySelector('.input-cantidad').value) || 0;
                subtotal += precio * cantidad;
            });

            const descuentoMonto = subtotal * (descuentoPorcentaje / 100);
            const totalFinal = subtotal - descuentoMonto;

            // Actualizar vista
            displaySubtotal.innerText = '$' + subtotal.toFixed(2);
            displayDesc.innerText = descuentoPorcentaje + '%';
            displayTotal.innerText = '$' + totalFinal.toLocaleString('es-MX', {minimumFractionDigits: 2});
        }

        // Eventos
        inputs.forEach(input => input.addEventListener('input', calcularVenta));
        selectCliente.addEventListener('change', calcularVenta);

        // Buscador
        buscador.addEventListener('input', function() {
            const termino = this.value.toLowerCase();
            filas.forEach(fila => {
                const nombre = fila.querySelector('.nombre-producto').innerText.toLowerCase();
                fila.style.display = nombre.includes(termino) ? "" : "none";
            });
        });
    </script>
    <script src="../js/admin.js"></script>
</body>
</html>