<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$mensaje = "";

// --- LÓGICA PARA RECIBIR INSUMOS DESDE EL HISTORIAL ---
if (isset($_POST['recibir_insumos_orden'])) {
    $id_orden = $_POST['id_orden'];
    
    try {
        $pdo->beginTransaction();

        // 1. Obtener los insumos de esta orden específica
        $stmt_ins = $pdo->prepare("SELECT id_insumo, cantidad_usada FROM orden_detalle_insumos WHERE id_orden = ?");
        $stmt_ins->execute([$id_orden]);
        $insumos = $stmt_ins->fetchAll();

        // 2. Cargar cada insumo al stock actual
        $stmt_update = $pdo->prepare("UPDATE insumos SET stock_actual = COALESCE(stock_actual, 0) + ? WHERE id = ?");
        foreach ($insumos as $ins) {
            $stmt_update->execute([$ins['cantidad_usada'], $ins['id_insumo']]);
        }

        // 3. Cambiar estado de la orden a 'LISTO' (O el estado que prefieras para fabricar)
        $stmt_status = $pdo->prepare("UPDATE ordenes_produccion SET estado = 'SURTIDO', observaciones = CONCAT(observaciones, ' | Insumos recibidos el ', NOW()) WHERE id = ?");
        $stmt_status->execute([$id_orden]);

        $pdo->commit();
        $mensaje = "<div class='alert exito'>¡Insumos de la Orden #$id_orden cargados al stock con éxito!</div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = "<div class='alert error'>Error: " . $e->getMessage() . "</div>";
    }
}

// 1. CONSULTA DE CABECERAS (Incluyendo la columna 'estado')
$sql_ordenes = "SELECT * FROM ordenes_produccion ORDER BY fecha_registro DESC";
$ordenes = $pdo->query($sql_ordenes)->fetchAll();

// 2. LÓGICA PARA VER DETALLE
$detalle_id = $_GET['ver'] ?? null;
$productos_detalle = [];
$insumos_detalle = [];

if ($detalle_id) {
    $stmt_p = $pdo->prepare("SELECT odp.*, p.nombre FROM orden_detalle_productos odp JOIN productos p ON odp.id_producto = p.id WHERE odp.id_orden = ?");
    $stmt_p->execute([$detalle_id]);
    $productos_detalle = $stmt_p->fetchAll();

    $stmt_i = $pdo->prepare("SELECT odi.*, i.nombre, i.unidad_medida FROM orden_detalle_insumos odi JOIN insumos i ON odi.id_insumo = i.id WHERE odi.id_orden = ?");
    $stmt_i->execute([$detalle_id]);
    $insumos_detalle = $stmt_i->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial | AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .main { padding: 20px; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; }
        .exito { background: #c6f6d5; color: #22543d; border: 1px solid #38a169; }
        .error { background: #fed7d7; color: #822727; border: 1px solid #e53e3e; }
        
        /* Badges de Estado */
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: bold; text-transform: uppercase; }
        .badge-pendiente { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; }
        .badge-surtido { background: #f0fff4; color: #2f855a; border: 1px solid #c6f6d5; }

        .card-detalle { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .grid-detalle { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; background: #f8fafc; padding: 12px; font-size: 0.85rem; color: #64748b; border-bottom: 2px solid #edf2f7; }
        td { padding: 12px; border-bottom: 1px solid #edf2f7; font-size: 0.9rem; }
        .btn-recibir { background: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 0.8rem; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <h1><i class="fas fa-history"></i> Historial de Producción</h1>
        <?php echo $mensaje; ?>

        <?php if ($detalle_id): ?>
        <div class="card-detalle">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3>Detalle Orden #<?php echo $detalle_id; ?></h3>
                <a href="historial_ordenes.php" style="color: #e53e3e; text-decoration:none;"><i class="fas fa-times"></i></a>
            </div>
            <div class="grid-detalle">
                <div>
                    <h4 style="color:#2b6cb0;">Productos</h4>
                    <table>
                        <?php foreach($productos_detalle as $pd): ?>
                        <tr><td><?php echo htmlspecialchars($pd['nombre']); ?></td><td><?php echo number_format($pd['cantidad_litros'], 2); ?> L</td></tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <div>
                    <h4 style="color:#38a169;">Insumos Requeridos</h4>
                    <table>
                        <?php foreach($insumos_detalle as $id): ?>
                        <tr><td><?php echo htmlspecialchars($id['nombre']); ?></td><td><?php echo number_format($id['cantidad_usada'], 3); ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card" style="background:white; border-radius:10px; border:1px solid #e2e8f0; overflow:hidden;">
            <table>
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Inversión</th>
                        <th style="text-align:right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ordenes as $o): ?>
                    <tr>
                        <td><strong>#<?php echo $o['id']; ?></strong></td>
                        <td><?php echo date('d/m/y H:i', strtotime($o['fecha_registro'])); ?></td>
                        <td>
                            <?php if(($o['estado'] ?? 'PENDIENTE') == 'PENDIENTE'): ?>
                                <span class="badge badge-pendiente">Esperando Insumos</span>
                            <?php else: ?>
                                <span class="badge badge-surtido">Surtido / Listo</span>
                            <?php endif; ?>
                        </td>
                        <td>$<?php echo number_format($o['costo_total_insumos'], 2); ?></td>
                        <td style="text-align:right; display:flex; justify-content:flex-end; gap:10px;">
                            <?php if(($o['estado'] ?? 'PENDIENTE') == 'PENDIENTE'): ?>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="id_orden" value="<?php echo $o['id']; ?>">
                                    <button type="submit" name="recibir_insumos_orden" class="btn-recibir" onclick="return confirm('¿Confirmas que recibiste el material de esta orden?')">
                                        <i class="fas fa-truck-loading"></i> Recibir
                                    </button>
                                </form>
                            <?php endif; ?>
                            <a href="?ver=<?php echo $o['id']; ?>" style="color:#4c51bf; font-weight:bold; text-decoration:none; font-size:0.8rem; margin-top:5px;">
                                <i class="fas fa-eye"></i> Ver
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>