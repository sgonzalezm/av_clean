<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$pedido_finalizado = false;
$nuevo_id = 0;

// --- 1. PROCESAMIENTO DEL PEDIDO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cliente_id'])) {
    try {
        $pdo->beginTransaction();

        $usuario_id = $_SESSION['admin_id']; 
        $cliente_id = $_POST['cliente_id'];
        $metodo_pago = $_POST['metodo_pago']; 
        $total_antes_descuento = 0;
        $requiere_produccion_global = false;

        $stmt_c = $pdo->prepare("SELECT c.nombre_completo, tc.descuento_porcentaje 
                                 FROM clientes c 
                                 INNER JOIN tipos_cliente tc ON c.tipo_cliente_id = tc.id 
                                 WHERE c.id = ?");
        $stmt_c->execute([$cliente_id]);
        $info_cliente = $stmt_c->fetch();

        if (!$info_cliente) throw new Exception("Cliente no encontrado.");
        $desc_cliente = $info_cliente['descuento_porcentaje'] / 100;

        $status_pago = ($metodo_pago == 'Contado') ? 'Pagado' : (($metodo_pago == 'Credito') ? 'Crédito' : 'Pendiente');
        $fecha_vencimiento = ($metodo_pago == 'Credito') ? date('Y-m-d', strtotime('+30 days')) : null;

        $stmt = $pdo->prepare("INSERT INTO pedidos (usuario_id, cliente_id, nombre, total, status_pago, status_logistica, fecha_vencimiento_pago, fecha_pedido) VALUES (?, ?, ?, 0, ?, 'Por Surtir', ?, NOW())");
        $stmt->execute([$usuario_id, $cliente_id, $info_cliente['nombre_completo'], $status_pago, $fecha_vencimiento]);
        $pedido_id = $pdo->lastInsertId();

        if(isset($_POST['productos'])) {
            foreach ($_POST['productos'] as $item) {
                $cant_vta = intval($item['cantidad']);
                if ($cant_vta > 0) {
                    $p_id = $item['id'];
                    $st = $pdo->prepare("SELECT p.nombre, p.precio, f.stock_litros_disponibles as stock 
                                         FROM productos p 
                                         LEFT JOIN formulas_maestras f ON p.id_formula_maestra = f.id 
                                         WHERE p.id = ?");
                    $st->execute([$p_id]);
                    $prod = $st->fetch();

                    if ($prod) {
                        if ($prod['stock'] < $cant_vta) $requiere_produccion_global = true;
                        
                        $subtotal = $prod['precio'] * $cant_vta;
                        $total_antes_descuento += $subtotal;

                        $ins = $pdo->prepare("INSERT INTO detalle_pedido (pedido_id, producto_id, cantidad, producto_nombre, precio_unitario) VALUES (?, ?, ?, ?, ?)");
                        $ins->execute([$pedido_id, $p_id, $cant_vta, $prod['nombre'], $prod['precio']]);
                    }
                }
            }
        }

        if ($total_antes_descuento > 0) {
            $total_final = $total_antes_descuento * (1 - $desc_cliente);
            $obs = $requiere_produccion_global ? "⚠️ Requiere fabricar líquido." : "✅ Listo para surtir.";
            $pdo->prepare("UPDATE pedidos SET total = ?, observaciones = ? WHERE id = ?")->execute([$total_final, $obs, $pedido_id]);
            
            $pdo->commit();
            $pedido_finalizado = true;
            $nuevo_id = $pedido_id;
        } else {
            throw new Exception("Debes agregar al menos un producto.");
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// --- 2. CONSULTAS DE DATOS ---
$productos = $pdo->query("SELECT p.id, p.nombre, p.precio, p.categoria, f.stock_litros_disponibles as stock 
                          FROM productos p 
                          LEFT JOIN formulas_maestras f ON p.id_formula_maestra = f.id 
                          ORDER BY p.categoria, p.nombre ASC")->fetchAll();

$clientes = $pdo->query("SELECT c.id, c.nombre_completo, tc.descuento_porcentaje FROM clientes c 
                         INNER JOIN tipos_cliente tc ON c.tipo_cliente_id = tc.id 
                         WHERE c.estatus = 'Activo' ORDER BY c.nombre_completo ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>POS | AHD Clean</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        :root { --accent: #10b981; --dark: #1e293b; --bg: #f8fafc; }
        body { margin: 0; background: var(--bg); font-family: sans-serif; overflow-x: hidden; }
        
        /* Ajuste de Header Mobile */
        .header-mobile { display: none; position: fixed; top: 0; left: 0; right: 0; height: 60px; background: var(--dark); color: white; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 2000; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
        
        .pos-container { display: grid; grid-template-columns: 1fr 400px; gap: 20px; padding: 20px; }
        
        @media (max-width: 992px) {
            .header-mobile { display: flex; }
            .pos-container { grid-template-columns: 1fr; padding-bottom: 230px; padding-top: 10px; }
            .main { margin-left: 0 !important; padding: 75px 15px 120px 15px !important; }
            
            /* Ticket panel compacto para no bloquear todo el viewport */
            .ticket-panel { position: fixed; bottom: 0; left: 0; right: 0; z-index: 1000 !important; border-radius: 0; padding: 15px !important; }
            .ticket-panel h3 { font-size: 0.9rem; margin-bottom: 8px !important; }
            .ticket-panel #granTotal { font-size: 1.8rem !important; margin: 5px 0 !important; }
            .ticket-panel button { padding: 12px !important; font-size: 1rem !important; margin-top: 10px !important; }
            
            /* Sidebar PRIORIDAD MÁXIMA */
            .sidebar { z-index: 3000 !important; }
            .overlay { z-index: 2500 !important; }
            
            .hide-mobile { display: none !important; }
            
            /* Ajuste de inputs para dedos */
            .form-control-pos { height: 40px !important; font-size: 0.85rem !important; }
            .qty-input-pos { width: 65px !important; height: 40px !important; font-size: 1rem !important; }
        }

        .product-row { display: flex; align-items: center; background: white; padding: 15px; border-radius: 12px; margin-bottom: 10px; border: 1px solid #e2e8f0; }
        .qty-input-pos { width: 75px; height: 45px; text-align: center; font-size: 1.1rem; border: 2px solid #cbd5e1; border-radius: 10px; font-weight: 800; }
        
        .ticket-panel { background: #1e293b; color: white; padding: 25px; border-radius: 15px; box-shadow: 0 -5px 25px rgba(0,0,0,0.2); }
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); }
        .overlay.active { display: block; }
        
        .search-pos { position: relative; margin-bottom: 20px; }
        .search-pos i { position: absolute; left: 15px; top: 18px; color: #94a3b8; }
        .search-pos input { width: 100%; padding: 15px 15px 15px 45px; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 1rem; box-sizing: border-box; }
        
        .form-control-pos { width: 100%; height: 45px; border-radius: 10px; margin-top: 5px; background: white; border: 1px solid #cbd5e1; font-size: 0.9rem; padding: 0 10px; color: #1e293b; }
    </style>
</head>
<body>
    <div class="overlay" id="overlay" onclick="toggleMenu()"></div>

    <div class="header-mobile">
        <button onclick="toggleMenu()" style="background:none; border:none; color:white; font-size:1.5rem; cursor:pointer;"><i class="fas fa-bars"></i></button>
        <span style="font-weight: 900; letter-spacing: 1px;">AHD CLEAN POS</span>
        <i class="fas fa-cash-register"></i>
    </div>

    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header hide-mobile" style="margin-bottom:20px;">
            <h1><i class="fas fa-shopping-cart"></i> Punto de Venta Interno</h1>
        </div>

        <form method="POST" id="formVenta">
            <div class="pos-container">
                <div class="catalog-panel">
                    <div class="search-pos">
                        <i class="fas fa-search"></i>
                        <input type="text" id="buscador" placeholder="Buscar producto...">
                    </div>
                    
                    <div class="product-grid">
                        <?php foreach ($productos as $i => $p): ?>
                        <div class="product-row" data-nombre="<?php echo strtolower($p['nombre']); ?>">
                            <div style="flex:1;">
                                <strong style="font-size:1rem; display:block;"><?php echo htmlspecialchars($p['nombre']); ?></strong>
                                <span style="font-size:0.75rem; color:#64748b; font-weight:600;">Stock: <?php echo number_format($p['stock'], 1); ?>L</span>
                            </div>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <span style="font-weight:900; color:var(--accent); font-size:0.9rem;">$<?php echo number_format($p['precio'], 2); ?></span>
                                <input type="hidden" name="productos[<?php echo $i; ?>][id]" value="<?php echo $p['id']; ?>">
                                <input type="hidden" class="precio-unitario" value="<?php echo $p['precio']; ?>">
                                <input type="number" name="productos[<?php echo $i; ?>][cantidad]" class="qty-input-pos input-cantidad" placeholder="0" inputmode="numeric">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="ticket-panel">
                    <h3 style="margin:0; color:#4fd1c5;"><i class="fas fa-receipt"></i> Resumen de Pedido</h3>
                    
                    <div style="margin-top:10px;">
                        <label style="font-size:0.7rem; font-weight:bold; opacity:0.8;">CLIENTE</label>
                        <select name="cliente_id" id="cliente_id" class="form-control-pos">
                            <?php foreach ($clientes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" data-descuento="<?php echo $c['descuento_porcentaje']; ?>">
                                    <?php echo htmlspecialchars($c['nombre_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="margin-top:10px;">
                        <label style="font-size:0.7rem; font-weight:bold; opacity:0.8;">TIPO DE PAGO</label>
                        <select name="metodo_pago" class="form-control-pos">
                            <option value="Contado">Pago Inmediato</option>
                            <option value="Pendiente">Contra entrega</option>
                            <option value="Credito">Crédito (30 días)</option>
                        </select>
                    </div>

                    <div style="background:#0f172a; padding:15px; border-radius:12px; text-align:center; border: 1px solid #334155; margin-top:15px;">
                        <div id="granTotal" style="font-size:2.2rem; font-weight:900; color:#4fd1c5;">$0.00</div>
                        <button type="submit" style="width:100%; padding:15px; background:var(--accent); color:white; border:none; border-radius:10px; font-weight:bold; font-size:1.1rem; cursor:pointer;">
                            GENERAR VENTA
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        <?php if($pedido_finalizado): ?>
            Swal.fire({
                title: '¡Venta Exitosa!',
                text: 'Pedido #<?php echo $nuevo_id; ?> registrado.',
                icon: 'success',
                confirmButtonColor: '#10b981'
            }).then(() => {
                window.open('imprimir_ticket.php?id=<?php echo $nuevo_id; ?>', '_blank');
                window.location.href = 'nueva_venta.php';
            });
        <?php endif; ?>

        function toggleMenu() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('overlay');
            if (sidebar) sidebar.classList.toggle('active');
            if (overlay) overlay.classList.toggle('active');
        }

        const inputs = document.querySelectorAll('.input-cantidad');
        const selectCliente = document.getElementById('cliente_id');

        function calcular() {
            let total = 0;
            const desc = parseFloat(selectCliente.options[selectCliente.selectedIndex].getAttribute('data-descuento')) || 0;

            document.querySelectorAll('.product-row').forEach(row => {
                const precio = parseFloat(row.querySelector('.precio-unitario').value);
                const cant = parseInt(row.querySelector('.input-cantidad').value) || 0;
                if (cant > 0) total += precio * cant;
            });

            const final = total * (1 - (desc / 100));
            document.getElementById('granTotal').innerText = '$' + final.toLocaleString('es-MX', {minimumFractionDigits:2});
        }

        document.getElementById('buscador').addEventListener('input', function() {
            const term = this.value.toLowerCase();
            document.querySelectorAll('.product-row').forEach(row => {
                row.style.display = row.getAttribute('data-nombre').includes(term) ? "flex" : "none";
            });
        });

        inputs.forEach(i => i.addEventListener('input', calcular));
        selectCliente.addEventListener('change', calcular);
    </script>
</body>
</html>