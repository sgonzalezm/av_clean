<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$id_orden = $_GET['id'] ?? null;
if (!$id_orden) { header("Location: historial_ordenes.php"); exit(); }

$mensaje = "";

// --- LÓGICA DE FINALIZACIÓN Y CARGA A INVENTARIO POR FÓRMULA ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar_finalizacion'])) {
    $cantidades_reales = $_POST['cantidad_real']; // Array [id_producto => litros_obtenidos]
    
    try {
        $pdo->beginTransaction();

        foreach ($cantidades_reales as $id_prod => $litros) {
            $litros = floatval($litros);
            if ($litros > 0) {
                // 1. Identificar la fórmula maestra asociada a este producto
                $stmt_info = $pdo->prepare("SELECT id_formula_maestra FROM productos WHERE id = ?");
                $stmt_info->execute([$id_prod]);
                $prod_data = $stmt_info->fetch();

                if ($prod_data && $prod_data['id_formula_maestra']) {
                    // 2. ACTUALIZAR EL STOCK EN EL TANQUE (Fórmula Maestra)
                    // Ya no sumamos a productos.stock_actual, sino a formulas_maestras.stock_litros_disponibles
                    $stmt_formula = $pdo->prepare("
                        UPDATE formulas_maestras 
                        SET stock_litros_disponibles = COALESCE(stock_litros_disponibles, 0) + ? 
                        WHERE id = ?
                    ");
                    $stmt_formula->execute([$litros, $prod_data['id_formula_maestra']]);
                }

                // 3. Registrar en el detalle de la orden cuánto se obtuvo realmente (Auditoría)
                $stmt_log = $pdo->prepare("UPDATE orden_detalle_productos SET cantidad_litros = ? WHERE id_orden = ? AND id_producto = ?");
                $stmt_log->execute([$litros, $id_orden, $id_prod]);
            }
        }

        // 4. Cambiar estado de la orden a TERMINADO
        $stmt_status = $pdo->prepare("UPDATE ordenes_produccion SET estado = 'TERMINADO', observaciones = CONCAT(observaciones, ' | Litros cargados a tanque el ', NOW()) WHERE id = ?");
        $stmt_status->execute([$id_orden]);

        $pdo->commit();
        header("Location: historial_ordenes.php?mensaje=finalizado"); 
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $mensaje = "<div class='alert error' style='background:#fee2e2; color:#b91c1c; padding:15px; border-radius:8px;'>Error al cargar a tanque: " . $e->getMessage() . "</div>";
    }
}

// --- CONSULTA DE DATOS DE LA ORDEN ACTUALIZADA ---
// Ahora traemos el stock disponible en el tanque (Fórmula)
$stmt = $pdo->prepare("
    SELECT odp.*, p.nombre, f.stock_litros_disponibles as stock_tanque 
    FROM orden_detalle_productos odp 
    JOIN productos p ON odp.id_producto = p.id 
    LEFT JOIN formulas_maestras f ON p.id_formula_maestra = f.id
    WHERE odp.id_orden = ?
");
$stmt->execute([$id_orden]);
$productos_orden = $stmt->fetchAll();

// Verificar que la orden no esté ya terminada
$check = $pdo->prepare("SELECT estado FROM ordenes_produccion WHERE id = ?");
$check->execute([$id_orden]);
$orden_info = $check->fetch();

if ($orden_info['estado'] == 'TERMINADO') {
    die("Esta orden ya fue finalizada y los litros están en el tanque.");
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
        .label-teorico { color: #718096; font-size: 0.85rem; font-weight: 600; }
        .badge-tanque { background: #ebf8ff; color: #2b6cb0; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <a href="historial_ordenes.php" style="text-decoration: none; color: #4a5568;"><i class="fas fa-arrow-left"></i> Volver al historial</a>
        
        <div style="display: flex; align-items: center; gap: 15px; margin: 20px 0;">
            <div style="background: #28a745; color: white; width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                <i class="fas fa-fill-drip"></i>
            </div>
            <h1>Finalizar y Cargar a Tanque #<?php echo $id_orden; ?></h1>
        </div>
        
        <?php echo $mensaje; ?>

        <div class="card-finalizar">
            <div style="background: #fffaf0; border-left: 4px solid #ed8936; padding: 15px; margin-bottom: 25px; border-radius: 0 8px 8px 0;">
                <p style="margin: 0; color: #7b341e; font-size: 0.95rem;">
                    <strong><i class="fas fa-info-circle"></i> Nueva Lógica de Inventario:</strong><br>
                    Al confirmar, los litros reales se sumarán al <strong>tanque de la fórmula</strong>. Esto permitirá vender cualquier presentación (1L, 5L, 20L) de forma dinámica.
                </p>
            </div>

            <form method="POST">
                <div class="row-producto" style="border-bottom: 2px solid #edf2f7; font-weight: bold; color: #2d3748; padding-bottom: 10px;">
                    <span>Producto / Mezcla</span>
                    <span style="text-align: center;">Planeado (Lts)</span>
                    <span style="text-align: center;">Obtenido (Lts)</span>
                </div>

                <?php foreach($productos_orden as $p): ?>
                <div class="row-producto">
                    <div>
                        <strong><?php echo htmlspecialchars($p['nombre']); ?></strong><br>
                        <span class="label-teorico">
                            <i class="fas fa-database"></i> En Tanque: 
                            <span class="badge-tanque"><?php echo number_format($p['stock_tanque'], 2); ?> L</span>
                        </span>
                    </div>
                    <div style="text-align: center; color: #4c51bf; font-weight: 800; font-size: 1.1rem;">
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
                    <p style="margin: 0; color: #22543d; font-size: 0.9rem; line-height: 1.4;">
                        <i class="fas fa-check-circle"></i> <strong>Verificación de Calidad:</strong> Al hacer clic en el botón, confirmas que la mezcla cumple con los estándares y los litros están listos en el área de envasado. El estado pasará a <strong>TERMINADO</strong>.
                    </p>
                </div>

                <button type="submit" name="confirmar_finalizacion" class="btn" 
                        style="width: 100%; background: #28a745; color: white; border: none; padding: 20px; border-radius: 10px; font-weight: 800; font-size: 1.2rem; margin-top: 20px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 15px; box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);">
                    <i class="fas fa-truck-loading"></i> CARGAR LITROS A TANQUE CENTRAL
                </button>
            </form>
        </div>
    </div>
</body>
</html>