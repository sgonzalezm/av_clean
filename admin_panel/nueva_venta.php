<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// --- 1. PROCESAMIENTO DEL PEDIDO (Lógica de Registro) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cliente_id'])) {
    try {
        $pdo->beginTransaction();

        $usuario_id = $_SESSION['admin_id']; 
        $cliente_id = $_POST['cliente_id'];
        $total_antes_descuento = 0;

        // Obtener datos del cliente para el pedido
        $stmt_c = $pdo->prepare("SELECT c.nombre_completo, c.email, c.telefono, c.direccion, tc.descuento_porcentaje 
                                 FROM clientes c 
                                 INNER JOIN tipos_cliente tc ON c.tipo_cliente_id = tc.id 
                                 WHERE c.id = ?");
        $stmt_c->execute([$cliente_id]);
        $info_cliente = $stmt_c->fetch();

        if (!$info_cliente) throw new Exception("Cliente no encontrado.");

        $desc_cliente = $info_cliente['descuento_porcentaje'] / 100;

        // Crear pedido base (Total temporal en 0)
        $stmt = $pdo->prepare("INSERT INTO pedidos (usuario_id, cliente_id, nombre, email, telefono, domicilio, total, status, fecha_pedido) VALUES (?, ?, ?, ?, ?, ?, 0, 'Confirmado', NOW())");
        $stmt->execute([$usuario_id, $cliente_id, $info_cliente['nombre_completo'], $info_cliente['email'], $info_cliente['telefono'], $info_cliente['direccion']]);
        $pedido_id = $pdo->lastInsertId();

        // Registrar productos seleccionados
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
            
            // --- INTEGRACIÓN DE IMPRESIÓN ---
            // Redirigimos al archivo del ticket pasando el ID recién creado
            header("Location: imprimir_ticket.php?id=" . $pedido_id);
            exit;
        } else {
            throw new Exception("Debe seleccionar al menos un producto con cantidad mayor a cero.");
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error al registrar la venta: " . $e->getMessage();
    }
}

// --- 2. CONSULTAS PARA LA VISTA ---
$productos = $pdo->query("SELECT id, nombre, precio, categoria FROM productos ORDER BY categoria, nombre ASC")->fetchAll();

$sql_clientes = "SELECT c.id, c.nombre_completo, tc.nombre as tipo, tc.descuento_porcentaje 
                 FROM clientes c 
                 INNER JOIN tipos_cliente tc ON c.tipo_cliente_id = tc.id 
                 WHERE c.estatus = 'Activo' ORDER BY c.nombre_completo ASC";
$clientes = $pdo->query($sql_clientes)->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>POS | AHD Clean</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .pos-container { display: grid; grid-template-columns: 1fr 380px; gap: 20px; height: 75vh; }
        .catalog-panel { background: white; border-radius: 12px; padding: 20px; display: flex; flex-direction: column; overflow: hidden; border: 1px solid #e2e8f0; }
        .product-grid { overflow-y: auto; display: flex; flex-direction: column; gap: 5px; }
        .ticket-panel { background: #1e293b; color: white; border-radius: 12px; padding: 20px; display: flex; flex-direction: column; }
        .product-row { display: flex; align-items: center; justify-content: space-between; padding: 10px; border-bottom: 1px solid #f1f5f9; }
        .qty-input-pos { width: 70px; text-align: center; border: 1px solid #cbd5e1; border-radius: 5px; padding: 5px; font-weight: bold; }
        .summary-box { background: #0f172a; border-radius: 10px; padding: 15px; margin-top: auto; }
        .total-line { display: flex; justify-content: space-between; font-size: 1.6rem; font-weight: 800; color: #4fd1c5; border-top: 1px solid #334155; padding-top: 10px; margin-top: 10px; }
        .btn-pay { width: 100%; background: #10b981; color: white; border: none; padding: 15px; border-radius: 8px; font-size: 1.2rem; font-weight: bold; cursor: pointer; margin-top: 15px; transition: 0.3s; }
        .btn-pay:hover { background: #059669; }
        .search-pos { position: relative; margin-bottom: 15px; }
        .search-pos i { position: absolute; left: 15px; top: 12px; color: #94a3b8; }
        .search-pos input { width: 100%; padding: 10px 10px 10px 40px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header" style="margin-bottom:20px;">
            <h1><i class="fas fa-cash-register"></i> Punto de Venta Interno</h1>
            <div class="user-badge"><i class="fas fa-user"></i> <?php echo $_SESSION['admin_nombre']; ?></div>
        </div>

        <?php if(isset($error)): ?>
            <div style="background:#fee2e2; color:#b91c1c; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #fecaca;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="formVenta">
            <div class="pos-container">
                <div class="catalog-panel">
                    <div class="search-pos">
                        <i class="fas fa-search"></i>
                        <input type="text" id="buscador" placeholder="Buscar producto por nombre...">
                    </div>
                    
                    <div class="product-grid" id="listaProductos">
                        <?php foreach ($productos as $i => $p): ?>
                        <div class="product-row" data-nombre="<?php echo strtolower($p['nombre']); ?>">
                            <div>
                                <strong style="display:block;"><?php echo htmlspecialchars($p['nombre']); ?></strong>
                                <small style="color:#64748b; text-transform:uppercase; font-size:0.7rem;"><?php echo $p['categoria']; ?></small>
                            </div>
                            <div style="display:flex; align-items:center; gap:15px;">
                                <span style="font-weight:bold; color:#059669;">$<?php echo number_format($p['precio'], 2); ?></span>
                                <input type="hidden" name="productos[<?php echo $i; ?>][id]" value="<?php echo $p['id']; ?>">
                                <input type="hidden" class="precio-unitario" value="<?php echo $p['precio']; ?>">
                                <input type="number" name="productos[<?php echo $i; ?>][cantidad]" 
                                       class="qty-input-pos input-cantidad" min="0" value="0">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="ticket-panel">
                    <div style="margin-bottom:20px;">
                        <label style="font-size:0.8rem; color:#94a3b8; font-weight:bold;">CLIENTE / DESCUENTO</label>
                        <select name="cliente_id" id="cliente_id" class="form-control" required style="background:#334155; color:white; border:none; margin-top:8px; height:45px;">
                            <?php foreach ($clientes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" data-descuento="<?php echo $c['descuento_porcentaje']; ?>">
                                    <?php echo htmlspecialchars($c['nombre_completo']); ?> (<?php echo (int)$c['descuento_porcentaje']; ?>%)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="flex-grow:1; border-top:1px solid #334155; padding-top:15px;">
                        <small style="color:#64748b;">La venta se registrará e inmediatamente se abrirá el ticket para imprimir.</small>
                    </div>

                    <div class="summary-box">
                        <div style="display:flex; justify-content:space-between; margin-bottom:5px; font-size:0.9rem; color:#94a3b8;">
                            <span>Subtotal</span>
                            <span id="subtotalTxt">$0.00</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:5px; font-size:0.9rem; color:#94a3b8;">
                            <span>Descuento aplicado</span>
                            <span id="descTxt">0%</span>
                        </div>
                        <div class="total-line">
                            <span>TOTAL</span>
                            <span id="granTotal">$0.00</span>
                        </div>
                    </div>

                    <button type="submit" class="btn-pay">
                        <i class="fas fa-print"></i> REGISTRAR E IMPRIMIR
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        const buscador = document.getElementById('buscador');
        const selectCliente = document.getElementById('cliente_id');
        const displayTotal = document.getElementById('granTotal');
        const displaySubtotal = document.getElementById('subtotalTxt');
        const displayDesc = document.getElementById('descTxt');
        const rows = document.querySelectorAll('.product-row');
        const inputs = document.querySelectorAll('.input-cantidad');

        function calcularVenta() {
            let subtotal = 0;
            const descPerc = parseFloat(selectCliente.options[selectCliente.selectedIndex].getAttribute('data-descuento')) || 0;
            
            rows.forEach(row => {
                const precio = parseFloat(row.querySelector('.precio-unitario').value);
                const cantidad = parseInt(row.querySelector('.input-cantidad').value) || 0;
                
                if(cantidad > 0) {
                    subtotal += precio * cantidad;
                    row.style.background = "#f0fdf4"; 
                } else {
                    row.style.background = "";
                }
            });

            const totalFinal = subtotal * (1 - (descPerc / 100));

            displaySubtotal.innerText = '$' + subtotal.toFixed(2);
            displayDesc.innerText = descPerc + '%';
            displayTotal.innerText = '$' + totalFinal.toLocaleString('es-MX', {minimumFractionDigits: 2});
        }

        buscador.addEventListener('input', function() {
            const term = this.value.toLowerCase();
            rows.forEach(row => {
                const nombre = row.getAttribute('data-nombre');
                row.style.display = nombre.includes(term) ? "flex" : "none";
            });
        });

        inputs.forEach(input => input.addEventListener('input', calcularVenta));
        selectCliente.addEventListener('change', calcularVenta);
        
        calcularVenta();
    </script>
</body>
</html>