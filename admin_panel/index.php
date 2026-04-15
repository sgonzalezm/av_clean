<?php
include '../includes/session.php';
include '../includes/conexion.php';
verificarSesion();

// --- 1. LÓGICA DE FILTRADO DINÁMICO (Sin cambios) ---
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

try {
    // --- 2. MÉTRICAS FINANCIERAS (INTEGRADAS) ---
    // CAJA REAL: Suma de abonos (monto_pagado) registrados en el periodo
    $ingresosReales = $pdo->query("SELECT SUM(monto_pagado) FROM pedidos WHERE fecha_pedido $rango_sql")->fetchColumn() ?? 0;
    
    // CUENTAS POR COBRAR: Saldo real pendiente (Total - Pagado)
    $cuentasPorCobrar = $pdo->query("SELECT SUM(total - monto_pagado) FROM pedidos WHERE status_pago != 'Pagado'")->fetchColumn() ?? 0;
    
    // EGRESOS 1: Inversión en Producción (Original)
    $inversionProduccion = $pdo->query("SELECT SUM(costo_total_insumos) FROM ordenes_produccion WHERE fecha_registro $rango_sql")->fetchColumn() ?? 0;
    
    // EGRESOS 2: Gastos Operativos (Tabla gastos)
    $gastosOperativos = $pdo->query("SELECT SUM(monto) FROM gastos WHERE fecha_gasto $rango_sql")->fetchColumn() ?? 0;

    $egresosTotales = $inversionProduccion + $gastosOperativos;
    $balanceNeto = $ingresosReales - $egresosTotales;
    $roiReal = ($egresosTotales > 0) ? ($balanceNeto / $egresosTotales) * 100 : 0;

    // --- 3. VALORIZACIÓN DE TANQUES (Sin cambios) ---
    $sql_valor_tanques = "SELECT SUM(f.stock_litros_disponibles * COALESCE((SELECT (p.precio / p.volumen_valor) FROM productos p WHERE p.id_formula_maestra = f.id AND p.volumen_valor >= 20 ORDER BY p.volumen_valor DESC LIMIT 1), (SELECT (p2.precio / p2.volumen_valor) * 0.80 FROM productos p2 WHERE p2.id_formula_maestra = f.id ORDER BY p2.volumen_valor ASC LIMIT 1))) as valor_total FROM formulas_maestras f";
    $valorInventarioTanques = $pdo->query($sql_valor_tanques)->fetchColumn() ?? 0;

    // --- 4. MONITOR DE LOGÍSTICA (Actualizado para mostrar saldos) ---
    $stats_surtido = $pdo->query("SELECT COUNT(*) as total_pendientes FROM pedidos WHERE status_logistica = 'Por Surtir'")->fetch();
    $pendientes_surtir_lista = $pdo->query("SELECT id, cliente_id, total, status_pago, monto_pagado FROM pedidos WHERE status_logistica = 'Por Surtir' ORDER BY fecha_pedido ASC LIMIT 5")->fetchAll();

    // --- 5. PROYECCIONES (Sin cambios) ---
    $sql_proyeccion = "SELECT SUM(odp.cantidad_litros * COALESCE((SELECT precio FROM productos WHERE id_formula_maestra = p.id_formula_maestra AND volumen_valor = 1 LIMIT 1), (p.precio / p.volumen_valor))) as retail_real, SUM(odp.cantidad_litros * COALESCE((SELECT (precio / volumen_valor) FROM productos WHERE id_formula_maestra = p.id_formula_maestra AND volumen_valor >= 20 ORDER BY volumen_valor DESC LIMIT 1), (p.precio / p.volumen_valor) * 0.80 )) as wholesale_real FROM orden_detalle_productos odp JOIN productos p ON odp.id_producto = p.id JOIN ordenes_produccion op ON odp.id_orden = op.id WHERE op.fecha_registro $rango_sql";
    $proy = $pdo->query($sql_proyeccion)->fetch();
    $proyeccionRetail = $proy['retail_real'] ?? 0;
    $proyeccionWholesale = $proy['wholesale_real'] ?? 0;

    // --- 6. VENDEDORES (Sin cambios) ---
    $leaderboard = $pdo->query("SELECT u.nombre, SUM(p.total) as total_vendido FROM pedidos p JOIN usuarios_admin u ON p.usuario_id = u.id WHERE p.fecha_pedido $rango_sql GROUP BY u.id ORDER BY total_vendido DESC LIMIT 5")->fetchAll();

} catch (PDOException $e) { die("Error técnico: " . $e->getMessage()); }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>AHD Dashboard | Maestro Integrado</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        :root { --accent: #3182ce; --dark: #1e293b; }
        
        /* Ajustes base para Mobile */
        .main { transition: 0.3s; padding: 20px; }
        .header-mobile { display: none; background: var(--dark); color: white; padding: 15px; position: fixed; top: 0; left: 0; right: 0; z-index: 1000; justify-content: space-between; align-items: center; }

        .filter-bar { background: white; padding: 20px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 15px; }
        
        .metricas-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .card.kpi { padding: 15px; border-radius: 15px; background: #fff; display: flex; align-items: center; gap: 12px; border: 1px solid #f1f5f9; }
        .kpi-icon { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        
        .bg-cash { background: #f0fff4; color: #2f855a; }
        .bg-inv { background: #fffaf0; color: #dd6b20; }
        .bg-balance { background: #ebf8ff; color: #3182ce; }
        .bg-debt { background: #fff5f5; color: #c53030; }
        .bg-tank { background: #faf5ff; color: #805ad5; }
        .bg-work { background: #fefcbf; color: #b7791f; }

        .bottom-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-top: 30px; }
        .retorno-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 25px; }
        .retorno-card { background: white; padding: 20px; border-radius: 15px; border: 1px solid #e2e8f0; position: relative; overflow: hidden; }
        .retorno-card::before { content: ""; position: absolute; top: 0; left: 0; width: 4px; height: 100%; }
        .card-retail::before { background: #38a169; }
        .card-masivo::before { background: #3182ce; }
        
        .status-badge { font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; font-weight: bold; text-transform: uppercase; }
        .badge-pay { background: #c6f6d5; color: #22543d; }
        .badge-pending { background: #fed7d7; color: #822727; }

        @media (max-width: 768px) {
            .header-mobile { display: flex; }
            .main { margin-left: 0 !important; padding-top: 80px !important; }
            .sidebar { position: fixed; left: -260px; z-index: 2000; transition: 0.3s; }
            .sidebar.active { left: 0; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            #custom-dates { flex-direction: column; }
            .metricas-grid { grid-template-columns: 1fr 1fr; }
            .kpi-icon { width: 35px; height: 35px; font-size: 1rem; }
            .bottom-grid, .retorno-container { grid-template-columns: 1fr; }
            h1 { font-size: 1.5rem; }
            .hide-mobile { display: none; }
        }

        #custom-dates { display: <?php echo $periodo == 'custom' ? 'flex' : 'none'; ?>; gap: 10px; }
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1500; }
        .overlay.active { display: block; }
    </style>
</head>
<body>
    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>
    
    <div class="header-mobile">
        <button onclick="toggleSidebar()" style="background:none; border:none; color:white; font-size:1.5rem;"><i class="fas fa-bars"></i></button>
        <span style="font-weight:900;">AHD CLEAN</span>
        <i class="fas fa-user-circle"></i>
    </div>

    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <div class="header hide-mobile" style="margin-bottom: 25px;">
            <h1 style="font-weight: 800; color: #1e293b;"><i class="fas fa-chart-line"></i> Dashboard Maestro</h1>
            <p style="color: #64748b;">Periodo: <strong><?php echo $titulo_filtro; ?></strong></p>
        </div>

        <div class="filter-bar">
            <form action="" method="GET" style="display:flex; flex-direction:column; gap:10px; flex-grow:1;">
                <label style="font-size:0.75rem; font-weight:bold; color:#718096;">SELECCIONAR PERIODO</label>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <select name="periodo" id="periodoSelect" onchange="toggleDates(this.value)" style="padding:10px; border-radius:10px; border:1px solid #cbd5e0; flex-grow:1;">
                        <option value="mes" <?php echo $periodo == 'mes' ? 'selected' : ''; ?>>Este Mes</option>
                        <option value="trimestre" <?php echo $periodo == 'trimestre' ? 'selected' : ''; ?>>Trimestral</option>
                        <option value="custom" <?php echo $periodo == 'custom' ? 'selected' : ''; ?>>Personalizado</option>
                    </select>
                    <button type="submit" style="background:var(--accent); color:white; border:none; padding:10px 20px; border-radius:10px; cursor:pointer; font-weight:bold;">Aplicar</button>
                </div>
                <div id="custom-dates">
                    <input type="date" name="f_inicio" value="<?php echo $fecha_inicio; ?>" style="padding:10px; border-radius:10px; border:1px solid #cbd5e0; flex-grow:1;">
                    <input type="date" name="f_fin" value="<?php echo $fecha_fin; ?>" style="padding:10px; border-radius:10px; border:1px solid #cbd5e0; flex-grow:1;">
                </div>
            </form>
        </div>

        <div class="metricas-grid">
            <div class="card kpi">
                <div class="kpi-icon bg-cash"><i class="fas fa-hand-holding-usd"></i></div>
                <div><small style="font-weight:bold; color:#64748b; font-size:0.6rem;">CAJA REAL</small><div style="font-size:1rem; font-weight:800;">$<?php echo number_format($ingresosReales, 2); ?></div></div>
            </div>
            <div class="card kpi">
                <div class="kpi-icon bg-inv"><i class="fas fa-flask"></i></div>
                <div><small style="font-weight:bold; color:#64748b; font-size:0.6rem;">EGRESOS</small><div style="font-size:1rem; font-weight:800;">$<?php echo number_format($egresosTotales, 2); ?></div></div>
            </div>
            <div class="card kpi" style="border: 1px solid #bee3f8;">
                <div class="kpi-icon bg-balance"><i class="fas fa-balance-scale"></i></div>
                <div><small style="font-weight:bold; color:#3182ce; font-size:0.6rem;">BALANCE</small>
                <div style="font-size:1rem; font-weight:800;">$<?php echo number_format($balanceNeto, 2); ?></div></div>
            </div>
            <div class="card kpi">
                <div class="kpi-icon bg-debt"><i class="fas fa-file-invoice-dollar"></i></div>
                <div><small style="color:#c53030; font-size:0.6rem; font-weight:bold;">POR COBRAR</small><div style="font-size:1rem; font-weight:800;">$<?php echo number_format($cuentasPorCobrar, 2); ?></div></div>
            </div>
            <div class="card kpi">
                <div class="kpi-icon bg-tank"><i class="fas fa-gas-pump"></i></div>
                <div><small style="color:#805ad5; font-size:0.6rem; font-weight:bold;">TANQUES</small><div style="font-size:1rem; font-weight:800;">$<?php echo number_format($valorInventarioTanques, 2); ?></div></div>
            </div>
            <div class="card kpi" style="background:#fffaf0;">
                <div class="kpi-icon bg-work"><i class="fas fa-dolly-flatbed"></i></div>
                <div><small style="color:#b7791f; font-size:0.6rem; font-weight:bold;">SURTIR</small><div style="font-size:1rem; font-weight:800;"><?php echo $stats_surtido['total_pendientes']; ?></div></div>
            </div>
        </div>

        <div class="retorno-container">
            <div class="retorno-card card-retail">
                <small style="color:#38a169; font-weight:800;">RETAIL (1L)</small>
                <div style="font-size: 1.4rem; font-weight:900; color:#1e293b;">$<?php echo number_format($proyeccionRetail, 2); ?></div>
            </div>
            <div class="retorno-card card-masivo">
                <small style="color:#3182ce; font-weight:800;">MAYOREO (+20L)</small>
                <div style="font-size: 1.4rem; font-weight:900; color:#1e293b;">$<?php echo number_format($proyeccionWholesale, 2); ?></div>
            </div>
        </div>

        <div class="bottom-grid">
            <div class="card" style="background:white; padding:20px; border-radius:15px; border: 1px solid #f1f5f9;">
                <h3><i class="fas fa-trophy" style="color:#ecc94b;"></i> Top Vendedores</h3>
                <table style="width:100%; border-collapse:collapse; margin-top:10px;">
                    <?php foreach($leaderboard as $v): ?>
                        <tr style="border-bottom:1px solid #f8fafc;"><td style="padding:10px 0; font-size: 0.85rem;"><strong><?php echo htmlspecialchars($v['nombre']); ?></strong></td><td style="text-align:right; font-weight:bold; color:#1e293b;">$<?php echo number_format($v['total_vendido'], 2); ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <div class="card" style="background:white; padding:20px; border-radius:15px; border: 1px solid #f6e05e;">
                <h3><i class="fas fa-history"></i> Monitor Surtido</h3>
                <table style="width:100%; border-collapse:collapse; margin-top:10px;">
                    <?php if(empty($pendientes_surtir_lista)): ?>
                        <tr><td style="color:#94a3b8; font-size:0.8rem; padding:15px; text-align:center;">Todo surtido.</td></tr>
                    <?php else: ?>
                        <?php foreach($pendientes_surtir_lista as $p): 
                            $saldo = $p['total'] - $p['monto_pagado'];
                        ?>
                            <tr style="border-bottom:1px solid #fefcbf;">
                                <td style="padding:8px 0;">
                                    <small style="color:#64748b; font-weight:bold;">#<?php echo $p['id']; ?></small> <span style="font-size:0.8rem;">ID: <?php echo $p['cliente_id']; ?></span><br>
                                    <span class="status-badge <?php echo ($saldo <= 0) ? 'badge-pay' : 'badge-pending'; ?>">
                                        <?php echo ($saldo <= 0) ? 'PAGADO' : 'DEBE $'.number_format($saldo, 2); ?>
                                    </span>
                                </td>
                                <td style="text-align:right; font-weight:bold; color:#1e293b; font-size:0.9rem;">$<?php echo number_format($p['total'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <script>
        function toggleDates(val) {
            const dateBox = document.getElementById('custom-dates');
            dateBox.style.display = (val === 'custom') ? 'flex' : 'none';
        }

        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('active');
        }
    </script>
</body>
</html>