<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$mensaje_exito = "";
$error = "";
$reporte = [];

// 1. OBTENER FÓRMULAS MAESTRAS
$query_formulas = "
    SELECT f.id as id_formula, f.nombre_formula, 
    (SELECT p.id FROM productos p WHERE p.id_formula_maestra = f.id LIMIT 1) as id_producto_ref
    FROM formulas_maestras f 
    ORDER BY f.nombre_formula ASC
";
$formulas = $pdo->query($query_formulas)->fetchAll();

// 2. LÓGICA DE CÁLCULO DE INSUMOS (INTELIGENTE)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['calcular'])) {
    $lotes = $_POST['lote']; 
    $insumos_necesarios = [];

    foreach ($lotes as $id_form => $litros) {
        $litros = floatval($litros);
        if ($litros > 0) {
            // Traemos el STOCK actual de cada insumo también
            $stmt = $pdo->prepare("
                SELECT f.insumo_id, f.cantidad_por_litro, i.nombre as insumo, 
                       i.unidad_medida, i.precio_unitario, i.stock_actual as stock_actual
                FROM formulas f
                JOIN insumos i ON f.insumo_id = i.id
                WHERE f.id_formula_maestra = ?
            ");
            $stmt->execute([$id_form]);
            $componentes = $stmt->fetchAll();

            foreach ($componentes as $c) {
                $id_insumo = $c['insumo_id'];
                $cantidad_neta_item = $c['cantidad_por_litro'] * $litros;

                if (!isset($insumos_necesarios[$id_insumo])) {
                    $insumos_necesarios[$id_insumo] = [
                        'id_insumo' => $id_insumo,
                        'nombre' => $c['insumo'],
                        'unidad' => $c['unidad_medida'],
                        'stock_actual' => $c['stock_actual'], // Guardamos stock actual
                        'cantidad_neta_total' => 0,
                        'total_a_comprar' => 0,
                        'precio_base_u' => $c['precio_unitario'],
                        'costo_final' => 0
                    ];
                }
                $insumos_necesarios[$id_insumo]['cantidad_neta_total'] += $cantidad_neta_item;
            }
        }
    }

    // LÓGICA DE OPTIMIZACIÓN: Solo comprar lo que falta
    foreach ($insumos_necesarios as $id => &$item) {
        // ¿Cuánto nos falta realmente? (Necesidad - Lo que hay en estante)
        $faltante_real = $item['cantidad_neta_total'] - $item['stock_actual'];

        if ($faltante_real <= 0) {
            // Tenemos de sobra, no comprar nada
            $item['total_a_comprar'] = 0;
            $item['costo_final'] = 0;
        } else {
            // Falta material, buscamos la mejor presentación para comprar el faltante
            $stmt_pres = $pdo->prepare("SELECT cantidad_capacidad, precio_presentacion FROM insumo_presentaciones WHERE id_insumo = ? ORDER BY cantidad_capacidad ASC");
            $stmt_pres->execute([$id]);
            $presentaciones = $stmt_pres->fetchAll(PDO::FETCH_ASSOC);

            $mejor_opcion = null;
            $precio_u = $item['precio_base_u'];

            if (!empty($presentaciones)) {
                foreach ($presentaciones as $p) {
                    if ($p['cantidad_capacidad'] >= $faltante_real) {
                        $mejor_opcion = $p['cantidad_capacidad'];
                        $precio_u = $p['precio_presentacion'] / $p['cantidad_capacidad'];
                        break;
                    }
                }
                if ($mejor_opcion === null) {
                    $max_p = end($presentaciones);
                    $unidades = ceil($faltante_real / $max_p['cantidad_capacidad']);
                    $mejor_opcion = $unidades * $max_p['cantidad_capacidad'];
                    $precio_u = $max_p['precio_presentacion'] / $max_p['cantidad_capacidad'];
                }
                $item['total_a_comprar'] = $mejor_opcion;
            } else {
                $item['total_a_comprar'] = $faltante_real;
            }
            $item['costo_final'] = $item['total_a_comprar'] * $precio_u;
        }
    }
    unset($item); 
    $reporte = $insumos_necesarios;
    $_SESSION['ultimo_reporte_ahd'] = $reporte;
    $_SESSION['ultimo_calculo_lotes'] = $lotes;
}

// 3. CONFIRMACIÓN (Igual que antes pero guardando la orden)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar_fabricacion'])) {
    $reporte_confirmado = $_SESSION['ultimo_reporte_ahd'] ?? [];
    $lotes_confirmados = $_SESSION['ultimo_calculo_lotes'] ?? [];
    
    if (!empty($reporte_confirmado)) {
        try {
            $pdo->beginTransaction();
            $total_inv = array_sum(array_column($reporte_confirmado, 'costo_final'));

            $stmt_o = $pdo->prepare("INSERT INTO ordenes_produccion (costo_total_insumos, observaciones, estado, fecha_registro) VALUES (?, ?, 'PENDIENTE', NOW())");
            $stmt_o->execute([$total_inv, "Lote optimizado considerando stock previo"]);
            $id_orden = $pdo->lastInsertId();

            $stmt_dp = $pdo->prepare("INSERT INTO orden_detalle_productos (id_orden, id_producto, cantidad_litros) VALUES (?, ?, ?)");
            foreach ($lotes_confirmados as $fid => $lts) {
                if ($lts > 0) {
                    $stmt_ref = $pdo->prepare("SELECT id FROM productos WHERE id_formula_maestra = ? LIMIT 1");
                    $stmt_ref->execute([$fid]);
                    $pref = $stmt_ref->fetch();
                    if ($pref) $stmt_dp->execute([$id_orden, $pref['id'], $lts]);
                }
            }

            $stmt_di = $pdo->prepare("INSERT INTO orden_detalle_insumos (id_orden, id_insumo, cantidad_usada, precio_al_momento) VALUES (?, ?, ?, ?)");
            foreach ($reporte_confirmado as $ins) {
                $stmt_di->execute([$id_orden, $ins['id_insumo'], $ins['total_a_comprar'], ($ins['costo_final'] > 0 ? $ins['costo_final']/$ins['total_a_comprar'] : $ins['precio_base_u'])]);
            }

            $pdo->commit();
            unset($_SESSION['ultimo_reporte_ahd'], $_SESSION['ultimo_calculo_lotes']);
            $mensaje_exito = "Orden #$id_orden generada con éxito.";
            $reporte = []; 
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Error crítico: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Producción Inteligente | AHD Clean</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .main { padding: 25px; }
        .card-formula { display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 12px 20px; border-radius: 10px; margin-bottom: 10px; border: 1px solid #e2e8f0; }
        .input-lote { width: 100px; padding: 8px; border: 2px solid #edf2f7; border-radius: 8px; text-align: center; font-weight: bold; }
        .header-prod { background: #4c51bf; color: white; padding: 20px; border-radius: 12px; margin-bottom: 25px; }
        .badge-stock { background: #f0fff4; color: #2f855a; padding: 4px 8px; border-radius: 6px; font-weight: bold; font-size: 0.85rem; border: 1px solid #c6f6d5; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <div class="header-prod">
            <h1><i class="fas fa-microchip"></i> Producción Inteligente</h1>
            <p>El sistema ahora descuenta lo que ya tienes en bodega antes de pedirte comprar.</p>
        </div>

        <?php if($mensaje_exito) echo "<div class='alert exito' style='background:#f0fff4; color:#2f855a; padding:15px; border-radius:10px; margin-bottom:20px;'><i class='fas fa-check-circle'></i> $mensaje_exito</div>"; ?>

        <?php if (!empty($reporte)): ?>
        <div class="card" style="background: #fff; padding: 25px; border-radius: 12px; border-left: 6px solid #4c51bf; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
            <h3><i class="fas fa-clipboard-list"></i> Análisis de Insumos</h3>
            <table style="width:100%; border-collapse: collapse; margin-top:15px;">
                <thead>
                    <tr style="text-align: left; color: #718096; border-bottom: 2px solid #edf2f7;">
                        <th style="padding: 10px;">Insumo</th>
                        <th>En Almacén</th>
                        <th>Necesario</th>
                        <th>A Comprar</th>
                        <th>Costo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($reporte as $item): ?>
                    <tr style="border-bottom: 1px solid #f7fafc;">
                        <td style="padding: 12px;"><strong><?php echo htmlspecialchars($item['nombre']); ?></strong></td>
                        <td><span class="badge-stock"><?php echo number_format($item['stock_actual'], 2); ?></span></td>
                        <td><?php echo number_format($item['cantidad_neta_total'], 2); ?></td>
                        <td>
                            <?php if($item['total_a_comprar'] > 0): ?>
                                <span style="color:#2b6cb0; font-weight:bold;"><i class="fas fa-shopping-cart"></i> <?php echo number_format($item['total_a_comprar'], 2); ?></span>
                            <?php else: ?>
                                <span style="color:#38a169; font-weight:bold;"><i class="fas fa-check-circle"></i> Suficiente</span>
                            <?php endif; ?>
                        </td>
                        <td><strong>$<?php echo number_format($item['costo_final'], 2); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <form method="POST" style="margin-top:20px; text-align:right;">
                <button type="submit" name="confirmar_fabricacion" style="background:#4c51bf; color:white; border:none; padding:15px 30px; border-radius:10px; cursor:pointer; font-weight:bold; font-size:1rem;">
                    GENERAR ORDEN DE PRODUCCIÓN
                </button>
            </form>
        </div>
        <?php endif; ?>

        <div class="card" style="background:white; padding:25px; border-radius:12px;">
            <form method="POST">
                <h3 style="margin-bottom:20px;">Selecciona Volumen de Mezcla</h3>
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php foreach($formulas as $f): ?>
                    <div class="card-formula">
                        <label><strong><?php echo htmlspecialchars($f['nombre_formula']); ?></strong></label>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="number" name="lote[<?php echo $f['id_formula']; ?>]" class="input-lote" placeholder="0" step="0.1">
                            <span style="color:#718096; font-weight:bold;">Lts</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" name="calcular" style="width:100%; background:#2d3748; color:white; border:none; padding:18px; margin-top:20px; border-radius:10px; font-weight:bold; cursor:pointer;">
                    CALCULAR NECESIDADES REALES
                </button>
            </form>
        </div>
    </div>
</body>
</html>