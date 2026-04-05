<?php
include '../includes/session.php';
include '../includes/conexion.php';
verificarSesion();

// --- 1. LÓGICA DE FILTRADO DINÁMICO ---
$periodo = $_GET['periodo'] ?? 'mes';
$f_inicio_custom = $_GET['f_inicio'] ?? '';
$f_fin_custom = $_GET['f_fin'] ?? '';

switch ($periodo) {
    case 'trimestre':
        $fecha_inicio = date('Y-m-d', strtotime('-3 months'));
        $fecha_fin = date('Y-m-d');
        $titulo_filtro = "Último Trimestre";
        break;
    case 'custom':
        $fecha_inicio = $f_inicio_custom;
        $fecha_fin = $f_fin_custom;
        $titulo_filtro = "Rango Personalizado";
        break;
    case 'mes':
    default:
        $fecha_inicio = date('Y-m-01');
        $fecha_fin = date('Y-m-d');
        $titulo_filtro = "Mes Actual (" . date('M Y') . ")";
        break;
}

$rango_sql = "BETWEEN '$fecha_inicio 00:00:00' AND '$fecha_fin 23:59:59'";

// --- 2. CONSULTAS A BASE DE DATOS ---
$totalProductos = $pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn();
$totalFormulas  = $pdo->query("SELECT COUNT(*) FROM formulas_maestras")->fetchColumn();

// Ventas del periodo
$stmt_ventas = $pdo->query("SELECT SUM(total) as ingresos, COUNT(*) as pedidos FROM pedidos WHERE fecha_pedido $rango_sql AND status != 'Cancelado'");
$stats_ventas = $stmt_ventas->fetch();
$ingresosPeriodo = $stats_ventas['ingresos'] ?? 0;

// Inversión Real (Costo de insumos en órdenes de producción)
$inversionProduccion = $pdo->query("SELECT SUM(costo_total_insumos) FROM ordenes_produccion WHERE fecha_registro $rango_sql")->fetchColumn() ?? 0;

// --- 3. VALORIZACIÓN DE TANQUES (INVENTARIO ACTUAL TERMINADO) ---
// Calculamos el valor actual de los litros en tanque basados en el precio de mayoreo (>=20L)
$sql_valor_tanques = "
    SELECT SUM(f.stock_litros_disponibles * COALESCE(
        (SELECT (p.precio / p.volumen_valor) 
         FROM productos p 
         WHERE p.id_formula_maestra = f.id AND p.volumen_valor >= 20 
         ORDER BY p.volumen_valor DESC LIMIT 1),
        (SELECT (p2.precio / p2.volumen_valor) * 0.80 
         FROM productos p2 
         WHERE p2.id_formula_maestra = f.id 
         ORDER BY p2.volumen_valor ASC LIMIT 1)
    )) as valor_total
    FROM formulas_maestras f
";
$valorInventarioTanques = $pdo->query($sql_valor_tanques)->fetchColumn() ?? 0;

// --- 4. PROYECCIÓN DINÁMICA (Basada en Producción del Periodo) ---
$sql_proyeccion = "
    SELECT 
        SUM(odp.cantidad_litros * COALESCE(
            (SELECT precio FROM productos WHERE id_formula_maestra = p.id_formula_maestra AND volumen_valor = 1 LIMIT 1),
            (p.precio / p.volumen_valor)
        )) as retail_real,
        
        SUM(odp.cantidad_litros * COALESCE(
            (SELECT (precio / volumen_valor) FROM productos WHERE id_formula_maestra = p.id_formula_maestra AND volumen_valor >= 20 ORDER BY volumen_valor DESC LIMIT 1),
            (p.precio / p.volumen_valor) * 0.80 
        )) as wholesale_real
    FROM orden_detalle_productos odp
    JOIN productos p ON odp.id_producto = p.id
    JOIN ordenes_produccion op ON odp.id_orden = op.id
    WHERE op.fecha_registro $rango_sql
";

$proyecciones = $pdo->query($sql_proyeccion)->fetch();
$proyeccionRetail = $proyecciones['retail_real'] ?? 0;
$proyeccionWholesale = $proyecciones['wholesale_real'] ?? 0;

// Balance y Otros
$balanceNeto = $ingresosPeriodo - $inversionProduccion;
$ivaAcreditable = $inversionProduccion * 0.16;

// Operaciones de Producción
$stats_prod = $pdo->query("
    SELECT 
        SUM(CASE WHEN estado = 'PENDIENTE' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'TERMINADO' THEN 1 ELSE 0 END) as finalizadas
    FROM ordenes_produccion WHERE fecha_registro $rango_sql
")->fetch();

// Leaderboard de Vendedores
$leaderboard = $pdo->query("
    SELECT u.nombre, SUM(p.total) as total_vendido
    FROM pedidos p
    JOIN usuarios_admin u ON p.usuario_id = u.id
    WHERE p.fecha_pedido $rango_sql AND p.status != 'Cancelado'
    GROUP BY u.id ORDER BY total_vendido DESC LIMIT 5
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Dashboard | AHD Clean</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .filter-bar { background: white; padding: 20px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .filter-form { display: flex; gap: 10px; align-items: flex-end; }
        .metricas-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .card.kpi { padding: 20px; border-radius: 15px; background: #fff; display: flex; align-items: center; gap: 15px; border: 1px solid #f1f5f9; }
        .kpi-icon { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        
        .retorno-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 25px; }
        .retorno-card { background: white; padding: 20px; border-radius: 15px; border: 1px solid #e2e8f0; position: relative; overflow: hidden; }
        .retorno-card::before { content: ""; position: absolute; top: 0; left: 0; width: 4px; height: 100%; }
        .card-retail::before { background: #38a169; }
        .card-masivo::before { background: #3182ce; }
        
        .bg-blue { background: #ebf8ff; color: #3182ce; }
        .bg-green { background: #f0fff4; color: #38a169; }
        .bg-orange { background: #fffaf0; color: #dd6b20; }
        .bg-red { background: #fff5f5; color: #e53e3e; }
        .bg-purple { background: #faf5ff; color: #805ad5; }
        
        #custom-dates { display: <?php echo $periodo == 'custom' ? 'flex' : 'none'; ?>; gap: 10px; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <div class="header" style="margin-bottom: 25px;">
            <h1>Dashboard de Control</h1>
            <p style="color: #718096;"><i class="fas fa-chart-pie"></i> Periodo: <strong><?php echo $titulo_filtro; ?></strong></p>
        </div>

        <div class="filter-bar">
            <form action="" method="GET" class="filter-form">
                <div style="display:flex; flex-direction:column; gap:5px;">
                    <label style="font-size:0.7rem; font-weight:bold; color:#718096;">PERIODO</label>
                    <select name="periodo" class="filter-input" onchange="this.value=='custom' ? document.getElementById('custom-dates').style.display='flex' : this.form.submit()" style="padding:8px; border-radius:8px; border:1px solid #cbd5e0;">
                        <option value="mes" <?php echo $periodo == 'mes' ? 'selected' : ''; ?>>Mensual</option>
                        <option value="trimestre" <?php echo $periodo == 'trimestre' ? 'selected' : ''; ?>>Trimestral</option>
                        <option value="custom" <?php echo $periodo == 'custom' ? 'selected' : ''; ?>>Personalizado</option>
                    </select>
                </div>

                <div id="custom-dates">
                    <div style="display:flex; flex-direction:column; gap:5px;">
                        <label style="font-size:0.7rem; font-weight:bold; color:#718096;">DESDE</label>
                        <input type="date" name="f_inicio" value="<?php echo $fecha_inicio; ?>" style="padding:7px; border-radius:8px; border:1px solid #cbd5e0;">
                    </div>
                    <div style="display:flex; flex-direction:column; gap:5px;">
                        <label style="font-size:0.7rem; font-weight:bold; color:#718096;">HASTA</label>
                        <input type="date" name="f_fin" value="<?php echo $fecha_fin; ?>" style="padding:7px; border-radius:8px; border:1px solid #cbd5e0;">
                    </div>
                    <button type="submit" style="background:#3182ce; color:white; border:none; padding:10px 15px; border-radius:8px; cursor:pointer; align-self:flex-end;"><i class="fas fa-search"></i></button>
                </div>
            </form>
            <div style="text-align:right;">
                <strong><?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> al <?php echo date('d/m/Y', strtotime($fecha_fin)); ?></strong>
            </div>
        </div>

        <div class="metricas-grid">
            <div class="card kpi">
                <div class="kpi-icon bg-blue"><i class="fas fa-cash-register"></i></div>
                <div><small style="color:#718096; font-size:0.65rem; font-weight:bold;">INGRESOS VENTAS</small><div style="font-size:1.1rem; font-weight:800;">$<?php echo number_format($ingresosPeriodo, 2); ?></div></div>
            </div>
            <div class="card kpi">
                <div class="kpi-icon bg-orange"><i class="fas fa-flask"></i></div>
                <div><small style="color:#718096; font-size:0.65rem; font-weight:bold;">INV. PRODUCCIÓN</small><div style="font-size:1.1rem; font-weight:800;">$<?php echo number_format($inversionProduccion, 2); ?></div></div>
            </div>
            <div class="card kpi">
                <div class="kpi-icon <?php echo $balanceNeto >= 0 ? 'bg-green' : 'bg-red'; ?>"><i class="fas fa-wallet"></i></div>
                <div><small style="color:#718096; font-size:0.65rem; font-weight:bold;">BALANCE NETO</small><div style="font-size:1.1rem; font-weight:800;">$<?php echo number_format($balanceNeto, 2); ?></div></div>
            </div>
            <div class="card kpi" style="border: 1px solid #d6bcfa;">
                <div class="kpi-icon bg-purple"><i class="fas fa-gas-pump"></i></div>
                <div><small style="color:#805ad5; font-size:0.65rem; font-weight:bold;">VALOR EN TANQUES (MAYOREO)</small><div style="font-size:1.1rem; font-weight:800;">$<?php echo number_format($valorInventarioTanques, 2); ?></div></div>
            </div>
        </div>

        <h3 style="margin: 30px 0 15px 0;"><i class="fas fa-chart-line"></i> Proyección de Retorno del Periodo</h3>
        <p style="font-size:0.8rem; color:#718096; margin-bottom:15px;">Estimación de venta de los litros fabricados en este rango de fechas.</p>

        <div class="retorno-container">
            <div class="retorno-card card-retail">
                <small style="color:#38a169; font-weight:800;">ESCENARIO RETAIL (1L)</small>
                <div style="font-size: 1.8rem; font-weight:900; color:#2d3748; margin: 5px 0;">$<?php echo number_format($proyeccionRetail, 2); ?></div>
                <span style="color:#38a169; font-size:0.8rem; font-weight:bold;">Utilidad Potencial: $<?php echo number_format($proyeccionRetail - $inversionProduccion, 2); ?></span>
            </div>

            <div class="retorno-card card-masivo">
                <small style="color:#3182ce; font-weight:800;">ESCENARIO MAYOREO (+20L)</small>
                <div style="font-size: 1.8rem; font-weight:900; color:#2d3748; margin: 5px 0;">$<?php echo number_format($proyeccionWholesale, 2); ?></div>
                <span style="color:#3182ce; font-size:0.8rem; font-weight:bold;">Utilidad Potencial: $<?php echo number_format($proyeccionWholesale - $inversionProduccion, 2); ?></span>
            </div>

            <div class="retorno-card" style="border-color:#e9d8fd; background:#faf5ff;">
                <small style="color:#805ad5; font-weight:800;">IVA ACREDITABLE GENERADO</small>
                <div style="font-size: 1.8rem; font-weight:900; color:#2d3748; margin: 5px 0;">$<?php echo number_format($ivaAcreditable, 2); ?></div>
                <p style="font-size:0.75rem; color:#718096; margin-top:5px;">Impuesto recuperable por compra de insumos.</p>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:25px; margin-top:30px;">
            <div class="card" style="background:white; padding:20px; border-radius:15px; border: 1px solid #f1f5f9;">
                <h3><i class="fas fa-star" style="color:#ecc94b;"></i> Top Vendedores</h3>
                <table style="width:100%; border-collapse:collapse; margin-top:15px;">
                    <?php if(empty($leaderboard)): ?>
                        <tr><td style="color:#a0aec0; font-size:0.9rem; padding:10px 0;">Sin ventas en este periodo.</td></tr>
                    <?php else: ?>
                        <?php foreach($leaderboard as $v): ?>
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:12px 0;"><strong><?php echo htmlspecialchars($v['nombre']); ?></strong></td>
                                <td style="text-align:right; font-weight:bold; color:#2d3748;">$<?php echo number_format($v['total_vendido'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </table>
            </div>
            
            <div class="card" style="background:white; padding:20px; border-radius:15px; border: 1px solid #f1f5f9;">
                <h3><i class="fas fa-tasks"></i> Actividad de Producción</h3>
                <div style="margin-top:20px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:15px; padding:10px; background:#fffaf0; border-radius:10px;">
                        <span style="color:#dd6b20; font-weight:600;">Órdenes Pendientes</span>
                        <span style="font-weight:800; color:#dd6b20; font-size:1.1rem;"><?php echo $stats_prod['pendientes'] ?? 0; ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; padding:10px; background:#f0fff4; border-radius:10px;">
                        <span style="color:#38a169; font-weight:600;">Órdenes Finalizadas</span>
                        <span style="font-weight:800; color:#38a169; font-size:1.1rem;"><?php echo $stats_prod['finalizadas'] ?? 0; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>