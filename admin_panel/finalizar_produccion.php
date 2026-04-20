<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$id_orden = $_GET['id'] ?? null;
if (!$id_orden) { header("Location: historial_ordenes.php"); exit(); }

$mensaje = "";

// --- LÓGICA DE FINALIZACIÓN / AVANCE PARCIAL ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_avance'])) {
    $cantidades_parciales = $_POST['cantidad_real']; 
    $finalizar = isset($_POST['finalizar_orden']); // Checkbox para cerrar la orden
    
    try {
        $pdo->beginTransaction();

        foreach ($cantidades_parciales as $id_prod => $litros) {
            $litros = floatval($litros);
            if ($litros > 0) {
                // 1. Identificar fórmula maestra asociada
                $stmt_info = $pdo->prepare("SELECT id_formula_maestra FROM productos WHERE id = ?");
                $stmt_info->execute([$id_prod]);
                $prod_data = $stmt_info->fetch();

                if ($prod_data && $prod_data['id_formula_maestra']) {
                    $id_formula_m = $prod_data['id_formula_maestra'];

                    // 2. SUMAR AL TANQUE (Incremento parcial)
                    $pdo->prepare("UPDATE formulas_maestras SET stock_litros_disponibles = stock_litros_disponibles + ? WHERE id = ?")
                        ->execute([$litros, $id_formula_m]);

                    // 3. RESTAR INSUMOS (Descuento proporcional)
                    $stmt_ing = $pdo->prepare("SELECT insumo_id, cantidad_por_litro FROM formulas WHERE id_formula_maestra = ?");
                    $stmt_ing->execute([$id_formula_m]);
                    $ingredientes = $stmt_ing->fetchAll();

                    foreach ($ingredientes as $ing) {
                        $cantidad_a_descontar = $ing['cantidad_por_litro'] * $litros;
                        $pdo->prepare("UPDATE insumos SET stock_actual = stock_actual - ? WHERE id = ?")
                            ->execute([$cantidad_a_descontar, $ing['insumo_id']]);
                    }
                }
            }
        }

        // 4. Lógica de cierre (Modificado para respetar el checkbox)
        if ($finalizar) {
            $pdo->prepare("UPDATE ordenes_produccion SET estado = 'TERMINADO', observaciones = CONCAT(observaciones, ' | Finalizado el ', NOW()) WHERE id = ?")
                ->execute([$id_orden]);
            $mensaje = "<div style='background:#dcfce7; color:#166534; padding:15px; border-radius:8px; margin-bottom:20px;'><strong>Éxito:</strong> La orden se ha marcado como TERMINADA.</div>";
        } else {
            $mensaje = "<div style='background:#fef3c7; color:#92400e; padding:15px; border-radius:8px; margin-bottom:20px;'><strong>Avance registrado:</strong> El inventario ha sido actualizado.</div>";
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $mensaje = "<div style='background:#fee2e2; color:#b91c1c; padding:15px; border-radius:8px; margin-bottom:20px;'>Error: " . $e->getMessage() . "</div>";
    }
}

// --- CONSULTA DE DATOS ---
$stmt = $pdo->prepare("SELECT odp.*, p.nombre, f.stock_litros_disponibles as stock_tanque 
                       FROM orden_detalle_productos odp 
                       JOIN productos p ON odp.id_producto = p.id 
                       LEFT JOIN formulas_maestras f ON p.id_formula_maestra = f.id
                       WHERE odp.id_orden = ?");
$stmt->execute([$id_orden]);
$productos_orden = $stmt->fetchAll();

$check = $pdo->prepare("SELECT estado FROM ordenes_produccion WHERE id = ?");
$check->execute([$id_orden]);
$orden_info = $check->fetch();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Producción Parcial | AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .main { padding: 30px; max-width: 900px; margin: auto; }
        .card-finalizar { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); border-top: 5px solid #3182ce; }
        .row-producto { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 20px; align-items: center; padding: 15px 0; border-bottom: 1px solid #eee; }
        .input-real { width: 100%; padding: 10px; border: 2px solid #cbd5e0; border-radius: 6px; font-weight: bold; font-size: 1.1rem; text-align: center; }
        .badge-tanque { background: #ebf8ff; color: #2b6cb0; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <a href="historial_ordenes.php" style="text-decoration: none; color: #4a5568;"><i class="fas fa-arrow-left"></i> Volver al historial</a>
        <div style="display: flex; align-items: center; gap: 15px; margin: 20px 0;">
            <div style="background: #3182ce; color: white; width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                <i class="fas fa-fill-drip"></i>
            </div>
            <h1>Producción #<?php echo $id_orden; ?></h1>
        </div>
        
        <?php echo $mensaje; ?>

        <?php if ($orden_info['estado'] == 'TERMINADO'): ?>
            <div style="padding:20px; background:#e2e8f0; text-align:center; border-radius:10px;">Orden finalizada.</div>
        <?php else: ?>
        <div class="card-finalizar">
            <div style="background: #ebf8ff; border-left: 4px solid #3182ce; padding: 15px; margin-bottom: 25px; border-radius: 0 8px 8px 0;">
                <p style="margin: 0; color: #2c5282; font-size: 0.95rem;">
                    <strong><i class="fas fa-info-circle"></i> Registro de Avance:</strong><br>
                    Ingresa solo las cantidades producidas ahora. Los campos en cero serán ignorados.
                </p>
            </div>

            <form method="POST">
                <div class="row-producto" style="border-bottom: 2px solid #edf2f7; font-weight: bold; color: #2d3748; padding-bottom: 10px;">
                    <span>Producto / Mezcla</span>
                    <span style="text-align: center;">Planeado (Lts)</span>
                    <span style="text-align: center;">Carga Actual (Lts)</span>
                </div>

                <?php foreach($productos_orden as $p): ?>
                <div class="row-producto">
                    <div>
                        <strong><?php echo htmlspecialchars($p['nombre']); ?></strong><br>
                        <span class="badge-tanque"><i class="fas fa-database"></i> Tanque: <?php echo number_format($p['stock_tanque'], 2); ?> L</span>
                    </div>
                    <div style="text-align: center; color: #718096; font-weight: 600;">
                        <?php echo number_format($p['cantidad_litros'], 2); ?> L
                    </div>
                    <div>
                        <input type="number" name="cantidad_real[<?php echo $p['id_producto']; ?>]" 
                               value="0" step="0.01" min="0" class="input-real" 
                               onfocus="this.select()">
                    </div>
                </div>
                <?php endforeach; ?>

                <div style="background: #fff7ed; padding: 15px; border-radius: 10px; margin: 25px 0; border: 1px dashed #f97316;">
                    <label style="cursor:pointer; display:flex; align-items:center; gap:10px; font-weight:bold; color: #9a3412;">
                        <input type="checkbox" name="finalizar_orden" style="transform: scale(1.5);">
                        Marcar esta orden como "TERMINADA" (Cerrar definitivamente)
                    </label>
                </div>

                <button type="submit" name="registrar_avance" class="btn" 
                        style="width: 100%; background: #3182ce; color: white; border: none; padding: 20px; border-radius: 10px; font-weight: 800; font-size: 1.2rem; cursor: pointer;">
                    <i class="fas fa-save"></i> REGISTRAR AVANCE
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>