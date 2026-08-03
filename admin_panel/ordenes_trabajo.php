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

// --- 2. CONSULTA MEJORADA ---
$sql = "SELECT 
            p.id as pedido_id, p.fecha_pedido, p.cliente_id, p.status_pago, p.status_logistica,
            dp.id as detalle_id, dp.cantidad, dp.producto_nombre, dp.estatus as detalle_status,
            prod.volumen_valor, prod.volumen_unidad,
            env.nombre as nombre_envase,
            fm.nombre_formula, fm.stock_litros_disponibles as stock_tanque,
            c.nombre_completo as cliente_nombre
        FROM pedidos p
        INNER JOIN detalle_pedido dp ON p.id = dp.pedido_id
        INNER JOIN productos prod ON dp.producto_id = prod.id
        LEFT JOIN envases env ON prod.envase_id = env.id
        LEFT JOIN formulas_maestras fm ON prod.id_formula_maestra = fm.id
        LEFT JOIN clientes c ON p.cliente_id = c.id
        WHERE p.status_logistica = 'Por Surtir'
        AND (dp.estatus IS NULL OR dp.estatus != 'Listo')
        ORDER BY p.fecha_pedido ASC, p.id ASC";

$raw_data = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$pedidos_agrupados = [];
$total_productos_pendientes = 0;
foreach ($raw_data as $row) {
    $pedidos_agrupados[$row['pedido_id']]['info'] = [
        'cliente_id' => $row['cliente_id'],
        'cliente_nombre' => $row['cliente_nombre'] ?? 'Cliente #' . $row['cliente_id'],
        'fecha' => $row['fecha_pedido'],
        'pago' => $row['status_pago']
    ];
    $pedidos_agrupados[$row['pedido_id']]['productos'][] = $row;
    $total_productos_pendientes++;
}

// Calcular total de litros requeridos
$total_litros_requeridos = 0;
foreach ($raw_data as $row) {
    $total_litros_requeridos += $row['cantidad'] * $row['volumen_valor'];
}

// Obtener resumen de stock por tanque
$resumen_tanques = $pdo->query("
    SELECT nombre_formula, stock_litros_disponibles 
    FROM formulas_maestras 
    WHERE stock_litros_disponibles > 0
    ORDER BY stock_litros_disponibles ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Órdenes de Trabajo | AHD Clean</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        :root { 
            --prod: #f59e0b; 
            --surtir: #3b82f6; 
            --accent: #10b981; 
            --dark: #1e293b;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
        }
        
        body { background: #f8fafc; margin: 0; font-family: system-ui, -apple-system, sans-serif; }

        /* Header Mobile */
        .header-mobile { display: none; position: fixed; top: 0; left: 0; right: 0; height: 60px; background: var(--dark); color: white; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 2000; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .header-mobile button { background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; padding: 5px 10px; }
        .main { padding: 20px; transition: 0.3s; }

        /* Responsive */
        @media (max-width: 992px) {
            .header-mobile { display: flex; }
            .main { margin-left: 0 !important; padding: 75px 15px 20px 15px !important; }
            .sidebar { position: fixed; left: -260px; z-index: 3000; }
            .sidebar.active { left: 0; }
            .hide-mobile { display: none !important; }
            .stats-grid { grid-template-columns: 1fr 1fr !important; }
            .data-grid { grid-template-columns: 1fr !important; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr !important; }
            .pedido-header { flex-direction: column; align-items: flex-start !important; gap: 10px; }
            .producto-item { padding: 12px 15px; }
            .btn-listo { padding: 15px; font-size: 1rem; }
        }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border-left: 4px solid #cbd5e1;
            transition: 0.3s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .stat-card .numero { font-size: 2rem; font-weight: 800; color: var(--dark); line-height: 1.2; }
        .stat-card .label { font-size: 0.8rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 5px; }
        .stat-card.total { border-left-color: var(--dark); }
        .stat-card.pendientes { border-left-color: var(--warning); }
        .stat-card.litros { border-left-color: var(--info); }
        .stat-card.pedidos { border-left-color: var(--accent); }

        /* Filtros */
        .filtros-container {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        .filtros-container input, 
        .filtros-container select {
            padding: 8px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            background: white;
            min-width: 200px;
            flex: 1;
        }
        .filtros-container input:focus,
        .filtros-container select:focus {
            outline: none;
            border-color: var(--info);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Pedido Group */
        .pedido-group { 
            background: #fff; 
            border-radius: 15px; 
            margin-bottom: 25px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
            border: 1px solid #e2e8f0; 
            overflow: hidden;
            transition: 0.3s;
        }
        .pedido-group:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.08); }

        .pedido-header { 
            background: #f8fafc; 
            padding: 15px 25px; 
            border-bottom: 1px solid #e2e8f0; 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .pedido-header .cliente-info h4 { margin: 0; color: var(--dark); font-size: 1.1rem; }
        .pedido-header .cliente-info small { color: #64748b; font-size: 0.85rem; }
        
        .pedido-header .estado-info {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .pedido-header .progress-info {
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            padding: 5px 15px;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
        }
        .pedido-header .progress-info .barra {
            width: 80px;
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
        }
        .pedido-header .progress-info .barra .fill {
            height: 100%;
            background: var(--accent);
            transition: width 0.5s ease;
        }

        /* Badges */
        .badge-pago { 
            font-size: 0.65rem; 
            padding: 4px 12px; 
            border-radius: 20px; 
            font-weight: bold; 
            text-transform: uppercase;
            display: inline-block;
        }
        .pago-pagado { background: #dcfce7; color: #15803d; }
        .pago-pendiente { background: #fef3c7; color: #92400e; }
        .pago-credito { background: #e0f2fe; color: #0369a1; }

        .status-badge { 
            padding: 4px 12px; 
            border-radius: 20px; 
            font-size: 0.7rem; 
            font-weight: bold; 
            display: inline-block; 
            margin-top: 5px; 
        }
        .bg-produccion { background: #fff7ed; color: #c2410c; border: 1px solid #fdba74; }
        .bg-surtir { background: #f0fdf4; color: #15803d; border: 1px solid #86efac; }

        /* Producto Item */
        .producto-item { 
            padding: 18px 25px; 
            border-bottom: 1px solid #f1f5f9; 
            border-left: 6px solid #cbd5e1;
            transition: 0.2s;
        }
        .producto-item:last-child { border-bottom: none; }
        .producto-item:hover { background: #fafbfc; }
        
        .producto-item .producto-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 10px;
        }
        .producto-item .producto-header h4 { 
            margin: 0; 
            font-size: 1.1rem; 
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .producto-item .producto-header .cantidad {
            font-weight: 600;
            color: var(--dark);
            background: #f1f5f9;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 0.9rem;
        }

        .data-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr 1fr; 
            gap: 15px; 
            margin-top: 12px; 
            font-size: 0.9rem; 
        }
        .data-grid .data-mini strong { 
            display: block; 
            color: #94a3b8; 
            font-size: 0.65rem; 
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        .data-grid .data-mini .valor {
            font-weight: 600;
            color: var(--dark);
        }
        .data-grid .data-mini .stock-bajo { color: var(--danger); }
        .data-grid .data-mini .stock-ok { color: var(--accent); }

        /* Botones */
        .btn-listo { 
            background: var(--accent); 
            color: white; 
            padding: 12px 25px; 
            border-radius: 10px; 
            font-weight: 700; 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            gap: 10px; 
            margin-top: 12px; 
            border: none;
            cursor: pointer;
            transition: 0.3s;
            min-width: 180px;
        }
        .btn-listo:hover { 
            background: #059669; 
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        .btn-listo:active { transform: translateY(0); }
        
        .btn-lock { 
            background: #f1f5f9; 
            color: #94a3b8; 
            cursor: not-allowed; 
            border: 1px solid #e2e8f0; 
        }
        .btn-lock:hover { 
            transform: none; 
            box-shadow: none; 
            background: #f1f5f9; 
        }

        .btn-accion-rapida {
            background: var(--info);
            color: white;
            padding: 6px 15px;
            border-radius: 6px;
            font-size: 0.8rem;
            text-decoration: none;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-accion-rapida:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }

        .btn-limpiar-filtros {
            background: #f1f5f9;
            color: #64748b;
            padding: 8px 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            transition: 0.3s;
            font-size: 0.9rem;
        }
        .btn-limpiar-filtros:hover {
            background: #e2e8f0;
        }

        .overlay { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.6); 
            z-index: 2500; 
        }
        .overlay.active { display: block; }

        /* Animaciones */
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .pedido-group {
            animation: slideIn 0.3s ease-out;
        }

        /* Scrollbar personalizada */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* Vacío */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #94a3b8;
        }
        .empty-state i { font-size: 4rem; margin-bottom: 20px; opacity: 0.5; }
        .empty-state h2 { color: var(--dark); margin-bottom: 10px; }
        .empty-state p { font-size: 1.1rem; }
    </style>
</head>
<body>
    <div class="overlay" id="overlay" onclick="toggleMenu()"></div>

    <div class="header-mobile">
        <button onclick="toggleMenu()"><i class="fas fa-bars"></i></button>
        <span style="font-weight: 900; letter-spacing: 1px;">AHD OPERACIONES</span>
        <i class="fas fa-fill-drip"></i>
    </div>

    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header hide-mobile" style="margin-bottom:25px;">
            <h1><i class="fas fa-flask" style="color: var(--info);"></i> Monitor de Envasado</h1>
            <p style="color: #64748b;">Control de salida de tanques centrales y productos pendientes.</p>
        </div>

        <!-- Mensajes -->
        <?php if(isset($_GET['error'])): ?>
            <div style="background:#fee2e2; color:#b91c1c; padding:15px 20px; border-radius:12px; margin-bottom:20px; border:1px solid #fecaca; display:flex; align-items:center; gap:12px;">
                <i class="fas fa-exclamation-triangle" style="font-size:1.2rem;"></i>
                <span><?php echo htmlspecialchars($_GET['error']); ?></span>
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['msj'])): ?>
            <div style="background:#dcfce7; color:#15803d; padding:15px 20px; border-radius:12px; margin-bottom:20px; border:1px solid #bbf7d0; display:flex; align-items:center; gap:12px;">
                <i class="fas fa-check-circle" style="font-size:1.2rem;"></i>
                <span><?php echo htmlspecialchars($_GET['msj']); ?></span>
            </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="numero"><?php echo $total_productos_pendientes; ?></div>
                <div class="label"><i class="fas fa-boxes"></i> Productos Pendientes</div>
            </div>
            <div class="stat-card pedidos">
                <div class="numero"><?php echo count($pedidos_agrupados); ?></div>
                <div class="label"><i class="fas fa-shopping-cart"></i> Pedidos Activos</div>
            </div>
            <div class="stat-card litros">
                <div class="numero"><?php echo number_format($total_litros_requeridos, 1); ?>L</div>
                <div class="label"><i class="fas fa-fill-drip"></i> Litros Requeridos</div>
            </div>
            <div class="stat-card pendientes">
                <div class="numero">
                    <?php 
                    $tanques_bajos = 0;
                    foreach($resumen_tanques as $t) {
                        if($t['stock_litros_disponibles'] < 100) $tanques_bajos++;
                    }
                    echo $tanques_bajos;
                    ?>
                </div>
                <div class="label"><i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i> Tanques Bajo Stock</div>
            </div>
        </div>

        <!-- Filtros y Búsqueda -->
        <div class="filtros-container">
            <input type="text" id="buscarPedido" placeholder="🔍 Buscar por #pedido, cliente o producto..." onkeyup="filtrarPedidos()">
            <select id="filtroPago" onchange="filtrarPedidos()">
                <option value="todos">Todos los pagos</option>
                <option value="Pagado">Pagado</option>
                <option value="Pendiente">Pendiente</option>
                <option value="Crédito">Crédito</option>
            </select>
            <select id="filtroStock" onchange="filtrarPedidos()">
                <option value="todos">Todos los productos</option>
                <option value="suficiente">Stock suficiente</option>
                <option value="insuficiente">Stock insuficiente</option>
            </select>
            <button class="btn-limpiar-filtros" onclick="limpiarFiltros()">
                <i class="fas fa-undo"></i> Limpiar
            </button>
        </div>

        <!-- Pedidos -->
        <?php foreach ($pedidos_agrupados as $pedido_id => $pedido): 
            $total_productos = count($pedido['productos']);
            $listos = 0;
            foreach($pedido['productos'] as $prod) {
                if(!$prod['stock_tanque'] < ($prod['cantidad'] * $prod['volumen_valor'])) {
                    $listos++;
                }
            }
            $porcentaje = ($listos / $total_productos) * 100;
        ?>
            <div class="pedido-group" data-pedido="<?php echo $pedido_id; ?>" data-cliente="<?php echo strtolower($pedido['info']['cliente_nombre']); ?>" data-pago="<?php echo $pedido['info']['pago']; ?>">
                <div class="pedido-header">
                    <div class="cliente-info">
                        <h4>
                            <i class="fas fa-user"></i> 
                            <?php echo htmlspecialchars($pedido['info']['cliente_nombre']); ?>
                        </h4>
                        <small>
                            <i class="fas fa-hashtag"></i> Pedido #<?php echo $pedido_id; ?> 
                            | <i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y H:i', strtotime($pedido['info']['fecha'])); ?>
                        </small>
                    </div>
                    <div class="estado-info">
                        <span class="badge-pago <?php echo ($pedido['info']['pago'] == 'Pagado') ? 'pago-pagado' : (($pedido['info']['pago'] == 'Crédito') ? 'pago-credito' : 'pago-pendiente'); ?>">
                            <i class="fas fa-<?php echo ($pedido['info']['pago'] == 'Pagado') ? 'check' : 'clock'; ?>"></i>
                            <?php echo $pedido['info']['pago']; ?>
                        </span>
                        <div class="progress-info">
                            <span style="font-size:0.85rem; font-weight:600;"><?php echo $listos; ?>/<?php echo $total_productos; ?></span>
                            <div class="barra">
                                <div class="fill" style="width: <?php echo $porcentaje; ?>%;"></div>
                            </div>
                            <span style="font-size:0.7rem; color:#94a3b8;"><?php echo round($porcentaje); ?>%</span>
                        </div>
                    </div>
                </div>

                <div class="pedido-body">
                    <?php foreach ($pedido['productos'] as $prod): 
                        $litros_req = $prod['cantidad'] * $prod['volumen_valor'];
                        $es_fab = ($prod['stock_tanque'] < $litros_req);
                        $stock_restante = $prod['stock_tanque'] - $litros_req;
                    ?>
                    <div class="producto-item" style="border-left-color: <?php echo $es_fab ? 'var(--prod)' : 'var(--surtir)'; ?>;">
                        <div class="producto-header">
                            <h4>
                                <?php echo htmlspecialchars($prod['producto_nombre']); ?>
                                <span class="cantidad"><i class="fas fa-box"></i> <?php echo $prod['cantidad']; ?> pzas</span>
                            </h4>
                            <span class="status-badge <?php echo $es_fab ? 'bg-produccion' : 'bg-surtir'; ?>">
                                <i class="fas <?php echo $es_fab ? 'fa-industry' : 'fa-check-circle'; ?>"></i>
                                <?php echo $es_fab ? 'FABRICACIÓN PENDIENTE' : 'LISTO EN TANQUE'; ?>
                            </span>
                        </div>

                        <div class="data-grid">
                            <div class="data-mini">
                                <strong><i class="fas fa-weight"></i> Volumen</strong>
                                <span class="valor"><?php echo (float)$prod['volumen_valor']; ?> <?php echo $prod['volumen_unidad']; ?> / pza</span>
                                <br><span style="font-size:0.8rem; color:#64748b;">Total: <?php echo number_format($litros_req, 1); ?>L</span>
                            </div>
                            <div class="data-mini">
                                <strong><i class="fas fa-flask"></i> Envase</strong>
                                <span class="valor"><?php echo $prod['nombre_envase'] ?: 'N/A'; ?></span>
                                <br><span style="font-size:0.8rem; color:#64748b;"><?php echo $prod['nombre_formula'] ?? 'Sin fórmula'; ?></span>
                            </div>
                            <div class="data-mini">
                                <strong><i class="fas fa-oil-can"></i> Stock en Tanque</strong>
                                <span class="valor <?php echo $es_fab ? 'stock-bajo' : 'stock-ok'; ?>">
                                    <?php echo number_format($prod['stock_tanque'], 1); ?>L
                                </span>
                                <br>
                                <span style="font-size:0.8rem; color:<?php echo $es_fab ? 'var(--danger)' : 'var(--accent)'; ?>;">
                                    <?php echo $es_fab ? 'Faltan ' . number_format($litros_req - $prod['stock_tanque'], 1) . 'L' : 'Sobran ' . number_format($stock_restante, 1) . 'L'; ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($es_fab): ?>
                            <div class="btn-listo btn-lock">
                                <i class="fas fa-lock"></i> SIN LÍQUIDO SUFICIENTE
                            </div>
                        <?php else: ?>
                            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;">
                                <a href="?completar_producto=<?php echo $prod['detalle_id']; ?>" 
                                   class="btn-listo" 
                                   onclick="return confirm('¿Confirmas que ya envasaste este producto?\n\nProducto: <?php echo addslashes($prod['producto_nombre']); ?>\nCantidad: <?php echo $prod['cantidad']; ?> pzas\nLitros: <?php echo number_format($litros_req, 1); ?>L')">
                                    <i class="fas fa-check"></i> MARCAR LISTO
                                </a>
                                <a href="detalle_pedido.php?id=<?php echo $pedido_id; ?>" class="btn-accion-rapida" target="_blank">
                                    <i class="fas fa-eye"></i> Ver Detalle
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if(empty($pedidos_agrupados)): ?>
            <div class="empty-state">
                <i class="fas fa-check-double" style="color: var(--accent);"></i>
                <h2>¡Todo en orden!</h2>
                <p>No hay productos pendientes por envasar en este momento.</p>
                <div style="margin-top:20px; color:#94a3b8; font-size:0.9rem;">
                    <i class="fas fa-sync-alt fa-spin"></i> Actualiza si acabas de procesar un pedido
                </div>
            </div>
        <?php endif; ?>

        <!-- Resumen de tanques -->
        <?php if(!empty($resumen_tanques)): ?>
        <div style="margin-top:30px; background:white; border-radius:12px; padding:20px; border:1px solid #e2e8f0;">
            <h4 style="margin-top:0; color:var(--dark);"><i class="fas fa-oil-can"></i> Resumen de Tanques</h4>
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap:10px;">
                <?php foreach($resumen_tanques as $tanque): 
                    $color = $tanque['stock_litros_disponibles'] < 100 ? 'var(--danger)' : ($tanque['stock_litros_disponibles'] < 300 ? 'var(--warning)' : 'var(--accent)');
                ?>
                    <div style="background:#f8fafc; padding:12px 15px; border-radius:8px; border-left:4px solid <?php echo $color; ?>;">
                        <div style="font-weight:600; font-size:0.9rem;"><?php echo htmlspecialchars($tanque['nombre_formula']); ?></div>
                        <div style="font-size:1.2rem; font-weight:700; color:<?php echo $color; ?>;">
                            <?php echo number_format($tanque['stock_litros_disponibles'], 1); ?>L
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleMenu() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('active');
        }

        function filtrarPedidos() {
            const busqueda = document.getElementById('buscarPedido').value.toLowerCase();
            const filtroPago = document.getElementById('filtroPago').value;
            const filtroStock = document.getElementById('filtroStock').value;
            
            const grupos = document.querySelectorAll('.pedido-group');
            
            grupos.forEach(grupo => {
                const pedidoId = grupo.dataset.pedido;
                const cliente = grupo.dataset.cliente;
                const pago = grupo.dataset.pago;
                
                // Buscar productos dentro del grupo
                const productos = grupo.querySelectorAll('.producto-item');
                let visible = false;
                let coincidenciaStock = false;
                
                productos.forEach(producto => {
                    const nombreProducto = producto.querySelector('h4').textContent.toLowerCase();
                    const coincideBusqueda = pedidoId.includes(busqueda) || 
                                            cliente.includes(busqueda) || 
                                            nombreProducto.includes(busqueda);
                    
                    const coincidePago = filtroPago === 'todos' || pago === filtroPago;
                    
                    let coincideStock = true;
                    if (filtroStock === 'suficiente') {
                        coincideStock = !producto.querySelector('.bg-produccion');
                    } else if (filtroStock === 'insuficiente') {
                        coincideStock = !!producto.querySelector('.bg-produccion');
                    }
                    
                    if (coincideBusqueda && coincidePago && coincideStock) {
                        coincidenciaStock = true;
                    }
                    
                    // Mostrar/ocultar producto individual
                    if (coincideBusqueda && coincidePago) {
                        producto.style.display = '';
                        if (filtroStock === 'suficiente' && producto.querySelector('.bg-produccion')) {
                            producto.style.display = 'none';
                        } else if (filtroStock === 'insuficiente' && !producto.querySelector('.bg-produccion')) {
                            producto.style.display = 'none';
                        } else {
                            producto.style.display = '';
                        }
                    } else {
                        producto.style.display = 'none';
                    }
                });
                
                // Verificar si hay algún producto visible
                const productosVisibles = grupo.querySelectorAll('.producto-item[style*="display: block"], .producto-item:not([style*="display: none"])');
                grupo.style.display = productosVisibles.length > 0 ? '' : 'none';
            });
        }

        function limpiarFiltros() {
            document.getElementById('buscarPedido').value = '';
            document.getElementById('filtroPago').value = 'todos';
            document.getElementById('filtroStock').value = 'todos';
            filtrarPedidos();
        }

        // Auto-cerrar mensajes después de 5 segundos
        setTimeout(function() {
            const alerts = document.querySelectorAll('[style*="background:#fee2e2"], [style*="background:#dcfce7"]');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>