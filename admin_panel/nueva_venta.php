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

        $stmt = $pdo->prepare("INSERT INTO pedidos (usuario_id, cliente_id, nombre, total, status, fecha_pedido) VALUES (?, ?, ?, 0, 'Confirmado', NOW())");
        $stmt->execute([$usuario_id, $cliente_id, $info_cliente['nombre_completo']]);
        $pedido_id = $pdo->lastInsertId();

        if(isset($_POST['productos'])) {
            foreach ($_POST['productos'] as $item) {
                $cant_vta = intval($item['cantidad']);
                if ($cant_vta > 0) {
                    $p_id = $item['id'];
                    
                    $st = $pdo->prepare("SELECT p.nombre, p.precio, f.stock_litros_disponibles as stock, f.id as id_formula 
                                         FROM productos p 
                                         LEFT JOIN formulas_maestras f ON p.id_formula_maestra = f.id 
                                         WHERE p.id = ?");
                    $st->execute([$p_id]);
                    $prod = $st->fetch();

                    if ($prod) {
                        if ($prod['stock'] < $cant_vta) {
                            $requiere_produccion_global = true;
                        }

                        $subtotal = $prod['precio'] * $cant_vta;
                        $total_antes_descuento += $subtotal;

                        $ins = $pdo->prepare("INSERT INTO detalle_pedido (pedido_id, producto_id, cantidad, producto_nombre, precio_unitario) VALUES (?, ?, ?, ?, ?)");
                        $ins->execute([$pedido_id, $p_id, $cant_vta, $prod['nombre'], $prod['precio']]);
                        
                        if ($prod['stock'] >= $cant_vta && $prod['id_formula']) {
                            $upd = $pdo->prepare("UPDATE formulas_maestras SET stock_litros_disponibles = stock_litros_disponibles - ? WHERE id = ?");
                            $upd->execute([$cant_vta, $prod['id_formula']]);
                        }
                    }
                }
            }
        }

        if ($total_antes_descuento > 0) {
            $total_final = $total_antes_descuento * (1 - $desc_cliente);
            $status_final = $requiere_produccion_global ? 'Pendiente Producción' : 'Confirmado';
            $pdo->prepare("UPDATE pedidos SET total = ?, status = ? WHERE id = ?")->execute([$total_final, $status_final, $pedido_id]);
            $pdo->commit();
            
            // VARIABLES PARA DISPARAR LA ALERTA
            $pedido_finalizado = true;
            $nuevo_id = $pedido_id;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// CONSULTA DE PRODUCTOS Y CLIENTES
$sql_prod = "SELECT p.id, p.nombre, p.precio, p.categoria, f.stock_litros_disponibles as stock 
             FROM productos p 
             LEFT JOIN formulas_maestras f ON p.id_formula_maestra = f.id 
             ORDER BY p.categoria, p.nombre ASC";
$productos = $pdo->query($sql_prod)->fetchAll();
$clientes = $pdo->query("SELECT c.id, c.nombre_completo, tc.descuento_porcentaje FROM clientes c INNER JOIN tipos_cliente tc ON c.tipo_cliente_id = tc.id WHERE c.estatus = 'Activo' ORDER BY c.nombre_completo ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>POS Móvil | AHD Clean</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        :root { --accent: #10b981; --dark: #1e293b; }
        body { margin: 0; padding: 0; background: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .header-mobile { display: none; position: fixed; top: 0; left: 0; right: 0; height: 60px; background: var(--dark); color: white; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 2000; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .pos-container { display: grid; grid-template-columns: 1fr 380px; gap: 20px; margin-top: 10px; }
        @media (max-width: 992px) {
            .header-mobile { display: flex; }
            .sidebar { position: fixed; left: -260px; top: 0; height: 100%; width: 260px; z-index: 3000; transition: 0.3s; background: var(--dark); }
            .sidebar.active { left: 0; }
            .main { margin-left: 0 !important; padding: 75px 15px 120px 15px !important; width: 100% !important; }
            .pos-container { grid-template-columns: 1fr; }
            .ticket-panel { position: fixed; bottom: 0; left: 0; right: 0; z-index: 1500; border-radius: 0; padding: 15px; background: #1e293b; box-shadow: 0 -5px 15px rgba(0,0,0,0.3); }
            .hide-mobile { display: none !important; }
            .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2500; }
            .overlay.active { display: block; }
        }
        .product-row { display: flex; align-items: center; background: white; padding: 15px; border-radius: 10px; margin-bottom: 8px; border: 1px solid #e2e8f0; transition: 0.2s; }
        .product-info { flex: 1; }
        .qty-input-pos { width: 75px; height: 45px; text-align: center; font-size: 1.1rem; border: 2px solid #cbd5e1; border-radius: 8px; font-weight: bold; }
        .stock-tag { font-size: 0.7rem; font-weight: bold; padding: 2px 6px; border-radius: 4px; margin-top: 5px; display: inline-block; }
        .st-ok { background: #dcfce7; color: #166534; }
        .st-low { background: #fee2e2; color: #b91c1c; }
        .btn-hamburger { background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; padding: 5px; }
        .search-pos { position: relative; margin-bottom: 15px; }
        .search-pos i { position: absolute; left: 15px; top: 15px; color: #94a3b8; }
        .search-pos input { width: 100%; padding: 12px 12px 12px 45px; border-radius: 10px; border: 1px solid #e2e8f0; font-size: 1rem; }
    </style>
</head>
<body>
    <div class="overlay" id="overlay" onclick="toggleMenu()"></div>

    <div class="header-mobile">
        <button class="btn-hamburger" onclick="toggleMenu()"><i class="fas fa-bars"></i></button>
        <span style="font-weight: 800; letter-spacing: 1px;">AHD CLEAN</span>
        <i class="fas fa-user-circle" style="font-size: 1.2rem;"></i>
    </div>

    <div id="mySidebar">
        <?php include 'sidebar.php'; ?>
    </div>

    <div class="main">
        <div class="header hide-mobile" style="margin-bottom:20px;">
            <h1><i class="fas fa-cash-register"></i> Punto de Venta Interno</h1>
        </div>

        <?php if(isset($error)): ?>
            <div style="background: #fee2e2; color: #b91c1c; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="formVenta">
            <div class="pos-container">
                <div class="catalog-panel">
                    <div class="search-pos">
                        <i class="fas fa-search"></i>
                        <input type="text" id="buscador" placeholder="Buscar producto...">
                    </div>
                    
                    <div class="product-grid">
                        <?php foreach ($productos as $i => $p): 
                            $st = (float)$p['stock'];
                        ?>
                        <div class="product-row" data-nombre="<?php echo strtolower($p['nombre']); ?>">
                            <div class="product-info">
                                <strong style="display:block;"><?php echo htmlspecialchars($p['nombre']); ?></strong>
                                <span class="stock-tag <?php echo ($st > 0) ? 'st-ok' : 'st-low'; ?>">
                                    Stock: <?php echo $st; ?>L
                                </span>
                            </div>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <span style="font-weight:bold; color:var(--accent);">$<?php echo number_format($p['precio'], 2); ?></span>
                                <input type="hidden" name="productos[<?php echo $i; ?>][id]" value="<?php echo $p['id']; ?>">
                                <input type="hidden" class="precio-unitario" value="<?php echo $p['precio']; ?>">
                                <input type="hidden" class="stock-actual" value="<?php echo $st; ?>">
                                <input type="number" name="productos[<?php echo $i; ?>][cantidad]" 
                                       class="qty-input-pos input-cantidad" min="0" value="0">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="ticket-panel">
                    <div id="alert-produccion" style="display:none; background:#fffbeb; color:#92400e; padding:10px; border-radius:8px; margin-bottom:10px; font-size:0.8rem; border:1px solid #fef3c7;">
                        <i class="fas fa-industry"></i> Requiere Orden de Producción
                    </div>

                    <div style="margin-bottom:15px;">
                        <label style="font-size:0.75rem; color:#94a3b8; font-weight:bold;">CLIENTE</label>
                        <select name="cliente_id" id="cliente_id" class="form-control" style="background:#334155; color:white; border:none; height:45px; width:100%; border-radius: 8px; padding: 0 10px;">
                            <?php foreach ($clientes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" data-descuento="<?php echo $c['descuento_porcentaje']; ?>">
                                    <?php echo htmlspecialchars($c['nombre_completo']); ?> (<?php echo (int)$c['descuento_porcentaje']; ?>%)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="summary-box" style="background: #0f172a; padding: 20px; border-radius: 15px;">
                        <div style="display:flex; justify-content:space-between; color:#4fd1c5; font-size:1.6rem; font-weight:900;">
                            <span>TOTAL</span>
                            <span id="granTotal">$0.00</span>
                        </div>
                        <button type="submit" class="btn-pay" style="width:100%; margin-top:15px; padding:18px; background:var(--accent); color:white; border:none; border-radius:12px; font-weight:bold; font-size:1.1rem; cursor:pointer;">
                            REGISTRAR VENTA
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        // --- NOTIFICACIÓN DE ÉXITO ---
        <?php if($pedido_finalizado): ?>
            Swal.fire({
                title: '¡Venta Exitosa!',
                text: 'El pedido #<?php echo $nuevo_id; ?> se ha generado correctamente.',
                icon: 'success',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#3b82f6',
                confirmButtonText: '<i class="fas fa-print"></i> Imprimir Ticket',
                cancelButtonText: '<i class="fas fa-plus"></i> Nueva Venta',
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.open('imprimir_ticket.php?id=<?php echo $nuevo_id; ?>', '_blank');
                    window.location.href = 'nueva_venta.php';
                } else {
                    window.location.href = 'nueva_venta.php';
                }
            });
        <?php endif; ?>

        function toggleMenu() {
            const sidebar = document.querySelector('.sidebar') || document.getElementById('mySidebar').firstElementChild;
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        const inputs = document.querySelectorAll('.input-cantidad');
        function calcularVenta() {
            let total = 0;
            let prod = false;
            const selectCliente = document.getElementById('cliente_id');
            const desc = parseFloat(selectCliente.options[selectCliente.selectedIndex].getAttribute('data-descuento')) || 0;
            
            document.querySelectorAll('.product-row').forEach(row => {
                const precio = parseFloat(row.querySelector('.precio-unitario').value);
                const stock = parseFloat(row.querySelector('.stock-actual').value);
                const cant = parseInt(row.querySelector('.input-cantidad').value) || 0;
                
                if(cant > 0) {
                    total += precio * cant;
                    if(cant > stock) prod = true;
                    row.style.borderColor = "var(--accent)";
                    row.style.background = "#f0fdf4";
                } else {
                    row.style.borderColor = "#e2e8f0";
                    row.style.background = "white";
                }
            });

            document.getElementById('alert-produccion').style.display = prod ? 'block' : 'none';
            const final = total * (1 - (desc / 100));
            document.getElementById('granTotal').innerText = '$' + final.toLocaleString('es-MX', {minimumFractionDigits:2});
        }

        document.getElementById('buscador').addEventListener('input', function() {
            const t = this.value.toLowerCase();
            document.querySelectorAll('.product-row').forEach(r => {
                r.style.display = r.getAttribute('data-nombre').includes(t) ? "flex" : "none";
            });
        });

        inputs.forEach(i => i.addEventListener('input', calcularVenta));
        document.getElementById('cliente_id').addEventListener('change', calcularVenta);
        
        // Inicializar cálculo por defecto
        calcularVenta();
    </script>
</body>
</html>