<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// 1. Obtener productos para el catálogo
$productos = $pdo->query("SELECT id, nombre, precio FROM productos ORDER BY nombre ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();

        $usuario_id = $_SESSION['admin_id']; 
        $cliente_ref = !empty($_POST['cliente_email']) ? $_POST['cliente_email'] : 'Venta General';
        $total_acumulado = 0;

        // 2. Crear pedido base
        $stmt = $pdo->prepare("INSERT INTO pedidos (usuario_id, email, total, status, fecha_pedido) VALUES (?, ?, 0, 'Confirmado', NOW())");
        $stmt->execute([$usuario_id, $cliente_ref]);
        $pedido_id = $pdo->lastInsertId();

        // 3. Procesar productos
        foreach ($_POST['productos'] as $item) {
            $cantidad = intval($item['cantidad']);
            if ($cantidad > 0) {
                $p_id = $item['id'];
                
                $p_info = $pdo->prepare("SELECT nombre, precio FROM productos WHERE id = ?");
                $p_info->execute([$p_id]);
                $prod = $p_info->fetch();

                if ($prod) {
                    $subtotal = $prod['precio'] * $cantidad;
                    $total_acumulado += $subtotal;

                    $ins = $pdo->prepare("INSERT INTO detalle_pedido (pedido_id, producto_id, cantidad, producto_nombre, precio_unitario) VALUES (?, ?, ?, ?, ?)");
                    $ins->execute([$pedido_id, $p_id, $cantidad, $prod['nombre'], $prod['precio']]);
                }
            }
        }

        // 4. Finalizar
        if ($total_acumulado > 0) {
            $pdo->prepare("UPDATE pedidos SET total = ? WHERE id = ?")->execute([$total_acumulado, $pedido_id]);
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
            <form method="POST" class="slide-in" id="formVenta">
                <div class="form-group">
                    <label>Referencia del Cliente</label>
                    <input type="text" name="cliente_email" class="form-control" placeholder="Nombre o Correo">
                </div>

                <div class="tabla-contenedor" style="margin-top:20px; border:1px solid #eee; border-radius:10px;">
                    <table style="width:100%; border-collapse: collapse;">
                        <thead style="background:#f9f9f9;">
                            <tr>
                                <th style="padding:15px; text-align:left;">Producto</th>
                                <th style="padding:15px; text-align:right;">Precio</th>
                                <th style="padding:15px; width:120px;">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $i => $p): ?>
                            <tr class="fila-producto" style="border-top:1px solid #eee;">
                                <td style="padding:15px;">
                                    <strong><?php echo htmlspecialchars($p['nombre']); ?></strong>
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

                <div class="total-bar" style="background:#2d3748; color:white; padding:20px; margin-top:20px; border-radius:10px; display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:1.2rem; font-weight:bold;">Total a Cobrar:</span>
                    <span id="granTotal" style="font-size:2rem; font-weight:900;">$0.00</span>
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
        // Lógica para calcular el total dinámicamente
        const inputs = document.querySelectorAll('.input-cantidad');
        const displayTotal = document.getElementById('granTotal');

        inputs.forEach(input => {
            input.addEventListener('input', () => {
                let totalVenta = 0;
                document.querySelectorAll('.fila-producto').forEach(fila => {
                    const precio = parseFloat(fila.querySelector('.precio-unitario').innerText);
                    const cantidad = parseInt(fila.querySelector('.input-cantidad').value) || 0;
                    totalVenta += precio * cantidad;
                });
                displayTotal.innerText = '$' + totalVenta.toLocaleString('es-MX', {minimumFractionDigits: 2});
            });
        });

        function toggleSidebar() { document.querySelector('.sidebar').classList.toggle('active'); }
    </script>
</body>
</html>