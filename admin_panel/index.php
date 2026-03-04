<?php
include '../includes/session.php';
include '../includes/conexion.php';
verificarSesion();

// 1. Estadísticas Generales (Conteo)
$totalProductos = $pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn();
$totalUsuarios  = $pdo->query("SELECT COUNT(*) FROM usuarios_admin")->fetchColumn();

// 2. Métricas de Ventas y Facturación (Toma de decisiones)
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

// 3. Facturación Real (Dinero que ya tiene factura)
$totalFacturado = $pdo->query("
    SELECT SUM(p.total) 
    FROM facturacion f 
    JOIN pedidos p ON f.pedido_id = p.id 
    WHERE MONTH(f.fecha_facturacion) = $mes_actual
")->fetchColumn() ?? 0;
/*
// 4. LEADERBOARD de Vendedores (Top 5 del mes)
$leaderboard = $pdo->query("
    SELECT u.nombre, SUM(p.total) as total_vendido, COUNT(p.id) as num_pedidos
    FROM pedidos p
    JOIN usuarios_admin u ON p.usuario_id = u.id
    WHERE MONTH(p.fecha_pedido) = $mes_actual AND p.status != 'Cancelado'
    GROUP BY u.id
    ORDER BY total_vendido DESC
    LIMIT 5
")->fetchAll();*/
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Dashboard | Torre de Control</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <div class="header">
            <div>
                <h1>Hola, <?php echo explode(' ', $_SESSION['admin_nombre'])[0]; ?> 👋</h1>
                <p style="color: #718096;">Aquí tienes el resumen ejecutivo de este mes.</p>
            </div>
            <div class="user-info-badge">
                <i class="fas fa-user-shield"></i> <?php echo $_SESSION['admin_rol']; ?>
            </div>
        </div>
        
        <div class="metricas-grid">
            <div class="card kpi">
                <div class="kpi-icon" style="background: #ebf8ff; color: #3182ce;"><i class="fas fa-dollar-sign"></i></div>
                <div class="kpi-data">
                    <small>Ventas Totales (Mes)</small>
                    <div class="numero">$<?php echo number_format($stats_ventas['ingresos_totales'], 2); ?></div>
                </div>
            </div>
            <div class="card kpi">
                <div class="kpi-icon" style="background: #e6fffa; color: #38a169;"><i class="fas fa-file-invoice-dollar"></i></div>
                <div class="kpi-data">
                    <small>Facturación Real</small>
                    <div class="numero">$<?php echo number_format($totalFacturado, 2); ?></div>
                </div>
            </div>
            <div class="card kpi">
                <div class="kpi-icon" style="background: #faf5ff; color: #805ad5;"><i class="fas fa-shopping-bag"></i></div>
                <div class="kpi-data">
                    <small>Pedidos Totales</small>
                    <div class="numero"><?php echo $stats_ventas['total_pedidos']; ?></div>
                </div>
            </div>
        </div>

        <div class="dashboard-content" style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 20px; margin-top: 20px;">
            
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin:0;"><i class="fas fa-trophy" style="color: #ecc94b;"></i> Top Vendedores del Mes</h3>
                    <a href="metricos.php" class="btn-link">Ver todos</a>
                </div>
                <table class="tabla-leaderboard">
                    <thead>
                        <tr>
                            <th>Vendedor</th>
                            <th>Pedidos</th>
                            <th style="text-align: right;">Total Vendido</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($leaderboard as $index => $v): ?>
                        <tr>
                            <td>
                                <span class="rank-pos"><?php echo $index + 1; ?></span>
                                <strong><?php echo $v['nombre']; ?></strong>
                            </td>
                            <td><?php echo $v['num_pedidos']; ?></td>
                            <td style="text-align: right; font-weight: bold; color: #2d3748;">
                                $<?php echo number_format($v['total_vendido'], 2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h3><i class="fas fa-warehouse"></i> Resumen Operativo</h3>
                <div class="stats-mini-row">
                    <div class="stat-item">
                        <span>Productos</span>
                        <strong><?php echo $totalProductos; ?></strong>
                    </div>
                    <div class="stat-item">
                        <span>Admins</span>
                        <strong><?php echo $totalUsuarios; ?></strong>
                    </div>
                </div>
                <hr style="border: 0; border-top: 1px solid #edf2f7; margin: 20px 0;">
                <p style="font-size: 0.85rem; color: #718096;">
                    <i class="fas fa-info-circle"></i> Los datos de facturación se actualizan en tiempo real conforme se procesan los pedidos.
                </p>
            </div>

        </div>
    </div>

    <style>
        .metricas-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; }
        .kpi { display: flex; align-items: center; gap: 15px; padding: 25px !important; }
        .kpi-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .kpi-data small { color: #718096; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
        
        .tabla-leaderboard { width: 100%; border-collapse: collapse; }
        .tabla-leaderboard th { text-align: left; padding: 12px; color: #a0aec0; font-size: 0.8rem; text-transform: uppercase; }
        .tabla-leaderboard td { padding: 15px 12px; border-bottom: 1px solid #edf2f7; }
        
        .rank-pos { background: #edf2f7; width: 25px; height: 25px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 0.8rem; margin-right: 10px; color: #4a5568; font-weight: bold; }
        
        .stats-mini-row { display: flex; justify-content: space-around; margin-top: 20px; }
        .stat-item { text-align: center; }
        .stat-item span { display: block; font-size: 0.8rem; color: #a0aec0; }
        .stat-item strong { font-size: 1.5rem; color: #2d3748; }

        .user-info-badge { background: #fff; padding: 8px 15px; border-radius: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); font-weight: 600; font-size: 0.9rem; color: #4a5568; border: 1px solid #edf2f7; }
        .btn-link { font-size: 0.85rem; color: #3182ce; text-decoration: none; font-weight: bold; }
    </style>

    <script src="../js/admin.js"></script>
</body>
</html>