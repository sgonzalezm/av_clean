<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// LÓGICA PARA RECIBIR COMPRA (Botón Surtir)
if (isset($_POST['recibir_insumo'])) {
    $id_insumo = $_POST['id_insumo'];
    $cantidad = $_POST['cantidad'];

    // Aquí es donde el stock REALMENTE sube
    $stmt = $pdo->prepare("UPDATE insumos SET stock_actual = COALESCE(stock_actual, 0) + ? WHERE id = ?");
    $stmt->execute([$cantidad, $id_insumo]);
    
    $mensaje = "Insumo cargado al inventario correctamente.";
}

// Consultamos los insumos que están en órdenes PENDIENTES
$sql = "SELECT i.id, i.nombre, i.unidad_medida, SUM(od.cantidad_usada) as total_requerido, i.stock_actual
        FROM orden_detalle_insumos od
        JOIN insumos i ON od.id_insumo = i.id
        JOIN ordenes_produccion o ON od.id_orden = o.id
        WHERE o.estado = 'PENDIENTE'
        GROUP BY i.id";
$pendientes = $pdo->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Compras Pendientes | AHD Clean</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <h1><i class="fas fa-shopping-cart"></i> Insumos por Surtir</h1>
        
        <table border="1" style="width:100%; background:white; border-collapse:collapse;">
            <thead>
                <tr style="background:#edf2f7;">
                    <th>Insumo</th>
                    <th>Stock Actual</th>
                    <th>Requerido por Producción</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($pendientes as $p): ?>
                <tr>
                    <td><?php echo $p['nombre']; ?></td>
                    <td><?php echo number_format($p['stock_actual'], 2); ?></td>
                    <td style="color:red; font-weight:bold;"><?php echo number_format($p['total_requerido'], 2); ?></td>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="id_insumo" value="<?php echo $p['id']; ?>">
                            <input type="hidden" name="cantidad" value="<?php echo $p['total_requerido']; ?>">
                            <button type="submit" name="recibir_insumo" class="btn" style="background:#28a745; color:white;">
                                Marcar como Recibido (Cargar Stock)
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>