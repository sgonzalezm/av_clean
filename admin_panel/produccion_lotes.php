<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$mensaje_exito = "";
$error = "";
$reporte = [];

// 1. OBTENER FÓRMULAS MAESTRAS (Ya no productos individuales)
// Obtenemos también un ID de producto representativo para mantener compatibilidad con el historial
$query_formulas = "
    SELECT f.id as id_formula, f.nombre_formula, 
    (SELECT p.id FROM productos p WHERE p.id_formula_maestra = f.id LIMIT 1) as id_producto_ref
    FROM formulas_maestras f 
    ORDER BY f.nombre_formula ASC
";
$formulas = $pdo->query($query_formulas)->fetchAll();

// 2. LÓGICA DE CÁLCULO DE INSUMOS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['calcular'])) {
    $lotes = $_POST['lote']; // Array [id_formula => litros_a_fabricar]
    $insumos_necesarios = [];

    foreach ($lotes as $id_form => $litros) {
        if ($litros > 0) {
            // Buscamos los ingredientes directamente por el ID de la fórmula
            $stmt = $pdo->prepare("
                SELECT f.insumo_id, f.cantidad_por_litro, i.nombre as insumo, 
                       i.unidad_medida, i.precio_unitario
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
                        'cantidad_neta' => 0,
                        'total_compra' => 0,
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

    // Lógica de optimización de compras (Presentaciones de insumos)
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
                    if ($p['precio_presentacion'] > 0) $precio_prorrateado = $p['precio_presentacion'] / $p['cantidad_capacidad'];
                    break;
                }
            }
            if ($mejor_opcion === null) {
                $max_p = end($presentaciones);
                $unidades = ceil($cantidad_neta / $max_p['cantidad_capacidad']);
                $mejor_opcion = $unidades * $max_p['cantidad_capacidad'];
                if ($max_p['precio_presentacion'] > 0) $precio_prorrateado = $max_p['precio_presentacion'] / $max_p['cantidad_capacidad'];
            }
            $item['total_compra'] = $mejor_opcion;
        } else {
            $item['total_compra'] = $cantidad_neta;
        }

        $item['precio_aplicado_u'] = $precio_prorrateado;
        $item['sobrante'] = $item['total_compra'] - $cantidad_neta;
        $item['costo_final'] = $item['total_compra'] * $precio_prorrateado;
        $item['ahorro'] = ($item['total_compra'] * $item['precio_base_u']) - $item['costo_final'];
    }

    $reporte = $insumos_necesarios;
    $_SESSION['ultimo_reporte_ahd'] = $reporte;
    $_SESSION['ultimo_calculo_lotes'] = $lotes;
}

// 3. CONFIRMACIÓN Y CREACIÓN DE ORDEN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar_fabricacion'])) {
    $reporte_confirmado = $_SESSION['ultimo_reporte_ahd'] ?? [];
    $lotes_confirmados = $_SESSION['ultimo_calculo_lotes'] ?? [];
    
    if (empty($reporte_confirmado)) {
        $error = "Error: El reporte está vacío.";
    } else {
        try {
            $pdo->beginTransaction();
            
            $total_inv = 0;
            foreach($reporte_confirmado as $r) { $total_inv += $r['costo_final']; }

            $stmt_o = $pdo->prepare("INSERT INTO ordenes_produccion (costo_total_insumos, observaciones, estado) VALUES (?, ?, 'PENDIENTE')");
            $stmt_o->execute([$total_inv, "Planificación de mezcla por lote (Tanque Central)"]);
            $id_orden = $pdo->lastInsertId();

            // Guardamos el detalle. Para mantener compatibilidad con finalizar_produccion.php, 
            // vinculamos la orden al ID de un producto que pertenezca a esa fórmula.
            $stmt_dp = $pdo->prepare("INSERT INTO orden_detalle_productos (id_orden, id_producto, cantidad_litros) VALUES (?, ?, ?)");
            foreach ($lotes_confirmados as $fid => $lts) {
                if ($lts > 0) {
                    // Buscamos un producto de referencia para esta fórmula
                    $stmt_ref = $pdo->prepare("SELECT id FROM productos WHERE id_formula_maestra = ? LIMIT 1");
                    $stmt_ref->execute([$fid]);
                    $pref = $stmt_ref->fetch();
                    $stmt_dp->execute([$id_orden, $pref['id'], $lts]);
                }
            }

            $stmt_di = $pdo->prepare("INSERT INTO orden_detalle_insumos (id_orden, id_insumo, cantidad_usada, precio_al_momento) VALUES (?, ?, ?, ?)");
            foreach ($reporte_confirmado as $ins) {
                $stmt_di->execute([$id_orden, $ins['id_insumo'], $ins['total_compra'], $ins['precio_aplicado_u']]);
            }

            $pdo->commit();
            $mensaje_exito = "Orden #$id_orden generada con éxito para carga a tanque.";
            echo "<script>localStorage.removeItem('ahd_lotes_draft');</script>";
            unset($_SESSION['ultimo_reporte_ahd'], $_SESSION['ultimo_calculo_lotes']);
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
    <meta charset="UTF-8">
    <title>Producción Lotes | AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .main { padding: 25px; }
        .card-formula { display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 12px 20px; border-radius: 10px; margin-bottom: 10px; border: 1px solid #e2e8f0; }
        .card-formula label { font-weight: 600; color: #2d3748; }
        .input-lote { width: 120px; padding: 10px; border: 2px solid #edf2f7; border-radius: 8px; text-align: center; font-weight: bold; font-size: 1rem; color: #4c51bf; }
        .input-lote:focus { border-color: #4c51bf; outline: none; }
        .header-prod { background: #4c51bf; color: white; padding: 20px; border-radius: 12px; margin-bottom: 25px; display: flex; align-items: center; gap: 15px; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert.exito { background: #f0fff4; color: #2f855a; border: 1px solid #c6f6d5; }
        .alert.error { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main">
        
        <div class="header-prod">
            <i class="fas fa-flask" style="font-size: 2rem;"></i>
            <div>
                <h1 style="margin:0; font-size: 1.5rem;">Producción por Lotes (Mezcla Central)</h1>
                <p style="margin:0; font-size: 0.9rem; opacity: 0.8;">Planifica la fabricación de litros de mezcla para tus tanques.</p>
            </div>
        </div>

        <?php if($mensaje_exito) echo "<div class='alert exito'><i class='fas fa-check-circle'></i> $mensaje_exito</div>"; ?>
        <?php if($error) echo "<div class='alert error'><i class='fas fa-exclamation-triangle'></i> $error</div>"; ?>

        <?php if (!empty($reporte)): ?>
        <div class="card" style="background: #fff; padding: 25px; border-radius: 12px; border-left: 6px solid #28a745; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
            <h3 style="margin-top:0;"><i class="fas fa-calculator"></i> Insumos Requeridos para esta Mezcla</h3>
            <table style="width:100%; border-collapse: collapse; margin: 20px 0;">
                <thead>
                    <tr style="text-align: left; color: #718096; border-bottom: 2px solid #edf2f7;">
                        <th style="padding: 10px;">Insumo</th>
                        <th>Cant. Neta</th>
                        <th>Presentación a Comprar</th>
                        <th>Costo Est.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $gran_total = 0; foreach($reporte as $item): $gran_total += $item['costo_final']; ?>
                    <tr style="border-bottom: 1px solid #f7fafc;">
                        <td style="padding: 12px;"><strong><?php echo htmlspecialchars($item['nombre']); ?></strong></td>
                        <td><?php echo number_format($item['cantidad_neta'], 2); ?> <?php echo $item['unidad']; ?></td>
                        <td><span style="background:#ebf8ff; color:#2b6cb0; padding:4px 8px; border-radius:6px; font-weight:bold;"><?php echo number_format($item['total_compra'], 2); ?> <?php echo $item['unidad']; ?></span></td>
                        <td><strong>$<?php echo number_format($item['costo_final'], 2); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="display:flex; justify-content: space-between; align-items: center; margin-top: 20px;">
                <h2 style="color: #28a745; margin:0;">Total Estimado: $<?php echo number_format($gran_total, 2); ?></h2>
                <form method="POST">
                    <button type="submit" name="confirmar_fabricacion" class="btn" style="background:#28a745; color:white; border:none; padding:15px 30px; border-radius:8px; cursor:pointer; font-weight:bold; font-size:1rem;">
                        <i class="fas fa-save"></i> CONFIRMAR Y GENERAR ORDEN
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="card" style="background:white; padding:25px; border-radius:12px; border: 1px solid #e2e8f0;">
            <form method="POST" id="form-lotes">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                    <h3 style="margin:0;"><i class="fas fa-list-check"></i> Seleccionar Volumen de Mezcla</h3>
                    <button type="button" onclick="limpiarBorrador()" style="background:none; border:none; color:#e53e3e; cursor:pointer; font-weight:600;">
                        <i class="fas fa-trash-can"></i> Limpiar Todo
                    </button>
                </div>
                
                <div style="max-height: 500px; overflow-y: auto; padding-right: 10px;">
                    <?php foreach($formulas as $f): ?>
                    <div class="card-formula">
                        <label><i class="fas fa-vial" style="color: #a0aec0; margin-right: 10px;"></i> <?php echo htmlspecialchars($f['nombre_formula']); ?></label>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="number" 
                                   name="lote[<?php echo $f['id_formula']; ?>]" 
                                   class="input-lote" 
                                   data-id="<?php echo $f['id_formula']; ?>"
                                   placeholder="0" step="0.1" min="0">
                            <span style="font-weight: bold; color: #718096;">Litros</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="submit" name="calcular" class="btn" style="width:100%; background:#4c51bf; color:white; border:none; padding:18px; margin-top:20px; border-radius:10px; font-weight:bold; font-size:1.1rem; cursor:pointer; box-shadow: 0 4px 12px rgba(76, 81, 191, 0.2);">
                    <i class="fas fa-calculator"></i> CALCULAR INSUMOS NECESARIOS
                </button>
            </form>
        </div>
    </div>

    <script>
        const inputs = document.querySelectorAll('.input-lote');

        window.onload = () => {
            const savedData = JSON.parse(localStorage.getItem('ahd_lotes_draft')) || {};
            inputs.forEach(input => {
                const id = input.getAttribute('data-id');
                if (savedData[id]) input.value = savedData[id];
            });
        };

        inputs.forEach(input => {
            input.addEventListener('input', () => {
                const savedData = JSON.parse(localStorage.getItem('ahd_lotes_draft')) || {};
                savedData[input.getAttribute('data-id')] = input.value;
                localStorage.setItem('ahd_lotes_draft', JSON.stringify(savedData));
            });
        });

        function limpiarBorrador() {
            if(confirm("¿Seguro que quieres borrar todas las cantidades?")) {
                localStorage.removeItem('ahd_lotes_draft');
                inputs.forEach(input => input.value = "");
            }
        }
    </script>
</body>
</html>