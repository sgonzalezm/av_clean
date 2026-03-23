<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$mensaje_exito = "";
$error = "";
$reporte = [];

// 1. OBTENER PRODUCTOS CON FÓRMULA ASIGNADA
$productos = $pdo->query("SELECT id, nombre, id_formula_maestra FROM productos WHERE id_formula_maestra IS NOT NULL ORDER BY nombre ASC")->fetchAll();

// 2. LÓGICA DE CÁLCULO (Explosión de Materiales)
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
                $total_item = $c['cantidad_por_litro'] * $litros;
                $id_insumo = $c['insumo_id'];

                if (!isset($insumos_necesarios[$id_insumo])) {
                    $insumos_necesarios[$id_insumo] = [
                        'id_insumo' => $id_insumo,
                        'nombre' => $c['insumo'],
                        'cantidad_requerida' => 0,
                        'unidad' => $c['unidad_medida'],
                        'costo_estimado' => 0,
                        'precio_u' => $c['precio_unitario']
                    ];
                }
                $insumos_necesarios[$id_insumo]['cantidad_requerida'] += $total_item;
                $insumos_necesarios[$id_insumo]['costo_estimado'] += ($total_item * $c['precio_unitario']);
            }
        }
    }
    $reporte = $insumos_necesarios;
    $_SESSION['ultimo_reporte_ahd'] = $reporte;
    $_SESSION['ultimo_calculo_lotes'] = $lotes;
}

// 3. CONFIRMACIÓN Y REGISTRO NORMALIZADO EN BD
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar_fabricacion'])) {
    $lotes_a_fabricar = $_SESSION['ultimo_calculo_lotes'] ?? [];
    $reporte_actual = $_SESSION['ultimo_reporte_ahd'] ?? [];
    
    if (empty($reporte_actual)) {
        $error = "No hay datos para procesar. Realice el cálculo primero.";
    } else {
        try {
            $pdo->beginTransaction();
            
            $total_final = 0;
            foreach($reporte_actual as $item) { $total_final += $item['costo_estimado']; }

            // A. INSERTAR CABECERA (ordenes_produccion)
            $sql_orden = "INSERT INTO ordenes_produccion (costo_total_insumos, observaciones) VALUES (?, ?)";
            $stmt_orden = $pdo->prepare($sql_orden);
            $stmt_orden->execute([$total_final, "Producción generada desde el panel de lotes"]);
            $id_orden = $pdo->lastInsertId();

            // B. INSERTAR DETALLE DE PRODUCTOS (orden_detalle_productos)
            $sql_det_prod = "INSERT INTO orden_detalle_productos (id_orden, id_producto, cantidad_litros) VALUES (?, ?, ?)";
            $stmt_det_prod = $pdo->prepare($sql_det_prod);
            foreach ($lotes_a_fabricar as $prod_id => $litros) {
                if ($litros > 0) {
                    $stmt_det_prod->execute([$id_orden, $prod_id, $litros]);
                }
            }

            // C. INSERTAR DETALLE DE INSUMOS Y DESCONTAR STOCK (orden_detalle_insumos)
            $sql_det_ins = "INSERT INTO orden_detalle_insumos (id_orden, id_insumo, cantidad_usada, precio_al_momento) VALUES (?, ?, ?, ?)";
            $stmt_det_ins = $pdo->prepare($sql_det_ins);
            
            $sql_update_stock = "UPDATE insumos SET stock_actual = stock_actual - ? WHERE id = ?";
            $stmt_update_stock = $pdo->prepare($sql_update_stock);

            foreach ($reporte_actual as $insumo) {
                // Guardar en detalle de la orden
                $stmt_det_ins->execute([
                    $id_orden, 
                    $insumo['id_insumo'], 
                    $insumo['cantidad_requerida'], 
                    $insumo['precio_u']
                ]);

                // Actualizar inventario físico
                $stmt_update_stock->execute([$insumo['cantidad_requerida'], $insumo['id_insumo']]);
            }

            $pdo->commit();
            $mensaje_exito = "¡Orden #$id_orden registrada exitosamente! Stock actualizado y detalles guardados.";
            unset($_SESSION['ultimo_reporte_ahd']);
            unset($_SESSION['ultimo_calculo_lotes']);
            $reporte = [];
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Fallo en el registro: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Producción | AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .main { padding: 20px; }
        .card-resumen { background: #fff; border-radius: 12px; padding: 25px; margin-top: 30px; border-top: 5px solid #2b6cb0; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn-pdf { background: #e53e3e; color: white; padding: 10px 15px; border-radius: 6px; text-decoration: none; display: inline-block; margin-bottom: 15px; font-size: 0.9rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #f8fafc; color: #4a5568; }
        .alerta-exito { background: #f0fff4; color: #2f855a; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c6f6d5; }
        .alerta-error { background: #fff5f5; color: #c53030; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fed7d7; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <h1><i class="fas fa-industry"></i> Gestión de Producción</h1>
        <hr style="margin-bottom: 25px; border: 0; border-top: 1px solid #e2e8f0;">
        
        <?php if($mensaje_exito): ?>
            <div class="alerta-exito"><i class="fas fa-check-circle"></i> <?php echo $mensaje_exito; ?></div>
        <?php endif; ?>

        <?php if($error): ?>
            <div class="alerta-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card" style="background:white; padding:25px; border-radius:10px; border: 1px solid #e2e8f0;">
            <form method="POST">
                <h3 style="margin-top:0;"><i class="fas fa-list-ol"></i> Planificar Lotes</h3>
                <p style="color: #718096; font-size: 0.9rem;">Indica los litros totales a fabricar para cada producto.</p>
                
                <div style="margin-top:20px;">
                    <?php foreach($productos as $p): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding: 10px; border-bottom: 1px solid #f7fafc;">
                        <span style="font-weight: 500;"><?php echo htmlspecialchars($p['nombre']); ?></span>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <input type="number" name="lote[<?php echo $p['id']; ?>]" placeholder="0" step="0.1" min="0" 
                                   style="width:100px; padding:8px; border:1px solid #cbd5e0; border-radius:6px; text-align:right;">
                            <span style="color: #a0aec0; font-size: 0.8rem;">LTS</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="submit" name="calcular" class="btn" style="width:100%; margin-top:20px; background: #4c51bf;">
                    <i class="fas fa-calculator"></i> Calcular Necesidades de Insumos
                </button>
            </form>
        </div>

        <?php if (!empty($reporte)): ?>
        <div class="card-resumen">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0;"><i class="fas fa-vial"></i> Explosión de Materiales</h3>
                <a href="generar_pdf_lote.php" target="_blank" class="btn-pdf">
                    <i class="fas fa-file-pdf"></i> Hoja de Pesado (Imprimir)
                </a>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Materia Prima</th>
                        <th style="text-align:center;">Cantidad Requerida</th>
                        <th style="text-align:right;">Costo Estimado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $total = 0; foreach($reporte as $item): $total += $item['costo_estimado']; ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($item['nombre']); ?></strong></td>
                        <td style="text-align:center;"><?php echo number_format($item['cantidad_requerida'], 3); ?> <?php echo $item['unidad']; ?></td>
                        <td style="text-align:right;">$<?php echo number_format($item['costo_estimado'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="text-align:right; margin-top:20px; padding-top:15px; border-top: 2px solid #f1f5f9;">
                <span style="font-size: 1.1rem;">Inversión en Insumos: <strong>$<?php echo number_format($total, 2); ?></strong></span>
            </div>

            <form method="POST" style="margin-top:25px;">
                <button type="submit" name="confirmar_fabricacion" class="btn" style="background:#2b6cb0; width:100%; color:white; padding:15px; font-weight:bold; font-size:1rem;">
                    <i class="fas fa-check-double"></i> CONFIRMAR PRODUCCIÓN Y DESCONTAR STOCK
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <script src="../js/admin.js"></script>
</body>
</html>