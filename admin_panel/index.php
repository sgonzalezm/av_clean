<?php
include '../includes/session.php';
include '../includes/conexion.php';
verificarSesion();

// 1. Estadísticas Generales
$totalProductos = $pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn();
$totalUsuarios  = $pdo->query("SELECT COUNT(*) FROM usuarios_admin")->fetchColumn();
$totalFormulas  = $pdo->query("SELECT COUNT(*) FROM formulas_maestras")->fetchColumn();

// 2. Métricas de Ventas y Facturación
$mes_actual = date('m');
$stmt_ventas = $pdo->query("
    SELECT 
        SUM(total) as ingresos_totales, 
        COUNT(*) as total_pedidos,
        SUM(CASE WHEN status = 'Completado' THEN 1 ELSE 0 END) as pedidos_completados
    FROM pedidos 
    WHERE MONTH(fecha_pedido) = $mes_actual
");
$stats_ventas = $stmt_ventas->fetch();

$totalFacturado = $pdo->query("
    SELECT SUM(p.total) 
    FROM facturacion f 
    JOIN pedidos p ON f.pedido_id = p.id 
    WHERE MONTH(f.fecha_facturacion) = $mes_actual
")->fetchColumn() ?? 0;

// 3. Métricas Operativas e Inversión
$inversionProduccion = $pdo->query("
    SELECT SUM(costo_total_insumos) 
    FROM ordenes_produccion 
    WHERE MONTH(fecha_registro) = $mes_actual
")->fetchColumn() ?? 0;

$stats_prod = $pdo->query("
    SELECT 
        SUM(CASE WHEN estado = 'PENDIENTE' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'EN PROCESO' THEN 1 ELSE 0 END) as en_proceso,
        SUM(CASE WHEN estado = 'SURTIDO' THEN 1 ELSE 0 END) as finalizadas
    FROM ordenes_produccion
")->fetch();

$insumosCriticos = $pdo->query("SELECT COUNT(*) FROM insumos WHERE stock_actual <= 5")->fetchColumn();

// 4. Lógica de Rentabilidad Dinámica (Nuevas Aristas)
$ingresosMes = $stats_ventas['ingresos_totales'] ?? 0;
$balanceNeto = $ingresosMes - $inversionProduccion;

// Valor de lo que se produjo pero sigue en stock (Inversión que no es pérdida)
$valorInventarioProducido = $pdo->query("
    SELECT SUM(costo_total_insumos) 
    FROM ordenes_produccion 
    WHERE estado = 'SURTIDO' AND MONTH(fecha_registro) = $mes_actual
")->fetchColumn() ?? 0;

// IVA Acreditable (Deducción estimada del 16%)
$ivaAcreditable = $inversionProduccion * 0.16;

// 5. Leaderboard
$leaderboard = $pdo->query("
    SELECT u.nombre, SUM(p.total) as total_vendido, COUNT(p.id) as num_pedidos
    FROM pedidos p
    JOIN usuarios_admin u ON p.usuario_id = u.id
    WHERE MONTH(p.fecha_pedido) = $mes_actual AND p.status != 'Cancelado'
    GROUP BY u.id
    ORDER BY total_vendido DESC
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Dashboard | AHD Clean</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        /* Base Grid */
        .metricas-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); 
            gap: 15px; 
            margin-bottom: 25px; 
        }
        .card.kpi { padding: 20px; border-radius: 12px; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; }
        .kpi-icon { width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; }
        .kpi-data small { color: #718096; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; display: block; }
        .kpi-data .numero { font-size: 1.2rem; font-weight: 800; color: #2d3748; }
        
        /* Colores */
        .bg-blue { background: #ebf8ff; color: #3182ce; }
        .bg-green { background: #f0fff4; color: #38a169; }
        .bg-purple { background: #faf5ff; color: #805ad5; }
        .bg-orange { background: #fffaf0; color: #dd6b20; }
        .bg-red { background: #fff5f5; color: #e53e3e; }
        .bg-teal { background: #e6fffa; color: #319795; }
        .bg-yellow { background: #fffff0; color: #b7791f; }

        .dashboard-layout { display: grid; grid-template-columns: 1.5fr 1fr; gap: 20px; margin-top: 20px; }
        .status-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
        .badge-count { background: #edf2f7; padding: 2px 10px; border-radius: 10px; font-weight: bold; font-size: 0.85rem; }

        .proyeccion-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .proyeccion-item {
            padding: 15px;
            border: 1px solid #edf2f7;
            border-radius: 10px;
            background: #fdfdfd;
        }

        /* --- RESPONSIVIDAD SMARTPHONES --- */
        @media (max-width: 768px) {
            .dashboard-layout { grid-template-columns: 1fr; }
            .header { flex-direction: column; align-items: flex-start !important; gap: 15px; }
            .user-info-badge { width: 100%; text-align: center; }
            .card { overflow-x: auto; }
            table { min-width: 400px; }
            .main { padding: 15px; }
            h1 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <div class="header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <h1>Hola, <?php echo explode(' ', $_SESSION['admin_nombre'])[0]; ?> 👋</h1>
                <p style="color: #718096;">Resumen ejecutivo AHD Clean.</p>
            </div>
            <div class="user-info-badge" style="background:white; padding:10px; border-radius:10px; border:1px solid #eee;">
                <i class="fas fa-user-shield"></i> <?php echo $_SESSION['admin_rol']; ?>
            </div>
        </div>
        
        <div class="metricas-grid">
            <div class="card kpi">
                <div class="kpi-icon bg-blue"><i class="fas fa-dollar-sign"></i></div>
                <div class="kpi-data">
                    <small>Ventas Mes</small>
                    <div class="numero">$<?php echo number_format($ingresosMes, 2); ?></div>
                </div>
            </div>
            <div class="card kpi">
                <div class="kpi-icon bg-orange"><i class="fas fa-vial"></i></div>
                <div class="kpi-data">
                    <small>Inversión Producción</small>
                    <div class="numero">$<?php echo number_format($inversionProduccion, 2); ?></div>
                </div>
            </div>
            <div class="card kpi">
                <div class="kpi-icon <?php echo $balanceNeto >= 0 ? 'bg-teal' : 'bg-red'; ?>">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="kpi-data">
                    <small>Flujo Neto</small>
                    <div class="numero" style="color: <?php echo $balanceNeto >= 0 ? '#38a169' : '#e53e3e'; ?>">
                        $<?php echo number_format($balanceNeto, 2); ?>
                    </div>
                </div>
            </div>
            <div class="card kpi">
                <div class="kpi-icon bg-yellow"><i class="fas fa-boxes"></i></div>
                <div class="kpi-data">
                    <small>Capital en Stock</small>
                    <div class="numero">$<?php echo number_format($valorInventarioProducido, 2); ?></div>
                </div>
            </div>
        </div>

        <div class="dashboard-layout">
            <div class="card">
                <div style="margin-bottom: 20px;">
                    <h3 style="margin:0;"><i class="fas fa-trophy" style="color: #ecc94b;"></i> Top Vendedores</h3>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; color: #a0aec0; font-size: 0.8rem; border-bottom: 2px solid #edf2f7;">
                            <th style="padding: 10px;">Nombre</th>
                            <th>Pedidos</th>
                            <th style="text-align: right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($leaderboard as $v): ?>
                        <tr style="border-bottom: 1px solid #f8fafc;">
                            <td style="padding: 12px 0;"><strong><?php echo $v['nombre']; ?></strong></td>
                            <td><?php echo $v['num_pedidos']; ?></td>
                            <td style="text-align: right; font-weight: bold;">$<?php echo number_format($v['total_vendido'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h3><i class="fas fa-industry"></i> Operaciones</h3>
                <div style="margin: 20px 0;">
                    <div class="status-row">
                        <span><i class="fas fa-microscope" style="color:#805ad5;"></i> Fórmulas</span>
                        <span class="badge-count"><?php echo $totalFormulas; ?></span>
                    </div>
                    <div class="status-row">
                        <span><i class="fas fa-hourglass-half" style="color:#dd6b20;"></i> Pendientes</span>
                        <span class="badge-count" style="color:#dd6b20;"><?php echo $stats_prod['pendientes'] ?? 0; ?></span>
                    </div>
                    <div class="status-row">
                        <span><i class="fas fa-check-double" style="color:#38a169;"></i> Finalizadas</span>
                        <span class="badge-count" style="color:#38a169;"><?php echo $stats_prod['finalizadas'] ?? 0; ?></span>
                    </div>
                    <div class="status-row" style="border:none;">
                        <span><i class="fas fa-exclamation-triangle" style="color:#e53e3e;"></i> Stock Crítico</span>
                        <span class="badge-count" style="background:#fff5f5; color:#e53e3e;"><?php echo $insumosCriticos; ?></span>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-around; background: #f8fafc; padding: 15px; border-radius: 10px;">
                    <div style="text-align: center;">
                        <small style="display:block; color:#718096;">Productos</small>
                        <strong><?php echo $totalProductos; ?></strong>
                    </div>
                    <div style="text-align: center;">
                        <small style="display:block; color:#718096;">Admins</small>
                        <strong><?php echo $totalUsuarios; ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top: 20px;">
            <h3 style="margin-bottom: 5px;"><i class="fas fa-chart-line"></i> Proyección de Retorno sobre Lotes (Mes Actual)</h3>
            <p style="font-size: 0.85rem; color: #718096;">Estimación basada en la inversión de <strong>$<?php echo number_format($inversionProduccion, 2); ?></strong> en insumos.</p>
            
            <div class="proyeccion-container">
                <div class="proyeccion-item">
                    <small style="font-weight: 700; color: #718096;">PROYECCIÓN MAYOREO (20L)</small>
                    <div class="numero" style="font-size: 1.4rem; margin: 5px 0;">$<?php echo number_format($inversionProduccion * 1.20, 2); ?></div>
                    <span style="font-size: 0.75rem; color: #38a169; font-weight: bold;">+20% Margen Est.</span>
                </div>
                
                <div class="proyeccion-item" style="background: #f0fff4; border-color: #c6f6d5;">
                    <small style="font-weight: 700; color: #718096;">PROYECCIÓN RETAIL (1L/5L)</small>
                    <div class="numero" style="font-size: 1.4rem; margin: 5px 0;">$<?php echo number_format($inversionProduccion * 1.45, 2); ?></div>
                    <span style="font-size: 0.75rem; color: #38a169; font-weight: bold;">+45% Margen Est.</span>
                </div>

                <div class="proyeccion-item" style="background: #ebf8ff; border-color: #bee3f8;">
                    <small style="font-weight: 700; color: #718096;">IVA ACREDITABLE (Deducción)</small>
                    <div class="numero" style="font-size: 1.4rem; margin: 5px 0; color: #3182ce;">$<?php echo number_format($ivaAcreditable, 2); ?></div>
                    <span style="font-size: 0.75rem; color: #3182ce;">Saldo a favor para ISR/IVA</span>
                </div>
            </div>
        </div>
    </div>
    <script src="../js/admin.js"></script>
</body>
</html>