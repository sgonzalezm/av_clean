<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// ==========================================
// 1. MANEJO DE FILTROS DE FECHA (PRESETS)
// ==========================================
$filtro = $_GET['filtro'] ?? 'mes';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';

$hoy = new DateTime();

if ($filtro === 'mes') {
    $fecha_inicio = $hoy->format('Y-m-01');
    $fecha_fin = $hoy->format('Y-m-t');
} elseif ($filtro === 'trimestre') {
    $mes_actual = (int)$hoy->format('n');
    $trimestre = ceil($mes_actual / 3);
    $mes_inicio = str_pad(($trimestre - 1) * 3 + 1, 2, '0', STR_PAD_LEFT);
    $fecha_inicio = $hoy->format("Y-$mes_inicio-01");
    $dt_inicio = new DateTime($fecha_inicio);
    $dt_fin = clone $dt_inicio;
    $dt_fin->modify('+2 months');
    $fecha_fin = $dt_fin->format('Y-m-t');
} elseif ($filtro === 'anual') {
    $fecha_inicio = $hoy->format('Y-01-01');
    $fecha_fin = $hoy->format('Y-12-31');
} elseif ($filtro === 'custom') {
    if (empty($fecha_inicio)) $fecha_inicio = $hoy->format('Y-m-01');
    if (empty($fecha_fin)) $fecha_fin = $hoy->format('Y-m-t');
}

// ==========================================
// 2. CONSULTAS FINANCIERAS (INGRESOS / EGRESOS)
// ==========================================

// A. Ingresos Facturados (Desde tabla facturacion)
$stmt_fact = $pdo->prepare("SELECT COALESCE(SUM(monto_total), 0) FROM facturacion WHERE DATE(fecha_facturacion) BETWEEN ? AND ? AND status_sat != 'CANCELADA'");
$stmt_fact->execute([$fecha_inicio, $fecha_fin]);
$ingresos_facturados = floatval($stmt_fact->fetchColumn());

// B. Ingresos NO Facturados (Pedidos finalizados que NO existen en la tabla facturacion)
$stmt_nofact = $pdo->prepare("
    SELECT COALESCE(SUM(p.total), 0) 
    FROM pedidos p 
    LEFT JOIN facturacion f ON p.id = f.pedido_id 
    WHERE f.id IS NULL AND DATE(p.fecha_pedido) BETWEEN ? AND ?
");
$stmt_nofact->execute([$fecha_inicio, $fecha_fin]);
$ingresos_no_facturados = floatval($stmt_nofact->fetchColumn());

// C. Egresos (Capital total invertido en base a columnas reales del Stock Actual)
$stmt_egresos = $pdo->query("SELECT COALESCE(SUM(stock_actual * precio_unitario), 0) FROM insumos");
$egresos_totales = floatval($stmt_egresos->fetchColumn());

// Totales consolidados
$ingresos_totales = $ingresos_facturados + $ingresos_no_facturados;
$balance_neto = $ingresos_totales - $egresos_totales;

// ==========================================
// 3. OBTENCIÓN DE DETALLES PARA LAS TABLAS
// ==========================================

// Listado de Facturas Actuales
$stmt_list_fact = $pdo->prepare("SELECT * FROM facturacion WHERE DATE(fecha_facturacion) BETWEEN ? AND ? ORDER BY fecha_facturacion DESC");
$stmt_list_fact->execute([$fecha_inicio, $fecha_fin]);
$facturas = $stmt_list_fact->fetchAll(PDO::FETCH_ASSOC);

// Listado de Pedidos No Facturados
$stmt_list_nofact = $pdo->prepare("
    SELECT p.*, c.nombre_completo as cliente_nombre 
    FROM pedidos p
    LEFT JOIN clientes c ON p.cliente_id = c.id
    LEFT JOIN facturacion f ON p.id = f.pedido_id 
    WHERE f.id IS NULL AND DATE(p.fecha_pedido) BETWEEN ? AND ?
    ORDER BY p.fecha_pedido DESC
");
$stmt_list_nofact->execute([$fecha_inicio, $fecha_fin]);
$pedidos_no_facturados = $stmt_list_nofact->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil de Impuestos & Cuadre Contable - AHD Clean</title>
    <link rel="stylesheet" href="../css/admin.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3182ce;
            --success: #38a169;
            --danger: #e53e3e;
            --warning: #dd6b20;
            --dark-blue: #2d3748;
            --bg-light: #f7fafc;
        }
        body {
            margin: 0;
            background: #f8fafc;
            font-family: system-ui, -apple-system, sans-serif;
        }
        
        /* Contenedor Flex para Sidebar + Contenido Principal */
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            padding: 25px;
            max-width: 100%;
            overflow-x: hidden;
            box-sizing: border-box;
        }

        /* Ajuste para aislar el contenedor específico de impuestos */
        .tax-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Toolbar de Filtros */
        .filter-bar {
            background: #fff;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
        }
        .preset-buttons {
            display: flex;
            gap: 10px;
        }
        .btn-preset {
            background: #e2e8f0;
            color: var(--dark-blue);
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-preset.active, .btn-preset:hover {
            background: var(--primary);
            color: white;
        }
        .custom-dates {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .custom-dates input[type="date"] {
            padding: 6px 10px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
        }

        /* Grid de Tarjetas de Totales */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card-metric {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            border-left: 5px solid #cbd5e0;
        }
        .card-metric.facturado { border-left-color: var(--primary); }
        .card-metric.no-facturado { border-left-color: var(--warning); }
        .card-metric.egresos { border-left-color: var(--danger); }
        .card-metric.neto { border-left-color: var(--success); }
        
        .card-metric h3 {
            margin: 0 0 10px 0;
            font-size: 0.9rem;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .card-metric .amount {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark-blue);
        }
        .card-metric .subtext {
            font-size: 0.8rem;
            color: #a0aec0;
            margin-top: 5px;
        }

        /* Secciones de Tablas de Datos */
        .split-tables {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }
        @media(min-width: 1200px) {
            .split-tables { grid-template-columns: 1fr 1fr; }
        }
        .table-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .table-header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #edf2f7;
            padding-bottom: 10px;
        }
        .table-header-actions h2 {
            margin: 0;
            font-size: 1.2rem;
            color: var(--dark-blue);
        }
        .table-wrapper {
            overflow-x: auto;
            max-height: 450px;
            overflow-y: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.9rem;
        }
        th {
            background: var(--bg-light);
            color: #4a5568;
            padding: 12px 10px;
            font-weight: 600;
            position: sticky;
            top: 0;
            border-bottom: 2px solid #e2e8f0;
        }
        td {
            padding: 12px 10px;
            border-bottom: 1px solid #edf2f7;
            color: #4a5568;
        }
        tr:hover { background: #f8fafc; }
        
        /* Badges de Estado */
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-success { background: #c6f6d5; color: #22543d; }
        .badge-danger { background: #fed7d7; color: #742a2a; }
        .badge-warning { background: #feebc8; color: #744210; }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--primary);
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .btn-action:hover { background: #2b6cb0; }

        /* Estilos de cabecera móvil en caso de layouts adaptados */
        .header-mobile-trigger {
            display: none;
            background: var(--dark-blue);
            color: white;
            padding: 10px 15px;
            align-items: center;
            gap: 10px;
            font-weight: bold;
        }
        
        @media (max-width: 992px) {
            .admin-layout { flex-direction: column; }
            .header-mobile-trigger { display: flex; }
            .main-content { padding: 15px; }
        }
    </style>
</head>
<body>

<div class="admin-layout">
    
    <?php include_once 'sidebar.php'; ?>

    <main class="main-content">
        <div class="tax-container">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                <h1><i class="fa-solid fa-calculator" style="color: var(--primary);"></i> Conciliación e Impuestos</h1>
                <p style="color: #718096; margin: 0;"><i class="fa-solid fa-calendar-days"></i> Periodo evaluado: <strong><?php echo date('d/m/Y', strtotime($fecha_inicio)); ?></strong> al <strong><?php echo date('d/m/Y', strtotime($fecha_fin)); ?></strong></p>
            </div>

            <div class="filter-bar">
                <div class="preset-buttons">
                    <a href="?filtro=mes" class="btn-preset <?php echo $filtro === 'mes' ? 'active' : ''; ?>">Este Mes</a>
                    <a href="?filtro=trimestre" class="btn-preset <?php echo $filtro === 'trimestre' ? 'active' : ''; ?>">Este Trimestre</a>
                    <a href="?filtro=anual" class="btn-preset <?php echo $filtro === 'anual' ? 'active' : ''; ?>">Año Fiscal</a>
                </div>
                
                <form method="GET" class="custom-dates">
                    <input type="hidden" name="filtro" value="custom">
                    <label for="fecha_inicio" style="font-size: 0.85rem; font-weight: 600; color: #4a5568;">Personalizado:</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>" required>
                    <span style="color: #cbd5e0;">al</span>
                    <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo $fecha_fin; ?>" required>
                    <button type="submit" class="btn-preset active" style="padding: 6px 12px; border:none; cursor:pointer;"><i class="fa-solid fa-filter"></i></button>
                </form>
            </div>

            <div class="metrics-grid">
                <div class="card-metric facturado">
                    <h3>Ingreso Facturado</h3>
                    <div class="amount">$<?php echo number_format($ingresos_facturados, 2); ?></div>
                    <div class="subtext">Suma de la tabla facturación</div>
                </div>
                <div class="card-metric no-facturado">
                    <h3>Ingreso Público Gral (No Facturado)</h3>
                    <div class="amount">$<?php echo number_format($ingresos_no_facturados, 2); ?></div>
                    <div class="subtext">Ventas internas y web sin CFDIs</div>
                </div>
                <div class="card-metric egresos">
                    <h3>Capital en Insumos</h3>
                    <div class="amount">$<?php echo number_format($egresos_totales, 2); ?></div>
                    <div class="subtext">Valor total actual de las existencias</div>
                </div>
                <div class="card-metric neto">
                    <h3>Balance (Flujo vs Stock)</h3>
                    <div class="amount" style="color: <?php echo $balance_neto >= 0 ? 'var(--success)' : 'var(--danger)'; ?>">
                        $<?php echo number_format($balance_neto, 2); ?>
                    </div>
                    <div class="subtext">Ingresos totales - Activos en Insumos</div>
                </div>
            </div>

            <div class="split-tables">
                
                <div class="table-section">
                    <div class="table-header-actions">
                        <h2><i class="fa-solid fa-file-invoice-dollar" style="color: var(--primary);"></i> Facturas Emitidas (SAT)</h2>
                        <span style="font-weight: bold; color: var(--primary); font-size: 1.1rem;">$<?php echo number_format($ingresos_facturados, 2); ?></span>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>RFC / Razón Social</th>
                                    <th>Monto</th>
                                    <th>Status SAT</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($facturas)): ?>
                                    <tr><td colspan="4" style="text-align: center; color: #a0aec0;">No hay facturas emitidas en este periodo.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($facturas as $f): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($f['fecha_facturacion'])); ?></td>
                                            <td>
                                                <div style="font-weight: 600; font-size:0.85rem;"><?php echo htmlspecialchars($f['razon_social']); ?></div>
                                                <small style="color: #718096;"><?php echo htmlspecialchars($f['rfc']); ?></small>
                                            </td>
                                            <td style="font-weight: 600;">$<?php echo number_format($f['monto_total'], 2); ?></td>
                                            <td>
                                                <?php 
                                                $status_class = ($f['status_sat'] === 'VIGENTE' || $f['status_sat'] === 'TIMBRADO') ? 'badge-success' : 'badge-danger';
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($f['status_sat']); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="table-section">
                    <div class="table-header-actions">
                        <h2><i class="fa-solid fa-basket-shopping" style="color: var(--warning);"></i> Pedidos sin Facturar</h2>
                        <span style="font-weight: bold; color: var(--warning); font-size: 1.1rem;">$<?php echo number_format($ingresos_no_facturados, 2); ?></span>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Folio</th>
                                    <th>Fecha</th>
                                    <th>Cliente</th>
                                    <th>Monto</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pedidos_no_facturados)): ?>
                                    <tr><td colspan="5" style="text-align: center; color: #a0aec0;">¡Perfecto! Todo está facturado en este periodo.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($pedidos_no_facturados as $p): ?>
                                        <tr>
                                            <td><b>#<?php echo $p['id']; ?></b></td>
                                            <td><?php echo date('d/m/Y', strtotime($p['fecha_pedido'])); ?></td>
                                            <td><?php echo htmlspecialchars($p['cliente_nombre'] ?? 'Público en General'); ?></td>
                                            <td style="font-weight: 600;">$<?php echo number_format($p['total'], 2); ?></td>
                                            <td>
                                                <a href="historial_ordenes.php?buscar=<?php echo $p['id']; ?>" class="btn-action" target="_blank">
                                                    <i class="fa-solid fa-eye"></i> Ver
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div> </div> </main>
</div> </body>
</html>