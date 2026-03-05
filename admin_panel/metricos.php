<?php
session_start();
include '../includes/conexion.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    
    // --- 1. MÉTRICAS EXISTENTES ---
    $meta_mensual = 50000;
    $stmt = $pdo->query("SELECT SUM(total) as total_mes FROM pedidos WHERE MONTH(fecha_pedido) = MONTH(CURRENT_DATE()) AND status != 'Cancelado'");
    $venta_mes = $stmt->fetch()['total_mes'] ?? 0;
    $porcentaje_meta = min(($venta_mes / $meta_mensual) * 100, 100);

    // --- 2. MÉTRICAS DE FACTURACIÓN (NUEVO) ---
    // Sumamos el total de los pedidos que tienen un registro en la tabla 'facturacion' este mes
    $stmt_facturado = $pdo->query("
        SELECT SUM(p.total) as total_facturado 
        FROM facturacion f 
        JOIN pedidos p ON f.pedido_id = p.id 
        WHERE MONTH(f.fecha_facturacion) = MONTH(CURRENT_DATE())
    ");
    $venta_facturada_mes = $stmt_facturado->fetch()['total_facturado'] ?? 0;
    
    // Porcentaje de facturación respecto a la venta total
    $porcentaje_facturacion = ($venta_mes > 0) ? ($venta_facturada_mes / $venta_mes) * 100 : 0;

    // --- 3. TOP PRODUCTOS ---
    $stmt = $pdo->query("SELECT p.nombre, SUM(dp.cantidad) as total_vendido 
                        FROM detalle_pedido dp 
                        JOIN productos p ON dp.producto_id = p.id 
                        GROUP BY p.id ORDER BY total_vendido DESC LIMIT 5");
    $top_productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 4. COMISIONES Y NIVEL ---
    $comision_acumulada = $venta_mes * 0.03;
    $nivel = "Bronce"; $color_nivel = "#cd7f32";
    if($venta_mes > 20000) { $nivel = "Plata"; $color_nivel = "#C0C0C0"; }
    if($venta_mes > 40000) { $nivel = "Oro"; $color_nivel = "#FFD700"; }

} catch(PDOException $e) { die("Error: " . $e->getMessage()); }
?>

<!DOCTYPE html>
<html>
<head>
    <title>Métricas de Facturación</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<!-- Botón toggle para móvil -->
    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <div class="header">
            <h1><i class="fas fa-chart-line"></i> Dashboard de Métricas</h1>
            <div class="meta-dias">
                <i class="far fa-calendar-alt"></i> <?php echo date('t') - date('d'); ?> días restantes
            </div>
        </div>

        <div class="metricas-grid">
            <div class="card">
                <h3>Venta Mensual</h3>
                <p class="monto">$<?php echo number_format($venta_mes, 2); ?></p>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $porcentaje_meta; ?>%"></div>
                </div>
                <small><?php echo round($porcentaje_meta, 1); ?>% de la meta</small>
            </div>

            <div class="card card-facturacion" style="border-left: 5px solid #4fd1c5;">
                <h3>Facturación (Mes)</h3>
                <p class="monto" style="color: #2c7a7b;">$<?php echo number_format($venta_facturada_mes, 2); ?></p>
                <div class="progress-bar" style="background: #e6fffa;">
                    <div class="progress-fill" style="width: <?php echo $porcentaje_facturacion; ?>%; background: #4fd1c5;"></div>
                </div>
                <small><?php echo round($porcentaje_facturacion, 1); ?>% del total vendido facturado</small>
            </div>

            <div class="card card-nivel" style="border-top: 5px solid <?php echo $color_nivel; ?>">
                <h3>Mi Nivel: <span style="color: <?php echo $color_nivel; ?>"><?php echo $nivel; ?></span></h3>
                <p class="monto">$<?php echo number_format($comision_acumulada, 2); ?></p>
                <small>Comisión acumulada total</small>
            </div>
        </div>

        <div class="graficos-grid">
            <div class="card-grafico">
                <h3><i class="fas fa-box-open"></i> Mezcla de Ventas</h3>
                <div style="height: 250px;">
                    <canvas id="chartProductos"></canvas>
                </div>
            </div>

            <div class="card-grafico">
                <h3><i class="fas fa-file-invoice-dollar"></i> Ventas vs Facturado</h3>
                <div style="height: 250px;">
                    <canvas id="chartFactura"></canvas>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Reutilizando tus estilos y añadiendo ajustes para los gráficos */
        .metricas-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .graficos-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-top: 20px; }
        .card, .card-grafico { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .monto { font-size: 2rem; font-weight: 800; color: #2d3748; margin: 10px 0; }
        .progress-bar { background: #edf2f7; height: 12px; border-radius: 6px; margin: 12px 0; overflow: hidden; }
        .progress-fill { background: #48bb78; height: 100%; transition: width 0.8s ease-out; }
    </style>

    <script>
        // Gráfico 1: Mezcla de Productos
        const ctxP = document.getElementById('chartProductos').getContext('2d');
        new Chart(ctxP, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($top_productos, 'nombre')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($top_productos, 'total_vendido')); ?>,
                    backgroundColor: ['#4299e1', '#48bb78', '#ecc94b', '#ed64a6', '#9f7aea']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });

        // Gráfico 2: Comparativa Facturación (NUEVO)
        const ctxF = document.getElementById('chartFactura').getContext('2d');
        new Chart(ctxF, {
            type: 'bar',
            data: {
                labels: ['Este Mes'],
                datasets: [
                    {
                        label: 'Venta Total',
                        data: [<?php echo $venta_mes; ?>],
                        backgroundColor: '#a0aec0'
                    },
                    {
                        label: 'Facturado',
                        data: [<?php echo $venta_facturada_mes; ?>],
                        backgroundColor: '#4fd1c5'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } }
            }
        });
    </script>
    <script src="../js/admin.js"></script>
</body>
</html>