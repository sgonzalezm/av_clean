<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$mensaje_exito = "";
$error = "";
$reporte = [];

// 1. OBTENER PRODUCTOS
$productos = $pdo->query("SELECT id, nombre, id_formula_maestra FROM productos WHERE id_formula_maestra IS NOT NULL ORDER BY nombre ASC")->fetchAll();

// 2. LÓGICA DE CÁLCULO
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

// 4. CONFIRMACIÓN
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
            $stmt_o->execute([$total_inv, "Planificación guardada"]);
            $id_orden = $pdo->lastInsertId();

            $stmt_dp = $pdo->prepare("INSERT INTO orden_detalle_productos (id_orden, id_producto, cantidad_litros) VALUES (?, ?, ?)");
            foreach ($lotes_confirmados as $pid => $lts) {
                if ($lts > 0) $stmt_dp->execute([$id_orden, $pid, $lts]);
            }

            $stmt_di = $pdo->prepare("INSERT INTO orden_detalle_insumos (id_orden, id_insumo, cantidad_usada, precio_al_momento) VALUES (?, ?, ?, ?)");
            foreach ($reporte_confirmado as $ins) {
                $stmt_di->execute([$id_orden, $ins['id_insumo'], $ins['total_compra'], $ins['precio_aplicado_u']]);
            }

            $pdo->commit();
            $mensaje_exito = "Orden #$id_orden generada con éxito.";
            
            // Limpiamos persistencia en JS mediante una bandera
            echo "<script>localStorage.removeItem('ahd_lotes_draft');</script>";
            
            unset($_SESSION['ultimo_reporte_ahd']);
            unset($_SESSION['ultimo_calculo_lotes']);
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
        .card-resumen { background: #fff; border-radius: 12px; padding: 25px; margin-bottom: 30px; border-left: 5px solid #2b6cb0; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .badge-ahorro { background: #f0fff4; color: #2f855a; padding: 3px 8px; border-radius: 5px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; border-bottom: 1px solid #edf2f7; text-align: left; }
        .sticky-calc { position: sticky; top: 0; z-index: 100; background: #f4f7f6; padding: 10px 0; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <h1><i class="fas fa-boxes-packing"></i> Producción Inteligente</h1>

        <?php if($mensaje_exito) echo "<div class='alert exito'>$mensaje_exito</div>"; ?>
        <?php if($error) echo "<div class='alert error'>$error</div>"; ?>

        <?php if (!empty($reporte)): ?>
        <div class="card-resumen" id="seccion-reporte">
            <h3><i class="fas fa-file-invoice-dollar"></i> Necesidades Calculadas</h3>
            <table>
                <thead>
                    <tr>
                        <th>Insumo</th>
                        <th>Neto</th>
                        <th>A Comprar</th>
                        <th>Costo Final</th>
                        <th>Ahorro</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $gran_total = 0; $gran_ahorro = 0; foreach($reporte as $item): 
                        $gran_total += $item['costo_final']; $gran_ahorro += $item['ahorro']; ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($item['nombre']); ?></strong></td>
                        <td><?php echo number_format($item['cantidad_neta'], 2); ?></td>
                        <td><span class="badge-compra"><?php echo number_format($item['total_compra'], 2); ?> <?php echo $item['unidad']; ?></span></td>
                        <td><strong>$<?php echo number_format($item['costo_final'], 2); ?></strong></td>
                        <td><?php if($item['ahorro'] > 0): ?><span class="badge-ahorro">-$<?php echo number_format($item['ahorro'], 2); ?></span><?php endif; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="text-align:right; margin-top:20px;">
                <h2 style="color:#2b6cb0;">Total: $<?php echo number_format($gran_total, 2); ?></h2>
                <form method="POST">
                    <button type="submit" name="confirmar_fabricacion" class="btn" style="background:#28a745; width:300px; padding:15px;">
                        CONFIRMAR Y GUARDAR PLAN
                    </button>
                    <button type="button" onclick="window.location.href=window.location.pathname" class="btn" style="background:#718096;">Nueva Mezcla</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="card" style="background:white; padding:25px; border-radius:12px; border: 1px solid #e2e8f0;">
            <form method="POST" id="form-lotes">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3><i class="fas fa-calculator"></i> Productos a Fabricar</h3>
                    <button type="button" onclick="limpiarBorrador()" style="background:none; border:none; color:red; cursor:pointer;"><i class="fas fa-trash"></i> Limpiar cantidades</button>
                </div>
                
                <div style="margin-top:20px; max-height: 500px; overflow-y: auto; padding-right: 10px;">
                    <?php foreach($productos as $p): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; border-bottom:1px solid #f7fafc; padding-bottom:8px;">
                        <label><?php echo htmlspecialchars($p['nombre']); ?></label>
                        <input type="number" 
                               name="lote[<?php echo $p['id']; ?>]" 
                               class="input-lote" 
                               data-id="<?php echo $p['id']; ?>"
                               placeholder="0" step="0.1" min="0" 
                               style="width:100px; padding:8px; border-radius:5px; border:1px solid #ddd;">
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="submit" name="calcular" class="btn" style="width:100%; background:#4c51bf; padding:15px; margin-top:20px;">
                    <i class="fas fa-sync-alt"></i> CALCULAR NECESIDADES
                </button>
            </form>
        </div>
    </div>

    <script>
        // PERSISTENCIA DE DATOS (BORRADOR)
        const form = document.getElementById('form-lotes');
        const inputs = document.querySelectorAll('.input-lote');

        // Al cargar la página, recuperar datos guardados
        window.onload = () => {
            const savedData = JSON.parse(localStorage.getItem('ahd_lotes_draft')) || {};
            inputs.forEach(input => {
                const id = input.getAttribute('data-id');
                if (savedData[id]) input.value = savedData[id];
            });
        };

        // Guardar cada vez que el usuario escribe
        inputs.forEach(input => {
            input.addEventListener('input', () => {
                const savedData = JSON.parse(localStorage.getItem('ahd_lotes_draft')) || {};
                const id = input.getAttribute('data-id');
                savedData[id] = input.value;
                localStorage.setItem('ahd_lotes_draft', JSON.stringify(savedData));
            });
        });

        function limpiarBorrador() {
            if(confirm("¿Seguro que quieres borrar todas las cantidades ingresadas?")) {
                localStorage.removeItem('ahd_lotes_draft');
                inputs.forEach(input => input.value = "");
            }
        }
    </script>
</body>
</html>