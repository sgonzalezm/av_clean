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

try {
    // --- 2. MÉTRICAS FINANCIERAS (Inversión vs Recuperación) ---
    // Ingresos Reales: Dinero en mano (Pagado)
    $ingresosReales = $pdo->query("SELECT SUM(total) FROM pedidos WHERE fecha_pedido $rango_sql AND status_pago = 'Pagado'")->fetchColumn() ?? 0;

    // Cuentas por Cobrar: Dinero en la calle (Pendientes y Créditos)
    $cuentasPorCobrar = $pdo->query("SELECT SUM(total) FROM pedidos WHERE status_pago IN ('Pendiente', 'Crédito')")->fetchColumn() ?? 0;

    // Inversión en Producción: Costo de químicos usados en órdenes del periodo
    $inversionProduccion = $pdo->query("SELECT SUM(costo_total_insumos) FROM ordenes_produccion WHERE fecha_registro $rango_sql")->fetchColumn() ?? 0;

    // BALANCE NETO: Diferencia entre lo que cobraste y lo que gastaste en fabricar
    $balanceNeto = $ingresosReales - $inversionProduccion;
    
    // % de Recuperación: ROI sobre la inversión fabricada
    $roiReal = ($inversionProduccion > 0) ? ($balanceNeto / $inversionProduccion) * 100 : 0;

    // --- 3. VALORIZACIÓN DE TANQUES ---
    $sql_valor_tanques = "
        SELECT SUM(f.stock_litros_disponibles * COALESCE(
            (SELECT (p.precio / p.volumen_valor) FROM productos p WHERE p.id_formula_maestra = f.id AND p.volumen_valor >= 20 ORDER BY p.volumen_valor DESC LIMIT 1),
            (SELECT (p2.precio / p2.volumen_valor) * 0.80 FROM productos p2 WHERE p2.id_formula_maestra = f.id ORDER BY p2.volumen_valor ASC LIMIT 1)
        )) as valor_total
    FROM formulas_maestras f";
    $valorInventarioTanques = $pdo->query($sql_valor_tanques)->fetchColumn() ?? 0;

    // --- 4. MONITOR DE LOGÍSTICA ---
    $stats_surtido = $pdo->query("SELECT COUNT(*) as total_pendientes FROM pedidos WHERE status_logistica = 'Por Surtir'")->fetch();
    $pendientes_surtir_lista = $pdo->query("SELECT id, cliente_id, total, status_pago FROM pedidos WHERE status_logistica = 'Por Surtir' ORDER BY fecha_pedido ASC LIMIT 5")->fetchAll();

    // --- 5. PROYECCIONES ---
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
        WHERE op.fecha_registro $rango_sql";
    
    $proy = $pdo->query($sql_proyeccion)->fetch();
    $proyeccionRetail = $proy['retail_real'] ?? 0;
    $proyeccionWholesale = $proy['wholesale_real'] ?? 0;

    // --- 6. VENDEDORES ---
    $leaderboard = $pdo->query("SELECT u.nombre, SUM(p.total) as total_vendido FROM pedidos p JOIN usuarios_admin u ON p.usuario_id = u.id WHERE p.fecha_pedido $rango_sql GROUP BY u.id ORDER BY total_vendido DESC LIMIT 5")->fetchAll();

} catch (PDOException $e) {
    die("Error técnico: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Dashboard Maestro | AHD Clean</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .filter-bar { background: white; padding: 20px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .metricas-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .card.kpi { padding: 15px; border-radius: 15px; background: #fff; display: flex; align-items: center; gap: 12px; border: 1px solid #f1f5f9; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .kpi-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
        
        .bg-cash { background: #f0fff4; color: #2f855a; } /* Verde Ingreso */
        .bg-inv { background: #fffaf0; color: #dd6b20; }  /* Naranja Inversión */
        .bg-balance { background: #ebf8ff; color: #3182ce; } /* Azul Balance */
        .bg-debt { background: #fff5f5; color: #c53030; }  /* Rojo Cobranza */
        .bg-tank { background: #faf5ff; color: #805ad5; }  /* Morado Tanque */
        .bg-work { background: #fefcbf; color: #b7791f; }  /* Amarillo Surtido */

        .bottom-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-top: 30px; }
        .status-badge { font-size: 0.6rem; padding: 2px 5px; border-radius: 4px; font-weight: bold; text-transform: uppercase; margin-right: 4px; }
        .badge-pay { background: #c6f6d5; color: #22543d; }
        .badge-pending { background: #fed7d7; color: #822727; }

        .retorno-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 25px; }
        .retorno-card { background: white; padding: 20px; border-radius: 15px; border: 1px solid #e2e8f0; position: relative; overflow: hidden; }
        .retorno-card::before { content: ""; position: absolute; top: 0; left: 0; width: 4px; height: 100%; }
        .card-retail::before { background: #38a169; }
        .card-masivo::before { background: #3182ce; }
        
        #custom-dates { display: <?php echo $periodo == 'custom' ? 'flex' : 'none'; ?>; gap: 10px; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <div class="header" style="margin-bottom: 25px;">
            <h1 style="font-weight: 800; color: #1e293b;"><i class="fas fa-chart-line"></i> Dashboard Maestro</h1>
            <p style="color: #64748b;">Filtro: <strong><?php echo $titulo_filtro; ?></strong></p>
        </div>

        <div class="filter-bar">
            <form action="" method="GET" style="display:flex; gap:10px; align-items:flex-end;">
                <div style="display:flex; flex-direction:column; gap:5px;">
                    <label style="font-size:0.7rem; font-weight:bold; color:#718096;">PERIODO</label>
                    <select name="periodo" id="periodoSelect" class="filter-input" onchange="toggleDates(this.value)" style="padding:8px; border-radius:8px; border:1px solid #cbd5e0; background:white;">
                        <option value="mes" <?php echo $periodo == 'mes' ? 'selected' : ''; ?>>Este Mes</option>
                        <option value="trimestre" <?php echo $periodo == 'trimestre' ? 'selected' : ''; ?>>Últimos 3 Meses</option>
                        <option value="custom" <?php echo $periodo == 'custom' ? 'selected' : ''; ?>>Rango Manual</option>
                    </select>
                </div>
                <div id="custom-dates">
                    <input type="date" name="f_inicio" value="<?php echo $fecha_inicio; ?>" style="padding:7px; border-radius:8px; border:1px solid #cbd5e0;">
                    <input type="date" name="f_fin" value="<?php echo $fecha_fin; ?>" style="padding:7px; border-radius:8px; border:1px solid #cbd5e0;">
                </div>
                <button type="submit" style="background:#3182ce; color:white; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; font-weight:bold;">Filtrar</button>
            </form>
            <div style="text-align:right; font-size: 0.85rem; color: #64748b;">
                <strong><?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?></strong>
            </div>
        </div>

        <div class="metricas-grid">
            <div class="card kpi">
                <div class="kpi-icon bg-cash"><i class="fas fa-hand-holding-usd"></i></div>
                <div><small style="font-weight:bold; color:#64748b; font-size:0.6rem;">CAJA REAL</small><div style="font-size:1.1rem; font-weight:800;">$<?php echo number_format($ingresosReales, 2); ?></div></div>
            </div>
            <div class="card kpi">
                <div class="kpi-icon bg-inv"><i class="fas fa-flask"></i></div>
                <div><small style="font-weight:bold; color:#64748b; font-size:0.6rem;">INVERSIÓN PROD.</small><div style="font-size:1.1rem; font-weight:800;">$<?php echo number_format($inversionProduccion, 2); ?></div></div>
            </div>
            <div class="card kpi" style="border: 2px solid #bee3f8;">
                <div class="kpi-icon bg-balance"><i class="fas fa-balance-scale"></i></div>
                <div><small style="font-weight:bold; color:#3182ce; font-size:0.6rem;">BALANCE NETO</small>
                <div style="font-size:1.1rem; font-weight:800;">$<?php echo number_format($balanceNeto, 2); ?></div>
                <span style="font-size:0.65rem; color:#3182ce; font-weight:bold;">Recuperado: <?php echo number_format($roiReal, 1); ?>%</span>
                </div>
            </div>
            <div class="card kpi">
                <div class="kpi-icon bg-debt"><i class="fas fa-file-invoice-dollar"></i></div>
                <div><small style="color:#c53030; font-size:0.6rem; font-weight:bold;">POR COBRAR</small><div style="font-size:1.1rem; font-weight:800;">$<?php echo number_format($cuentasPorCobrar, 2); ?></div></div>
            </div>
            <div class="card kpi">
                <div class="kpi-icon bg-tank"><i class="fas fa-gas-pump"></i></div>
                <div><small style="color:#805ad5; font-size:0.6rem; font-weight:bold;">VALOR TANQUES</small><div style="font-size:1.1rem; font-weight:800;">$<?php echo number_format($valorInventarioTanques, 2); ?></div></div>
            </div>
            <div class="card kpi" style="background:#fffaf0;">
                <div class="kpi-icon bg-work"><i class="fas fa-dolly-flatbed"></i></div>
                <div><small style="color:#b7791f; font-size:0.6rem; font-weight:bold;">POR SURTIR</small><div style="font-size:1.1rem; font-weight:800;"><?php echo $stats_surtido['total_pendientes']; ?> Pedidos</div></div>
            </div>
        </div>

        <div class="retorno-container">
            <div class="retorno-card card-retail">
                <small style="color:#38a169; font-weight:800;">ESCENARIO RETAIL (1L)</small>
                <div style="font-size: 1.6rem; font-weight:900; color:#1e293b; margin: 5px 0;">$<?php echo number_format($proyeccionRetail, 2); ?></div>
                <span style="color:#38a169; font-size:0.75rem; font-weight:bold;">Utilidad Potencial: $<?php echo number_format($proyeccionRetail - $inversionProduccion, 2); ?></span>
            </div>
            <div class="retorno-card card-masivo">
                <small style="color:#3182ce; font-weight:800;">ESCENARIO MAYOREO (+20L)</small>
                <div style="font-size: 1.6rem; font-weight:900; color:#1e293b; margin: 5px 0;">$<?php echo number_format($proyeccionWholesale, 2); ?></div>
                <span style="color:#3182ce; font-size:0.75rem; font-weight:bold;">Utilidad Potencial: $<?php echo number_format($proyeccionWholesale - $inversionProduccion, 2); ?></span>
            </div>
        </div>

        <div class="bottom-grid">
            <div class="card" style="background:white; padding:20px; border-radius:15px; border: 1px solid #f1f5f9;">
                <h3 style="font-size: 1rem; margin-bottom: 15px;"><i class="fas fa-trophy" style="color:#ecc94b;"></i> Top Vendedores</h3>
                <table style="width:100%; border-collapse:collapse;">
                    <?php foreach($leaderboard as $v): ?>
                        <tr style="border-bottom:1px solid #f8fafc;"><td style="padding:12px 0; font-size: 0.9rem;"><strong><?php echo htmlspecialchars($v['nombre']); ?></strong></td><td style="text-align:right; font-weight:bold; color:#1e293b;">$<?php echo number_format($v['total_vendido'], 2); ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <div class="card" style="background:white; padding:20px; border-radius:15px; border: 1px solid #f6e05e;">
                <h3 style="font-size: 1rem; margin-bottom: 15px; color:#b7791f;"><i class="fas fa-history"></i> Monitor de Surtido</h3>
                <table style="width:100%; border-collapse:collapse;">
                    <?php if(empty($pendientes_surtir_lista)): ?>
                        <tr><td style="color:#94a3b8; font-size:0.9rem; padding:20px; text-align:center;">Todo surtido.</td></tr>
                    <?php else: ?>
                        <?php foreach($pendientes_surtir_lista as $p): ?>
                            <tr style="border-bottom:1px solid #fefcbf;">
                                <td style="padding:10px 0;">
                                    <small style="color:#64748b; font-weight:bold;">#<?php echo $p['id']; ?></small> <span style="font-size:0.9rem;">ID Cliente: <?php echo $p['cliente_id']; ?></span><br>
                                    <span class="status-badge <?php echo ($p['status_pago'] == 'Pagado') ? 'badge-pay' : 'badge-pending'; ?>"><?php echo $p['status_pago']; ?></span>
                                </td>
                                <td style="text-align:right; font-weight:bold; color:#1e293b;">$<?php echo number_format($p['total'], 2); ?></td>
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
            if (val === 'custom') {
                dateBox.style.display = 'flex';
            } else {
                dateBox.style.display = 'none';
                document.getElementById('periodoSelect').form.submit();
            }
        }
    </script>
</body>
</html>