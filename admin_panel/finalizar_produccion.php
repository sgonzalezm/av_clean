<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$id_orden = $_GET['id'] ?? null;
if (!$id_orden) { header("Location: historial_ordenes.php"); exit(); }

$mensaje = "";

// --- LÓGICA DE REGISTRO DE AVANCE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_avance'])) {
    $avances = $_POST['cantidad_real']; 
    $finalizar = isset($_POST['finalizar_orden']);
    
    try {
        $pdo->beginTransaction();

        foreach ($avances as $id_producto => $litros) {
            $litros = floatval($litros);
            if ($litros > 0) {
                // 1. Obtener fórmula
                $stmt_info = $pdo->prepare("SELECT id_formula_maestra FROM productos WHERE id = ?");
                $stmt_info->execute([$id_producto]);
                $prod_data = $stmt_info->fetch();

                if ($prod_data) {
                    $id_formula = $prod_data['id_formula_maestra'];

                    // 2. Sumar al tanque
                    $pdo->prepare("UPDATE formulas_maestras SET stock_litros_disponibles = stock_litros_disponibles + ? WHERE id = ?")
                        ->execute([$litros, $id_formula]);

                    // 3. Restar insumos
                    $stmt_ing = $pdo->prepare("SELECT insumo_id, cantidad_por_litro FROM formulas WHERE id_formula_maestra = ?");
                    $stmt_ing->execute([$id_formula]);
                    foreach ($stmt_ing->fetchAll() as $ing) {
                        $pdo->prepare("UPDATE insumos SET stock_actual = stock_actual - (?) WHERE id = ?")
                            ->execute([($ing['cantidad_por_litro'] * $litros), $ing['insumo_id']]);
                    }

                    // 4. ACUMULAR en detalle_producto
                    $pdo->prepare("UPDATE orden_detalle_productos SET cantidad_producida = cantidad_producida + ? WHERE id_orden = ? AND id_producto = ?")
                        ->execute([$litros, $id_orden, $id_producto]);
                }
            }
        }

        if ($finalizar) {
            $pdo->prepare("UPDATE ordenes_produccion SET estado = 'TERMINADO' WHERE id = ?")->execute([$id_orden]);
        }
        
        $pdo->commit();
        $mensaje = "<div style='background:#dcfce7; color:#166534; padding:15px; border-radius:8px; margin-bottom:20px;'>Avance guardado correctamente.</div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = "<div style='background:#fee2e2; color:#b91c1c; padding:15px;'>Error: " . $e->getMessage() . "</div>";
    }
}

// --- CONSULTA (FILTRA SOLO LO PENDIENTE) ---
$stmt = $pdo->prepare("
    SELECT odp.*, p.nombre, f.stock_litros_disponibles as stock_tanque, 
           (odp.cantidad_litros - odp.cantidad_producida) as pendiente
    FROM orden_detalle_productos odp 
    JOIN productos p ON odp.id_producto = p.id 
    LEFT JOIN formulas_maestras f ON p.id_formula_maestra = f.id
    WHERE odp.id_orden = ? 
    AND (odp.cantidad_litros - odp.cantidad_producida) > 0
");
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
    <title>Producción | AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .main { padding: 30px; max-width: 900px; margin: auto; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); border-top: 5px solid #3182ce; }
        .row-p { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 20px; align-items: center; padding: 15px 0; border-bottom: 1px solid #eee; }
        .input-lts { width: 100%; padding: 10px; border: 2px solid #cbd5e0; border-radius: 6px; font-weight: bold; text-align: center; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <a href="historial_ordenes.php"><i class="fas fa-arrow-left"></i> Volver</a>
        <h1>Producción #<?php echo $id_orden; ?></h1>
        <?php echo $mensaje; ?>

        <?php if(empty($productos_orden) && $orden_info['estado'] != 'TERMINADO'): ?>
            <div style="padding:20px; text-align:center;">¡Todo fabricado! Marca como terminada la orden.</div>
            <form method="POST">
                <input type="hidden" name="registrar_avance" value="1">
                <input type="hidden" name="finalizar_orden" value="1">
                <button type="submit" style="width:100%; padding:15px; background:#28a745; color:white; border:none; border-radius:8px; cursor:pointer;">CERRAR ORDEN</button>
            </form>
        <?php else: ?>
        <div class="card">
            <form method="POST">
                <div class="row-p" style="font-weight:bold;">
                    <span>Producto</span>
                    <span style="text-align:center;">Pendiente</span>
                    <span style="text-align:center;">Cargar (Lts)</span>
                </div>
                <?php foreach($productos_orden as $p): ?>
                <div class="row-p">
                    <div><strong><?php echo htmlspecialchars($p['nombre']); ?></strong></div>
                    <div style="text-align:center; color:#e53e3e; font-weight:bold;"><?php echo number_format($p['pendiente'], 2); ?> L</div>
                    <div>
                        <input type="number" name="cantidad_real[<?php echo $p['id_producto']; ?>]" value="0" step="0.01" min="0" class="input-lts" onfocus="this.select()">
                    </div>
                </div>
                <?php endforeach; ?>

                <div style="margin-top:20px;">
                    <label><input type="checkbox" name="finalizar_orden"> Marcar como TERMINADA</label>
                </div>
                <button type="submit" name="registrar_avance" style="width:100%; padding:15px; background:#3182ce; color:white; border:none; border-radius:8px; margin-top:20px;">GUARDAR AVANCE</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>