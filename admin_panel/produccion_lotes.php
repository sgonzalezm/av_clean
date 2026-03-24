<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$mensaje_exito = "";
$error = "";
$reporte = [];

// 1. OBTENER PRODUCTOS CON FÓRMULA ASIGNADA
$productos = $pdo->query("SELECT id, nombre, id_formula_maestra FROM productos WHERE id_formula_maestra IS NOT NULL ORDER BY nombre ASC")->fetchAll();

// 2. LÓGICA DE CÁLCULO (EXPLOSIÓN + PRESENTACIONES + PRECIOS VOLUMEN)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['calcular'])) {
    $lotes = $_POST['lote']; 
    $insumos_necesarios = [];

    foreach ($lotes as $prod_id => $litros) {
        if ($litros > 0) {
            $stmt = $pdo->prepare("
                SELECT f.insumo_id, f.cantidad_por_litro, i.nombre as insumo, 
                       i.unidad_medida, i.precio_unitario
                FROM productos p
                JOIN formulas f ON p.id_formula_maestra = f.id_formula_maestra
                JOIN insumos i ON f.insumo_id = i.id
                WHERE p.id = ?
            ");
            $stmt->execute([$prod_id]);
            $componentes = $stmt->fetchAll();

            foreach ($componentes as $c) {
                $id_insumo = $c['insumo_id'];
                $cantidad_neta_item = $c['cantidad_por_litro'] * $litros;

                if (!isset($insumos_necesarios[$id_insumo])) {
                    $insumos_necesarios[$id_insumo] = [
                        'id_insumo' => $id_insumo,
                        'nombre' => $c['insumo'],
                        'unidad' => $c['unidad_medida'],
                        'cantidad_neta' => 0,
                        'total_compra' => 0,
                        'sobrante' => 0,
                        'precio_base_u' => $c['precio_unitario'],
                        'precio_aplicado_u' => 0,
                        'costo_final' => 0,
                        'ahorro' => 0
                    ];
                }
                $insumos_necesarios[$id_insumo]['cantidad_neta'] += $cantidad_neta_item;
            }
        }
    }

    foreach ($insumos_necesarios as $id => &$item) {
        $cantidad_neta = $item['cantidad_neta'];
        $stmt_pres = $pdo->prepare("SELECT cantidad_capacidad, precio_presentacion FROM insumo_presentaciones WHERE id_insumo = ? ORDER BY cantidad_capacidad ASC");
        $stmt_pres->execute([$id]);
        $presentaciones = $stmt_pres->fetchAll(PDO::FETCH_ASSOC);

        $mejor_opcion = null;
        $precio_prorrateado = $item['precio_base_u'];

        if (!empty($presentaciones)) {
            foreach ($presentaciones as $p) {
                if ($p['cantidad_capacidad'] >= $cantidad_neta) {
                    $mejor_opcion = $p['cantidad_capacidad'];
                    if ($p['precio_presentacion'] > 0) {
                        $precio_prorrateado = $p['precio_presentacion'] / $p['cantidad_capacidad'];
                    }
                    break;
                }
            }
            if ($mejor_opcion === null) {
                $max_p = end($presentaciones);
                $unidades = ceil($cantidad_neta / $max_p['cantidad_capacidad']);
                $mejor_opcion = $unidades * $max_p['cantidad_capacidad'];
                if ($max_p['precio_presentacion'] > 0) {
                    $precio_prorrateado = $max_p['precio_presentacion'] / $max_p['cantidad_capacidad'];
                }
            }
            $item['total_compra'] = $mejor_opcion;
        } else {
            $item['total_compra'] = $cantidad_neta;
        }

        $item['precio_aplicado_u'] = $precio_prorrateado;
        $item['sobrante'] = $item['total_compra'] - $cantidad_neta;
        $item['costo_final'] = $item['total_compra'] * $precio_prorrateado;
        $costo_teorico = $item['total_compra'] * $item['precio_base_u'];
        $item['ahorro'] = $costo_teorico - $item['costo_final'];
    }

    $reporte = $insumos_necesarios;
    $_SESSION['ultimo_reporte_ahd'] = $reporte;
    $_SESSION['ultimo_calculo_lotes'] = $lotes;
}

// 4. CONFIRMACIÓN Y REGISTRO (CORREGIDO)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar_fabricacion'])) {
    $reporte_confirmado = $_SESSION['ultimo_reporte_ahd'] ?? [];
    $lotes_confirmados = $_SESSION['ultimo_calculo_lotes'] ?? [];
    
    if (empty($reporte_confirmado)) {
        $error = "Error: El reporte está vacío en la sesión.";
    } else {
        try {
            $pdo->beginTransaction();
            $total_inversion = 0;
            foreach($reporte_confirmado as $r) { $total_inversion += $r['costo_final']; }

            // CORRECCIÓN: Usamos 3 marcadores (?) para 3 valores
            $sql_o = "INSERT INTO ordenes_produccion (costo_total_insumos, observaciones, estado) VALUES (?, ?, ?)";
            $stmt_o = $pdo->prepare($sql_o);
            $stmt_o->execute([
                $total_inversion, 
                "Planificación de lote: Esperando insumos", 
                'PENDIENTE'
            ]);
            $id_orden = $pdo->lastInsertId();

            // Detalle de Productos
            $stmt_dp = $pdo->prepare("INSERT INTO orden_detalle_productos (id_orden, id_producto, cantidad_litros) VALUES (?, ?, ?)");
            foreach ($lotes_confirmados as $pid => $lts) {
                if ($lts > 0) $stmt_dp->execute([$id_orden, $pid, $lts]);
            }

            // Detalle de Insumos (Planificación de necesidad)
            $stmt_di = $pdo->prepare("INSERT INTO orden_detalle_insumos (id_orden, id_insumo, cantidad_usada, precio_al_momento) VALUES (?, ?, ?, ?)");

            foreach ($reporte_confirmado as $ins) {
                $stmt_di->execute([
                    $id_orden, 
                    $ins['id_insumo'], 
                    $ins['total_compra'], 
                    $ins['precio_aplicado_u']
                ]);
            }

            $pdo->commit();
            $mensaje_exito = "¡Orden de Planificación #$id_orden generada! Los insumos han sido enviados a Compras Pendientes.";
            unset($_SESSION['ultimo_reporte_ahd']);
            $reporte = [];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Producción Lotes | AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .main { padding: 20px; }
        .card-resumen { background: #fff; border-radius: 12px; padding: 25px; margin-top: 30px; border-top: 5px solid #2b6cb0; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .btn-pdf { background: #e53e3e; color: white; padding: 10px 15px; border-radius: 6px; text-decoration: none; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; border-bottom: 1px solid #edf2f7; text-align: left; }
        .badge-ahorro { background: #f0fff4; color: #2f855a; padding: 3px 8px; border-radius: 5px; font-size: 0.8rem; font-weight: bold; border: 1px solid #c6f6d5; }
        .badge-compra { background: #ebf8ff; color: #2b6cb0; padding: 3px 8px; border-radius: 5px; font-weight: bold; }
        .text-sobrante { color: #a0aec0; font-size: 0.8rem; font-style: italic; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <h1><i class="fas fa-boxes-packing"></i> Producción Inteligente</h1>

        <?php if($mensaje_exito): ?>
            <div style="background:#c6f6d5; color:#22543d; padding:15px; border-radius:8px; margin-bottom:20px;">
                <i class="fas fa-check-circle"></i> <?php echo $mensaje_exito; ?>
            </div>
        <?php endif; ?>

        <?php if($error): ?>
            <div style="background:#fed7d7; color:#822727; padding:15px; border-radius:8px; margin-bottom:20px;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="card" style="background:white; padding:25px; border-radius:12px; border: 1px solid #e2e8f0;">
            <form method="POST">
                <h3><i class="fas fa-calculator"></i> Planificar Lotes</h3>
                <div style="margin-top:20px;">
                    <?php foreach($productos as $p): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid #f7fafc; padding-bottom:10px;">
                        <span><?php echo htmlspecialchars($p['nombre']); ?></span>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <input type="number" name="lote[<?php echo $p['id']; ?>]" placeholder="0" step="0.1" min="0" style="width:110px; padding:8px; border-radius:5px; border:1px solid #ddd;">
                            <span style="color:#718096; font-size:0.9rem;">Litros</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" name="calcular" class="btn" style="width:100%; background:#4c51bf; padding:15px; font-weight:bold;">
                    <i class="fas fa-sync-alt"></i> Calcular Costos y Sobrantes
                </button>
            </form>
        </div>

        <?php if (!empty($reporte)): ?>
        <div class="card-resumen">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3><i class="fas fa-file-invoice-dollar"></i> Reporte con Reintegración</h3>
                <a href="generar_pdf_lote.php" target="_blank" class="btn-pdf"><i class="fas fa-print"></i> PDF de Pesado</a>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Insumo</th>
                        <th>Consumo Neto</th>
                        <th>Presentación Usada</th>
                        <th>Sobrante a Reintegrar</th>
                        <th>Costo Final</th>
                        <th>Ahorro</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $gran_total = 0; $gran_ahorro = 0; foreach($reporte as $item): 
                        $gran_total += $item['costo_final']; 
                        $gran_ahorro += $item['ahorro'];
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($item['nombre']); ?></strong></td>
                        <td><?php echo number_format($item['cantidad_neta'], 3); ?> <small><?php echo $item['unidad']; ?></small></td>
                        <td><span class="badge-compra"><?php echo number_format($item['total_compra'], 2); ?> <?php echo $item['unidad']; ?></span></td>
                        <td class="text-sobrante">+<?php echo number_format($item['sobrante'], 3); ?> (vuelve a stock)</td>
                        <td><strong>$<?php echo number_format($item['costo_final'], 2); ?></strong></td>
                        <td>
                            <?php if($item['ahorro'] > 0): ?>
                                <span class="badge-ahorro">-$<?php echo number_format($item['ahorro'], 2); ?></span>
                            <?php else: ?>
                                <small style="color:#ccc;">-</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="text-align:right; margin-top:25px; border-top:2px solid #edf2f7; padding-top:20px;">
                <p style="color:#2f855a; font-weight:bold; margin:0;">Ahorro por Volumen: $<?php echo number_format($gran_ahorro, 2); ?></p>
                <h2 style="margin:5px 0; color:#2d3748;">Inversión en Insumos: $<?php echo number_format($gran_total, 2); ?></h2>
            </div>

            <form method="POST">
                <button type="submit" name="confirmar_fabricacion" class="btn" style="background:#2b6cb0; width:100%; padding:18px; font-weight:bold; font-size:1.1rem; margin-top:15px;">
                    <i class="fas fa-check-double"></i> CONFIRMAR PLANIFICACIÓN
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>