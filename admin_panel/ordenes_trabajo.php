<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// --- 1. LÓGICA DE ACTUALIZACIÓN CON DESCUENTO DE TANQUE ---
if (isset($_GET['completar_producto'])) {
    $id_detalle = $_GET['completar_producto'];

    try {
        $pdo->beginTransaction();

        // 1. Obtener datos del producto y stock actual de la fórmula
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

        // 2. VERIFICACIÓN DE STOCK (Candado operativo)
        if ($check['stock_litros_disponibles'] < $litros_a_descontar) {
            throw new Exception("No hay stock suficiente en el tanque para surtir: " . number_format($litros_a_descontar, 2) . "L requeridos.");
        }

        // 3. DESCONTAR DEL TANQUE CENTRAL
        $stmt_descuento = $pdo->prepare("
            UPDATE formulas_maestras 
            SET stock_litros_disponibles = stock_litros_disponibles - ? 
            WHERE id = ?
        ");
        $stmt_descuento->execute([$litros_a_descontar, $check['id_formula_m']]);

        // 4. Marcar como LISTO el producto específico en detalle_pedido
        $pdo->prepare("UPDATE detalle_pedido SET estatus = 'Listo' WHERE id = ?")->execute([$id_detalle]);

        // 5. REVISIÓN GLOBAL DEL PEDIDO (¿Ya se surtió todo?)
        $pedido_id = $check['pedido_id'];
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM detalle_pedido WHERE pedido_id = ? AND (estatus != 'Listo' OR estatus IS NULL)");
        $stmt_count->execute([$pedido_id]);
        $pendientes = $stmt_count->fetchColumn();

        if ($pendientes == 0) {
            // NUEVO STATUS: Si ya no hay nada pendiente, pasa de 'Por Surtir' a 'Surtido'
            $pdo->prepare("UPDATE pedidos SET status_logistica = 'Surtido' WHERE id = ?")->execute([$pedido_id]);
            $msg = "Pedido #$pedido_id surtido totalmente. Litros descontados del tanque.";
        } else {
            $msg = "Producto envasado y descontado del tanque correctamente.";
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

// --- 2. CONSULTA MAESTRA (Filtro por status_logistica) ---
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
        'cliente' => "ID Cliente: ".$row['cliente_id'], // Aquí puedes hacer un join si quieres el nombre
        'fecha' => $row['fecha_pedido'],
        'pago' => $row['status_pago']
    ];
    $pedidos_agrupados[$row['pedido_id']]['productos'][] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Órdenes de Trabajo | AHD Clean</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        :root { --prod: #f59e0b; --surtir: #3b82f6; --accent: #10b981; --danger: #ef4444; }
        .pedido-group { background: #fff; border-radius: 15px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .pedido-header { background: #f8fafc; padding: 15px 25px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .producto-item { padding: 20px 25px; border-bottom: 1px solid #f1f5f9; border-left: 6px solid #cbd5e1; }
        .producto-item:last-child { border-bottom: none; }
        .badge-pago { font-size: 0.7rem; padding: 3px 8px; border-radius: 5px; font-weight: bold; text-transform: uppercase; }
        .pago-pagado { background: #dcfce7; color: #15803d; }
        .pago-pendiente { background: #fee2e2; color: #b91c1c; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; display: inline-block; }
        .bg-produccion { background: #fff7ed; color: #c2410c; border: 1px solid #fdba74; }
        .bg-surtir { background: #f0fdf4; color: #15803d; border: 1px solid #86efac; }
        .grid-mini { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 10px; }
        .data-mini { font-size: 0.85rem; color: #475569; }
        .data-mini strong { color: #1e293b; display: block; }
        .btn-listo { background: var(--accent); color: white; padding: 8px 20px; border-radius: 8px; font-size: 0.85rem; font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-lock { background: #cbd5e1; color: #64748b; cursor: not-allowed; text-decoration: none; padding: 8px 20px; border-radius: 8px; font-size: 0.85rem; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header" style="margin-bottom:30px;">
            <h1><i class="fas fa-truck-loading"></i> Monitor de Surtido y Envasado</h1>
            <p>Los pedidos listos para salir de los tanques centrales.</p>
        </div>

        <?php if(isset($_GET['error'])): ?>
            <div style="background: #fee2e2; color: #b91c1c; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #fecaca;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['msj'])): ?>
            <div style="background: #dcfce7; color: #15803d; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #bbf7d0;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['msj']); ?>
            </div>
        <?php endif; ?>

        <?php foreach ($pedidos_agrupados as $pedido_id => $pedido): ?>
            <div class="pedido-group">
                <div class="pedido-header">
                    <div>
                        <span style="font-size: 0.8rem; color: #64748b; font-weight: bold;">PEDIDO #<?php echo $pedido_id; ?></span>
                        <h3 style="margin: 0; color: #1e293b;"><?php echo htmlspecialchars($pedido['info']['cliente']); ?></h3>
                    </div>
                    <div style="text-align: right;">
                        <span class="badge-pago <?php echo ($pedido['info']['pago'] == 'Pagado') ? 'pago-pagado' : 'pago-pendiente'; ?>">
                            <?php echo $pedido['info']['pago']; ?>
                        </span><br>
                        <small style="color: #64748b;"><?php echo date("d/M H:i", strtotime($pedido['info']['fecha'])); ?></small>
                    </div>
                </div>

                <div class="pedido-body">
                    <?php foreach ($pedido['productos'] as $prod): 
                        $litros_req = $prod['cantidad'] * $prod['volumen_valor'];
                        $es_fab = ($prod['stock_tanque'] < $litros_req);
                    ?>
                    <div class="producto-item" style="border-left-color: <?php echo $es_fab ? 'var(--prod)' : 'var(--surtir)'; ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div style="flex: 1;">
                                <h4 style="margin: 0 0 5px 0;"><?php echo htmlspecialchars($prod['producto_nombre']); ?></h4>
                                <span class="status-badge <?php echo $es_fab ? 'bg-produccion' : 'bg-surtir'; ?>">
                                    <i class="fas <?php echo $es_fab ? 'fa-industry' : 'fa-fill-drip'; ?>"></i>
                                    <?php echo $es_fab ? 'Requiere Fabricación' : 'Líquido Disponible en Tanque'; ?>
                                </span>
                                
                                <div class="grid-mini">
                                    <div class="data-mini"><strong>Surtir</strong><?php echo $prod['cantidad']; ?> pzas de <?php echo (float)$prod['volumen_valor'].$prod['volumen_unidad']; ?></div>
                                    <div class="data-mini"><strong>Envase Necesario</strong><span style="color:#0369a1; font-weight:bold;"><?php echo $prod['nombre_envase'] ?: 'Sin envase definido'; ?></span></div>
                                    <div class="data-mini"><strong>Estado del Tanque</strong>
                                        <span style="color: <?php echo $es_fab ? 'var(--danger)' : 'var(--accent)'; ?>; font-weight:bold;">
                                            <?php echo number_format($prod['stock_tanque'], 1); ?>L disponibles
                                        </span> 
                                        <small>(Se usarán: <?php echo $litros_req; ?>L)</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div style="margin-left: 20px; text-align: right;">
                                <?php if ($es_fab): ?>
                                    <span class="btn-lock"><i class="fas fa-lock"></i> BLOQUEADO</span>
                                <?php else: ?>
                                    <a href="?completar_producto=<?php echo $prod['detalle_id']; ?>" 
                                       class="btn-listo" 
                                       onclick="return confirm('¿Confirmas que ya envasaste este producto? Se descontarán <?php echo $litros_req; ?>L del tanque.')">
                                        <i class="fas fa-check"></i> MARCAR LISTO
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if(empty($pedidos_agrupados)): ?>
            <div style="text-align:center; padding:100px; color:#94a3b8;">
                <i class="fas fa-check-double" style="font-size:3rem; margin-bottom:15px;"></i>
                <h2>Todo en orden</h2>
                <p>No hay pedidos pendientes de envasado.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>