<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// --- 1. LÓGICA DE ACTUALIZACIÓN CON DESCUENTO DE TANQUE ---
if (isset($_GET['completar_producto'])) {
    $id_detalle = $_GET['completar_producto'];

    try {
        $pdo->beginTransaction();

        $stmt_val = $pdo->prepare("
            SELECT dp.pedido_id, dp.cantidad, dp.producto_id, fm.id as id_formula_m, 
                   fm.stock_litros_disponibles, prod.volumen_valor 
            FROM detalle_pedido dp
            INNER JOIN productos prod ON dp.producto_id = prod.id
            LEFT JOIN formulas_maestras fm ON prod.id_formula_maestra = fm.id
            WHERE dp.id = ?
        ");
        $stmt_val->execute([$id_detalle]);
        $check = $stmt_val->fetch();

        if (!$check) throw new Exception("Producto no encontrado.");

        $litros_a_descontar = $check['cantidad'] * $check['volumen_valor'];

        if ($check['stock_litros_disponibles'] < $litros_a_descontar) {
            throw new Exception("No hay stock suficiente en el tanque para surtir: " . number_format($litros_a_descontar, 2) . "L requeridos.");
        }

        $stmt_descuento = $pdo->prepare("
            UPDATE formulas_maestras 
            SET stock_litros_disponibles = stock_litros_disponibles - ? 
            WHERE id = ?
        ");
        $stmt_descuento->execute([$litros_a_descontar, $check['id_formula_m']]);

        $pdo->prepare("UPDATE detalle_pedido SET estatus = 'Listo' WHERE id = ?")->execute([$id_detalle]);

        $pedido_id = $check['pedido_id'];
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM detalle_pedido WHERE pedido_id = ? AND (estatus != 'Listo' OR estatus IS NULL)");
        $stmt_count->execute([$pedido_id]);
        $pendientes = $stmt_count->fetchColumn();

        if ($pendientes == 0) {
            $pdo->prepare("UPDATE pedidos SET status_logistica = 'Surtido' WHERE id = ?")->execute([$pedido_id]);
            $msg = "Pedido completo. ¡Tanque actualizado!";
        } else {
            $msg = "Producto envasado correctamente.";
        }

        $pdo->commit();
        header("Location: ordenes_trabajo.php?msj=" . urlencode($msg));
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header("Location: ordenes_trabajo.php?error=" . urlencode($e->getMessage()));
        exit;
    }
}

// --- 2. CONSULTA (Se quitó p.status por status_logistica) ---
$sql = "SELECT 
            p.id as pedido_id, p.fecha_pedido, p.cliente_id, p.status_pago, p.status_logistica,
            dp.id as detalle_id, dp.cantidad, dp.producto_nombre, dp.estatus as detalle_status,
            prod.volumen_valor, prod.volumen_unidad,
            env.nombre as nombre_envase,
            fm.nombre_formula, fm.stock_litros_disponibles as stock_tanque
        FROM pedidos p
        INNER JOIN detalle_pedido dp ON p.id = dp.pedido_id
        INNER JOIN productos prod ON dp.producto_id = prod.id
        LEFT JOIN envases env ON prod.envase_id = env.id
        LEFT JOIN formulas_maestras fm ON prod.id_formula_maestra = fm.id
        WHERE p.status_logistica = 'Por Surtir'
        AND (dp.estatus IS NULL OR dp.estatus != 'Listo')
        ORDER BY p.fecha_pedido ASC, p.id ASC";

$raw_data = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$pedidos_agrupados = [];
foreach ($raw_data as $row) {
    $pedidos_agrupados[$row['pedido_id']]['info'] = [
        'cliente_id' => $row['cliente_id'],
        'fecha' => $row['fecha_pedido'],
        'pago' => $row['status_pago']
    ];
    $pedidos_agrupados[$row['pedido_id']]['productos'][] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Bodega | AHD Clean</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        :root { --prod: #f59e0b; --surtir: #3b82f6; --accent: #10b981; --dark: #1e293b; }
        body { background: #f8fafc; margin: 0; font-family: sans-serif; }

        /* Mobile Layout */
        .header-mobile { display: none; position: fixed; top: 0; left: 0; right: 0; height: 60px; background: var(--dark); color: white; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 2000; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .main { padding: 20px; transition: 0.3s; }

        @media (max-width: 992px) {
            .header-mobile { display: flex; }
            .main { margin-left: 0 !important; padding: 75px 15px 120px 15px !important; }
            .sidebar { position: fixed; left: -260px; z-index: 3000; }
            .sidebar.active { left: 0; }
            .hide-mobile { display: none !important; }
        }

        .pedido-group { background: #fff; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; overflow: hidden; }
        .pedido-header { background: #f8fafc; padding: 15px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .producto-item { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; border-left: 6px solid #cbd5e1; }
        .producto-item:last-child { border-bottom: none; }

        .badge-pago { font-size: 0.65rem; padding: 3px 8px; border-radius: 5px; font-weight: bold; text-transform: uppercase; }
        .pago-pagado { background: #dcfce7; color: #15803d; }
        .pago-pendiente { background: #fef3c7; color: #92400e; }
        
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: bold; display: inline-block; margin-top: 5px; }
        .bg-produccion { background: #fff7ed; color: #c2410c; border: 1px solid #fdba74; }
        .bg-surtir { background: #f0fdf4; color: #15803d; border: 1px solid #86efac; }

        .data-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px; font-size: 0.8rem; }
        .data-mini strong { display: block; color: #94a3b8; font-size: 0.65rem; text-transform: uppercase; }

        .btn-listo { background: var(--accent); color: white; padding: 12px 20px; border-radius: 10px; font-weight: 800; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 10px; width: 100%; }
        .btn-lock { background: #f1f5f9; color: #94a3b8; cursor: not-allowed; border: 1px solid #e2e8f0; }

        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2500; }
        .overlay.active { display: block; }
    </style>
</head>
<body>
    <div class="overlay" id="overlay" onclick="toggleMenu()"></div>

    <div class="header-mobile">
        <button onclick="toggleMenu()" style="background:none; border:none; color:white; font-size:1.5rem;"><i class="fas fa-bars"></i></button>
        <span style="font-weight: 900; letter-spacing: 1px;">AHD OPERACIONES</span>
        <i class="fas fa-fill-drip"></i>
    </div>

    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header hide-mobile" style="margin-bottom:25px;">
            <h1><i class="fas fa-flask"></i> Monitor de Envasado</h1>
            <p>Control de salida de tanques centrales.</p>
        </div>

        <?php if(isset($_GET['error'])): ?>
            <div style="background:#fee2e2; color:#b91c1c; padding:15px; border-radius:12px; margin-bottom:20px; border:1px solid #fecaca;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['msj'])): ?>
            <div style="background:#dcfce7; color:#15803d; padding:15px; border-radius:12px; margin-bottom:20px; border:1px solid #bbf7d0;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['msj']); ?>
            </div>
        <?php endif; ?>

        <?php foreach ($pedidos_agrupados as $pedido_id => $pedido): ?>
            <div class="pedido-group">
                <div class="pedido-header">
                    <div>
                        <small style="color:#64748b; font-weight:bold;">PEDIDO #<?php echo $pedido_id; ?></small>
                        <h4 style="margin:0; color:var(--dark);">ID Cliente: <?php echo $pedido['info']['cliente_id']; ?></h4>
                    </div>
                    <div style="text-align: right;">
                        <span class="badge-pago <?php echo ($pedido['info']['pago'] == 'Pagado') ? 'pago-pagado' : 'pago-pendiente'; ?>">
                            <?php echo $pedido['info']['pago']; ?>
                        </span>
                    </div>
                </div>

                <div class="pedido-body">
                    <?php foreach ($pedido['productos'] as $prod): 
                        $litros_req = $prod['cantidad'] * $prod['volumen_valor'];
                        $es_fab = ($prod['stock_tanque'] < $litros_req);
                    ?>
                    <div class="producto-item" style="border-left-color: <?php echo $es_fab ? 'var(--prod)' : 'var(--surtir)'; ?>;">
                        <div style="display: flex; flex-direction: column;">
                            <h4 style="margin:0; font-size:1.1rem;"><?php echo htmlspecialchars($prod['producto_nombre']); ?></h4>
                            
                            <span class="status-badge <?php echo $es_fab ? 'bg-produccion' : 'bg-surtir'; ?>">
                                <i class="fas <?php echo $es_fab ? 'fa-industry' : 'fa-check-circle'; ?>"></i>
                                <?php echo $es_fab ? 'FABRICACIÓN PENDIENTE' : 'LISTO EN TANQUE'; ?>
                            </span>

                            <div class="data-grid">
                                <div class="data-mini"><strong>CANTIDAD</strong><?php echo $prod['cantidad']; ?> pzas (<?php echo (float)$prod['volumen_valor'].$prod['volumen_unidad']; ?>)</div>
                                <div class="data-mini"><strong>ENVASE</strong><?php echo $prod['nombre_envase'] ?: 'N/A'; ?></div>
                                <div class="data-mini" style="grid-column: span 2;">
                                    <strong>DISPONIBLE EN TANQUE</strong>
                                    <span style="font-weight:bold; color: <?php echo $es_fab ? 'var(--danger)' : 'var(--accent)'; ?>;">
                                        <?php echo number_format($prod['stock_tanque'], 1); ?>L / Necesitas: <?php echo $litros_req; ?>L
                                    </span>
                                </div>
                            </div>

                            <?php if ($es_fab): ?>
                                <div class="btn-listo btn-lock">
                                    <i class="fas fa-lock"></i> SIN LÍQUIDO SUFICIENTE
                                </div>
                            <?php else: ?>
                                <a href="?completar_producto=<?php echo $prod['detalle_id']; ?>" 
                                   class="btn-listo" 
                                   onclick="return confirm('¿Confirmas que ya envasaste este producto?')">
                                    <i class="fas fa-check"></i> MARCAR LISTO
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if(empty($pedidos_agrupados)): ?>
            <div style="text-align:center; padding:100px 20px; color:#94a3b8;">
                <i class="fas fa-check-double" style="font-size:3rem; margin-bottom:15px;"></i>
                <h2>Todo en orden</h2>
                <p>No hay productos pendientes por envasar.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleMenu() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('active');
        }
    </script>
</body>
</html>