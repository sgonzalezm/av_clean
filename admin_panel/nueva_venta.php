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
        $descuento_manual = floatval($_POST['descuento_manual'] ?? 0);
        $total_antes_descuento = 0;
        $requiere_produccion_global = false;

        $stmt_c = $pdo->prepare("SELECT c.nombre_completo, tc.descuento_porcentaje 
                                 FROM clientes c 
                                 INNER JOIN tipos_cliente tc ON c.tipo_cliente_id = tc.id 
                                 WHERE c.id = ?");
        $stmt_c->execute([$cliente_id]);
        $info_cliente = $stmt_c->fetch();

        if (!$info_cliente) throw new Exception("Cliente no encontrado.");
        
        $porcentaje_total_desc = ($info_cliente['descuento_porcentaje'] + $descuento_manual) / 100;

        $status_pago = ($metodo_pago == 'Contado') ? 'Pagado' : (($metodo_pago == 'Credito') ? 'Crédito' : 'Pendiente');
        $fecha_vencimiento = ($metodo_pago == 'Credito') ? date('Y-m-d', strtotime('+30 days')) : null;

        $stmt = $pdo->prepare("INSERT INTO pedidos (usuario_id, cliente_id, nombre, total, status_pago, status_logistica, fecha_vencimiento_pago, fecha_pedido) VALUES (?, ?, ?, 0, ?, 'Por Surtir', ?, NOW())");
        $stmt->execute([$usuario_id, $cliente_id, $info_cliente['nombre_completo'], $status_pago, $fecha_vencimiento]);
        $pedido_id = $pdo->lastInsertId();

        if(isset($_POST['productos'])) {
            foreach ($_POST['productos'] as $item) {
                // CAMBIO CLAVE: Usamos floatval en lugar de intval para aceptar decimales (ej. 0.700)
                $cant_vta = floatval($item['cantidad']);
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
                        $total_antes_descuento += $prod['precio'] * $cant_vta;
                        $ins = $pdo->prepare("INSERT INTO detalle_pedido (pedido_id, producto_id, cantidad, producto_nombre, precio_unitario) VALUES (?, ?, ?, ?, ?)");
                        $ins->execute([$pedido_id, $p_id, $cant_vta, $prod['nombre'], $prod['precio']]);
                    }
                }
            }
        }

        if ($total_antes_descuento > 0) {
            $total_final = $total_antes_descuento * (1 - $porcentaje_total_desc);
            $monto_pagado_inicial = ($metodo_pago == 'Contado') ? $total_final : 0;
            $obs = $requiere_produccion_global ? "⚠️ Requiere fabricar líquido." : "✅ Listo para surtir.";
            $pdo->prepare("UPDATE pedidos SET total = ?, monto_pagado = ?, observaciones = ? WHERE id = ?")->execute([$total_final, $monto_pagado_inicial, $obs, $pedido_id]);
            $pdo->commit();
            $pedido_finalizado = true;
            $nuevo_id = $pedido_id;
        } else {
            throw new Exception("Agrega productos.");
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// --- 2. CONSULTAS ---
$productos = $pdo->query("SELECT p.id, p.nombre, p.precio, f.stock_litros_disponibles as stock FROM productos p LEFT JOIN formulas_maestras f ON p.id_formula_maestra = f.id ORDER BY p.nombre ASC")->fetchAll();
$clientes = $pdo->query("SELECT c.id, c.nombre_completo, tc.descuento_porcentaje FROM clientes c INNER JOIN tipos_cliente tc ON c.tipo_cliente_id = tc.id WHERE c.estatus = 'Activo' ORDER BY c.nombre_completo ASC")->fetchAll();
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
        body { margin: 0; background: var(--bg); font-family: sans-serif; }
        
        /* Header Móvil */
        .header-mobile { display: none; position: fixed; top: 0; left: 0; right: 0; height: 60px; background: var(--dark); color: white; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 2000; box-shadow: 0 2px 10px rgba(0,0,0,0.2); }

        /* Contenedor Principal */
        .pos-container { display: grid; grid-template-columns: 1fr 380px; gap: 20px; padding: 20px; }

        /* Estilo para la lista de productos seleccionados */
        .resumen-carrito { max-height: 300px; overflow-y: auto; margin-bottom: 15px; border: 1px solid #e2e8f0; border-radius: 8px; background: white; }
        .item-lista { display: flex; justify-content: space-between; padding: 8px; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem; color: #334155; }

        .btn-ver-resumen { display: none; background: #3b82f6; color: white; padding: 10px; border-radius: 8px; text-align: center; margin-bottom: 10px; cursor: pointer; }
        /* Responsive Mobile */
        @media (max-width: 992px) {
            .header-mobile { display: flex; }
            .sidebar { position: fixed; left: -280px; top: 0; height: 100%; z-index: 3000; transition: 0.3s; }
            .sidebar.active { left: 0; }
            .main { margin-left: 0 !important; padding: 75px 15px 220px 15px !important; }
            .pos-container { grid-template-columns: 1fr; padding: 0; }
            
            .ticket-panel { 
                position: fixed; bottom: 0; left: 0; right: 0; 
                z-index: 1000 !important; border-radius: 20px 20px 0 0; 
                padding: 15px !important; box-shadow: 0 -10px 30px rgba(0,0,0,0.3);
            }
            .mobile-config-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px; }
            .hide-mobile { display: none !important; }

            .btn-ver-resumen { display: block; }
        }

        .product-row { display: flex; align-items: center; background: white; padding: 12px; border-radius: 12px; margin-bottom: 8px; border: 1px solid #e2e8f0; }
        .qty-input-pos { width: 65px; height: 38px; text-align: center; border: 2px solid #cbd5e1; border-radius: 8px; font-weight: 800; }
        
        /* Estilo del Panel de Cobro */
        .ticket-panel { background: #1e293b; color: white; padding: 20px; border-radius: 15px; }
        .form-control-pos { width: 100%; height: 38px; border-radius: 8px; margin-top: 4px; border: 1px solid #334155; padding: 0 8px; font-size: 0.85rem; }
        
        .search-pos { position: relative; margin-bottom: 15px; }
        .search-pos i { position: absolute; left: 15px; top: 13px; color: #94a3b8; }
        .search-pos input { width: 100%; padding: 10px 10px 10px 40px; border-radius: 10px; border: 1px solid #e2e8f0; box-sizing: border-box; }

        /* Ajuste de Descuento Manual */
        .discount-badge { background: #0f172a; border: 1px solid #4fd1c5; display: flex; align-items: center; border-radius: 8px; padding: 2px 5px; margin-top: 4px; }
        .input-descuento-manual { background: transparent; border: none; color: #4fd1c5; font-weight: bold; text-align: center; width: 40px; outline: none; font-size: 1rem; }
        
        .desglose-ticket { font-size: 0.8rem; margin-top: 10px; border-top: 1px solid #334155; padding-top: 10px; }
        .desglose-item { display: flex; justify-content: space-between; margin-bottom: 4px; opacity: 0.8; }
        .btn-cobrar { background: var(--accent); color: white; border: none; width: 100%; height: 48px; border-radius: 12px; font-weight: bold; margin-top: 10px; cursor: pointer; font-size: 1rem; }
        
        /* Overlay para cerrar el sidebar */
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2500; }
        .overlay.active { display: block; }
    </style>
</head>
<body>
    <div class="overlay" id="overlay" onclick="toggleMenu()"></div>

    <div class="header-mobile">
        <button onclick="toggleMenu()" style="background:none; border:none; color:white; font-size:1.5rem;"><i class="fas fa-bars"></i></button>
        <span style="font-weight: 900;">AHD CLEAN POS</span>
        <i class="fas fa-cash-register"></i>
    </div>

    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header hide-mobile">
            <h1><i class="fas fa-shopping-cart"></i> Punto de Venta</h1>
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
                                <strong style="font-size:0.9rem; display:block;"><?php echo htmlspecialchars($p['nombre']); ?></strong>
                                <span style="font-size:0.7rem; color:#64748b;">Stock: <?php echo number_format($p['stock'], 3); ?>L</span>
                            </div>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span style="font-weight:bold; color:var(--accent); font-size:0.85rem;">$<?php echo number_format($p['precio'], 2); ?></span>
                                <input type="hidden" name="productos[<?php echo $i; ?>][id]" value="<?php echo $p['id']; ?>">
                                <input type="hidden" class="precio-unitario" value="<?php echo $p['precio']; ?>">
                                <input type="number" name="productos[<?php echo $i; ?>][cantidad]" class="qty-input-pos input-cantidad" placeholder="0" step="any" inputmode="decimal">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="ticket-panel">
                    <h3 class="hide-mobile" style="margin: 0 0 15px 0;">Resumen</h3>
                    <div class="mobile-config-grid">
                        <div>
                            <label style="font-size: 0.65rem; color: #94a3b8; text-transform: uppercase;">Cliente</label>
                            <select name="cliente_id" id="cliente_id" class="form-control-pos">
                                <?php foreach($clientes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" data-descuento="<?php echo $c['descuento_porcentaje']; ?>"><?php echo $c['nombre_completo']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 0.65rem; color: #94a3b8; text-transform: uppercase;">Desc. Manual %</label>
                            <div class="discount-badge">
                                <i class="fas fa-tag" style="font-size: 0.7rem; margin-right: 5px; color: #4fd1c5;"></i>
                                <input type="number" name="descuento_manual" id="descuento_manual" class="input-descuento-manual" value="0" min="0" max="100">
                                <span style="color:#4fd1c5; font-weight: bold;">%</span>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 10px;">
                        <label style="font-size: 0.65rem; color: #94a3b8; text-transform: uppercase;">Método de Pago</label>
                        <select name="metodo_pago" class="form-control-pos">
                            <option value="Contado">Efectivo / Contado</option>
                            <option value="Transferencia">Transferencia</option>
                            <option value="Credito">Crédito (30 días)</option>
                        </select>
                    </div>

                    <div class="btn-ver-resumen" onclick="mostrarResumen()">🛒 Ver productos agregados</div>

                    <div id="contenedor-resumen" class="hide-mobile">
                        <div class="resumen-carrito" id="lista-carrito">
                            <p style="padding:10px; color:#94a3b8; text-align:center;">No hay productos agregados</p>
                        </div>
                    </div>

                    <div class="desglose-ticket">
                        <div class="desglose-item"><span>Subtotal:</span> <span id="sub_view">$0.00</span></div>
                        <div class="desglose-item" style="color:#4fd1c5;"><span>Total Ahorro:</span> <span id="ahorro_view">-$0.00</span></div>
                        <div class="desglose-item" style="font-size: 1.2rem; font-weight: bold; margin-top: 5px; border-top: 1px solid #475569; padding-top: 8px;">
                            <span>TOTAL:</span> <span id="total_view" style="color:var(--accent);">$0.00</span>
                        </div>
                    </div>

                    <button type="submit" class="btn-cobrar">FINALIZAR VENTA</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        function toggleMenu() {
            const sb = document.querySelector('.sidebar');
            const ov = document.getElementById('overlay');
            sb.classList.toggle('active');
            ov.classList.toggle('active');
        }

        const inputs = document.querySelectorAll('.input-cantidad');
        const selCli = document.getElementById('cliente_id');
        const inDesc = document.getElementById('descuento_manual');

        function calcular() {
            let sub = 0;
            const descCli = parseFloat(selCli.options[selCli.selectedIndex].getAttribute('data-descuento')) || 0;
            const descMan = parseFloat(inDesc.value) || 0;

            document.querySelectorAll('.product-row').forEach(row => {
                const p = parseFloat(row.querySelector('.precio-unitario').value);
                // CAMBIO CLAVE: Usamos parseFloat en lugar de parseInt para admitir parcialidades en el cálculo en vivo
                const c = parseFloat(row.querySelector('.input-cantidad').value) || 0;
                sub += p * c;
            });

            const porcTotal = (descCli + descMan) / 100;
            const ahorro = sub * porcTotal;
            const total = sub - ahorro;

            document.getElementById('sub_view').innerText = '$' + sub.toFixed(2);
            document.getElementById('ahorro_view').innerText = '-$' + ahorro.toFixed(2);
            document.getElementById('total_view').innerText = '$' + (total < 0 ? 0 : total).toFixed(2);
        }

        document.getElementById('buscador').addEventListener('input', function() {
            const t = this.value.toLowerCase();
            document.querySelectorAll('.product-row').forEach(r => {
                r.style.display = r.getAttribute('data-nombre').includes(t) ? "flex" : "none";
            });
        });

        selCli.addEventListener('change', calcular);
        inDesc.addEventListener('input', calcular);

        <?php if($pedido_finalizado): ?>
            Swal.fire('¡Éxito!', 'Venta #<?php echo $nuevo_id; ?> guardada', 'success').then(() => {
                window.open('imprimir_ticket.php?id=<?php echo $nuevo_id; ?>', '_blank');
                window.location.href = 'nueva_venta.php';
            });
        <?php endif; ?>


        // Función para actualizar la lista visual
        function actualizarResumen() {
            const contenedor = document.getElementById('lista-carrito');
            contenedor.innerHTML = ''; // Limpiar
            let hayProductos = false;

            document.querySelectorAll('.product-row').forEach(row => {
                const input = row.querySelector('.input-cantidad');
                // CAMBIO CLAVE: Usamos parseFloat en lugar de parseInt para renderizar correctamente la lista flotante
                const cantidad = parseFloat(input.value) || 0;
                if (cantidad > 0) {
                    hayProductos = true;
                    const nombre = row.querySelector('strong').innerText;
                    const precio = row.querySelector('.precio-unitario').value;
                    contenedor.innerHTML += `
                        <div class="item-lista">
                            <span>${cantidad}x ${nombre}</span>
                            <span>$${(cantidad * precio).toFixed(2)}</span>
                        </div>`;
                }
            });

            if (!hayProductos) {
                contenedor.innerHTML = '<p style="padding:10px; color:#94a3b8; text-align:center;">No hay productos agregados</p>';
            }
        }

        // Función para abrir el modal/sección en móvil
        function mostrarResumen() {
            const el = document.getElementById('contenedor-resumen');
            el.classList.toggle('hide-mobile');
        }

        // Escuchar cambios para actualizar el resumen automáticamente
        inputs.forEach(i => i.addEventListener('input', () => {
            calcular();
            actualizarResumen();
        }));
    </script>
</body>
</html>