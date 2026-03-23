<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// 1. CONSULTA DE CABECERAS DE ÓRDENES
$sql_ordenes = "SELECT * FROM ordenes_produccion ORDER BY fecha_registro DESC";
$ordenes = $pdo->query($sql_ordenes)->fetchAll();

// 2. LÓGICA PARA VER DETALLE (Si se solicita por AJAX o URL)
$detalle_id = $_GET['ver'] ?? null;
$productos_detalle = [];
$insumos_detalle = [];

if ($detalle_id) {
    // Obtener productos fabricados en esa orden
    $stmt_p = $pdo->prepare("SELECT odp.*, p.nombre FROM orden_detalle_productos odp JOIN productos p ON odp.id_producto = p.id WHERE odp.id_orden = ?");
    $stmt_p->execute([$detalle_id]);
    $productos_detalle = $stmt_p->fetchAll();

    // Obtener insumos usados en esa orden
    $stmt_i = $pdo->prepare("SELECT odi.*, i.nombre, i.unidad_medida FROM orden_detalle_insumos odi JOIN insumos i ON odi.id_insumo = i.id WHERE odi.id_orden = ?");
    $stmt_i->execute([$detalle_id]);
    $insumos_detalle = $stmt_i->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Producción | AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .main { padding: 20px; }
        .status-badge { background: #ebf8ff; color: #2b6cb0; padding: 4px 10px; border-radius: 15px; font-size: 0.75rem; font-weight: bold; }
        .card-detalle { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .grid-detalle { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; background: #f8fafc; padding: 12px; font-size: 0.85rem; color: #64748b; border-bottom: 2px solid #edf2f7; }
        td { padding: 12px; border-bottom: 1px solid #edf2f7; font-size: 0.9rem; }
        .btn-ver { color: #4c51bf; text-decoration: none; font-weight: bold; }
        .btn-ver:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <h1><i class="fas fa-history"></i> Historial de Producción</h1>
        <p style="color: #718096; margin-bottom: 25px;">Consulta las órdenes ejecutadas y el consumo de materia prima histórico.</p>

        <?php if ($detalle_id): ?>
        <div class="card-detalle">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3>Detalle de la Orden #<?php echo $detalle_id; ?></h3>
                <a href="historial_ordenes.php" style="color: #e53e3e; text-decoration:none;"><i class="fas fa-times"></i> Cerrar Detalle</a>
            </div>
            <hr style="border:0; border-top:1px solid #eee; margin: 15px 0;">
            
            <div class="grid-detalle">
                <div>
                    <h4 style="color:#2b6cb0;"><i class="fas fa-box"></i> Productos Obtenidos</h4>
                    <table>
                        <thead><tr><th>Producto</th><th>Litros</th></tr></thead>
                        <tbody>
                            <?php foreach($productos_detalle as $pd): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pd['nombre']); ?></td>
                                <td><?php echo number_format($pd['cantidad_litros'], 2); ?> Lts</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div>
                    <h4 style="color:#38a169;"><i class="fas fa-flask"></i> Insumos Utilizados</h4>
                    <table>
                        <thead><tr><th>Insumo</th><th>Cantidad</th><th>Costo Unit.</th></tr></thead>
                        <tbody>
                            <?php foreach($insumos_detalle as $id): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($id['nombre']); ?></td>
                                <td><?php echo number_format($id['cantidad_usada'], 3); ?> <?php echo $id['unidad_medida']; ?></td>
                                <td>$<?php echo number_format($id['precio_al_momento'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card" style="background:white; border-radius:10px; border:1px solid #e2e8f0; overflow:hidden;">
            <table style="width:100%;">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Fecha y Hora</th>
                        <th>Inversión Insumos</th>
                        <th>Observaciones</th>
                        <th style="text-align:right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ordenes)): ?>
                        <tr><td colspan="5" style="text-align:center; padding:30px; color:#a0aec0;">No hay registros de producción todavía.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($ordenes as $o): ?>
                    <tr <?php if($detalle_id == $o['id']) echo 'style="background:#f0f7ff;"'; ?>>
                        <td><strong>#<?php echo $o['id']; ?></strong></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($o['fecha_registro'])); ?></td>
                        <td style="color: #2f855a; font-weight:bold;">$<?php echo number_format($o['costo_total_insumos'], 2); ?></td>
                        <td style="color: #718096; font-size: 0.8rem;"><?php echo htmlspecialchars($o['observaciones']); ?></td>
                        <td style="text-align:right;">
                            <a href="?ver=<?php echo $o['id']; ?>" class="btn-ver">
                                <i class="fas fa-eye"></i> Ver Detalle
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="../js/admin.js"></script>
</body>
</html>