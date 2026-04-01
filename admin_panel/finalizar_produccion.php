<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$id_orden = $_GET['id'] ?? null;
if (!$id_orden) { header("Location: historial_ordenes.php"); exit(); }

$mensaje = "";

// --- LÓGICA DE FINALIZACIÓN Y CARGA A INVENTARIO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar_finalizacion'])) {
    $cantidades_reales = $_POST['cantidad_real']; // Array [id_producto => cantidad]
    
    try {
        $pdo->beginTransaction();

        foreach ($cantidades_reales as $id_prod => $cantidad) {
            $cantidad = floatval($cantidad);
            if ($cantidad > 0) {
                // 1. Actualizar el stock físico del producto
                $stmt_stock = $pdo->prepare("UPDATE productos SET stock_actual = COALESCE(stock_actual, 0) + ? WHERE id = ?");
                $stmt_stock->execute([$cantidad, $id_prod]);

                // 2. Registrar en el detalle de la orden cuánto se obtuvo realmente (opcional, para auditoría)
                $stmt_log = $pdo->prepare("UPDATE orden_detalle_productos SET cantidad_litros = ? WHERE id_orden = ? AND id_producto = ?");
                $stmt_log->execute([$cantidad, $id_orden, $id_prod]);
            }
        }

        // 3. Cambiar estado de la orden a TERMINADO
        $stmt_status = $pdo->prepare("UPDATE ordenes_produccion SET estado = 'TERMINADO', observaciones = CONCAT(observaciones, ' | Finalizado el ', NOW()) WHERE id = ?");
        $stmt_status->execute([$id_orden]);

        $pdo->commit();
        header("Location: historial_ordenes.php?mensaje=finalizado"); exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $mensaje = "<div class='alert error'>Error al finalizar: " . $e->getMessage() . "</div>";
    }
}

// --- CONSULTA DE DATOS DE LA ORDEN ---
$stmt = $pdo->prepare("
    SELECT odp.*, p.nombre, p.stock_actual as stock_ahora 
    FROM orden_detalle_productos odp 
    JOIN productos p ON odp.id_producto = p.id 
    WHERE odp.id_orden = ?
");
$stmt->execute([$id_orden]);
$productos_orden = $stmt->fetchAll();

// Verificar que la orden no esté ya terminada
$check = $pdo->prepare("SELECT estado FROM ordenes_produccion WHERE id = ?");
$check->execute([$id_orden]);
$orden_info = $check->fetch();

if ($orden_info['estado'] == 'TERMINADO') {
    die("Esta orden ya fue finalizada y cargada al inventario.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Finalizar Producción | AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .main { padding: 30px; max-width: 900px; margin: auto; }
        .card-finalizar { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); border-top: 5px solid #28a745; }
        .row-producto { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 20px; align-items: center; padding: 15px 0; border-bottom: 1px solid #eee; }
        .input-real { width: 100%; padding: 10px; border: 2px solid #cbd5e0; border-radius: 6px; font-weight: bold; font-size: 1.1rem; text-align: center; }
        .input-real:focus { border-color: #28a745; outline: none; }
        .label-teorico { color: #718096; font-size: 0.9rem; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <a href="historial_ordenes.php" style="text-decoration: none; color: #4a5568;"><i class="fas fa-arrow-left"></i> Volver al historial</a>
        <h1 style="margin-top: 20px;"><i class="fas fa-check-double"></i> Finalizar Producción #<?php echo $id_orden; ?></h1>
        
        <?php echo $mensaje; ?>

        <div class="card-finalizar">
            <p style="margin-bottom: 25px; color: #4a5568;">
                Ingresa la cantidad <strong>real obtenida</strong> después del envasado. El sistema actualizará el stock disponible para venta automáticamente.
            </p>

            <form method="POST">
                <div class="row-producto" style="border-bottom: 2px solid #edf2f7; font-weight: bold; color: #2d3748;">
                    <span>Producto</span>
                    <span style="text-align: center;">Planeado (Lts)</span>
                    <span style="text-align: center;">Real Obtenido (Lts)</span>
                </div>

                <?php foreach($productos_orden as $p): ?>
                <div class="row-producto">
                    <div>
                        <strong><?php echo htmlspecialchars($p['nombre']); ?></strong><br>
                        <span class="label-teorico">Stock actual en sistema: <?php echo number_format($p['stock_ahora'], 2); ?> L</span>
                    </div>
                    <div style="text-align: center; color: #4c51bf; font-weight: bold;">
                        <?php echo number_format($p['cantidad_litros'], 2); ?>
                    </div>
                    <div>
                        <input type="number" 
                               name="cantidad_real[<?php echo $p['id_producto']; ?>]" 
                               value="<?php echo $p['cantidad_litros']; ?>" 
                               step="0.01" min="0" class="input-real" required>
                    </div>
                </div>
                <?php endforeach; ?>

                <div style="margin-top: 30px; background: #f0fff4; padding: 20px; border-radius: 8px; border: 1px solid #c6f6d5;">
                    <p style="margin: 0; color: #22543d; font-size: 0.95rem;">
                        <i class="fas fa-info-circle"></i> Al confirmar, el estado de la orden pasará a <strong>TERMINADO</strong> y no podrá modificarse nuevamente. Los insumos ya fueron descontados previamente al surtir la orden.
                    </p>
                </div>

                <button type="submit" name="confirmar_finalizacion" class="btn" 
                        style="width: 100%; background: #28a745; padding: 18px; font-weight: bold; font-size: 1.1rem; margin-top: 20px;"
                        onclick="return confirm('¿Confirmas que estas cantidades son las que ingresarán al almacén de venta?')">
                    <i class="fas fa-warehouse"></i> CARGAR A INVENTARIO DE VENTA
                </button>
            </form>
        </div>
    </div>
</body>
</html>