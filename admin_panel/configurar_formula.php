<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$id_formula = $_GET['id'] ?? null;
if (!$id_formula) { header("Location: formulas.php"); exit; }

// 1. GUARDAR / ACTUALIZAR DESCRIPCIÓN (FUNCIÓN MANTENIDA)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_descripcion'])) {
    $descripcion = $_POST['descripcion'];
    $stmt = $pdo->prepare("UPDATE formulas_maestras SET descripcion = ? WHERE id = ?");
    $stmt->execute([$descripcion, $id_formula]);
    header("Location: configurar_formula.php?id=$id_formula");
    exit;
}

// 2. OBTENER INFORMACIÓN DE LA FÓRMULA MAESTRA (FUNCIÓN MANTENIDA)
$stmt = $pdo->prepare("SELECT * FROM formulas_maestras WHERE id = ?");
$stmt->execute([$id_formula]);
$formula_maestra = $stmt->fetch();

// 3. AGREGAR INSUMO A LA RECETA (FUNCIÓN MANTENIDA)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_insumo'])) {
    $insumo_id = $_POST['insumo_id'];
    $cantidad = $_POST['cantidad'];
    $sql = "INSERT INTO formulas (id_formula_maestra, insumo_id, cantidad_por_litro) VALUES (?, ?, ?)";
    $stmt_insert = $pdo->prepare($sql);
    $stmt_insert->execute([$id_formula, $insumo_id, $cantidad]);
    header("Location: configurar_formula.php?id=$id_formula"); 
    exit;
}

// 4. ELIMINAR INSUMO (FUNCIÓN MANTENIDA)
if (isset($_GET['eliminar'])) {
    $id_detalle = $_GET['eliminar'];
    $pdo->prepare("DELETE FROM formulas WHERE id = ?")->execute([$id_detalle]);
    header("Location: configurar_formula.php?id=$id_formula"); 
    exit;
}

// 5. CONSULTAS DE INSUMOS (FUNCIÓN MANTENIDA)
$insumos_db = $pdo->query("SELECT id, nombre, unidad_medida FROM insumos ORDER BY nombre ASC")->fetchAll();

$sql_detalle = "SELECT f.id as detalle_id, i.id as insumo_id, i.nombre, i.precio_unitario as precio_base, f.cantidad_por_litro, i.unidad_medida,
                (SELECT MIN(precio_presentacion / cantidad_capacidad) FROM insumo_presentaciones WHERE id_insumo = i.id) as precio_masivo
                FROM formulas f JOIN insumos i ON f.insumo_id = i.id WHERE f.id_formula_maestra = ?";
$stmt_detalle = $pdo->prepare($sql_detalle);
$stmt_detalle->execute([$id_formula]);
$detalle_receta = $stmt_detalle->fetchAll();

$costo_base_total = 0;
$costo_masivo_total = 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Receta | AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        body { font-family: sans-serif; }
        .grid-receta { display: grid; grid-template-columns: 1fr 2fr; gap: 20px; }
        .card { background: white; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 20px; }
        .form-control { width:100%; padding: 12px; margin-bottom:10px; border: 1px solid #ccc; border-radius:5px; font-size: 16px; box-sizing: border-box; }
        .btn { width:100%; border:none; padding:12px; border-radius:5px; cursor:pointer; font-size: 16px; font-weight: bold; }
        
        .resumen-costo-container { display: flex; justify-content: space-between; gap: 10px; margin-bottom: 20px; }
        .costo-box { flex: 1; padding: 10px; border-radius: 8px; text-align: center; border: 1px solid; }
        .costo-box.base { background: #fff5f5; border-color: #feb2b2; color: #c53030; }
        .costo-box.optimizado { background: #f0fff4; border-color: #c6f6d5; color: #2f855a; }
        .costo-box small { font-weight: bold; text-transform: uppercase; font-size: 0.65rem; display: block; }
        .costo-box span { font-size: 1.2rem; font-weight: 800; }

        @media (max-width: 768px) {
            .grid-receta { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <div style="margin-bottom: 20px;">
            <a href="formulas.php" style="text-decoration: none; color: #4c51bf;"><i class="fas fa-arrow-left"></i> Volver</a>
            <h1><i class="fas fa-flask"></i> <?php echo htmlspecialchars($formula_maestra['nombre_formula']); ?></h1>
        </div>

        <div class="grid-receta">
            <div>
                <div class="card" style="border: 2px solid #4c51bf;">
                    <h3><i class="fas fa-calculator"></i> Lote de Producción</h3>
                    <label>Litros a preparar:</label>
                    <input type="number" id="input_litros" class="form-control" value="1" step="0.1" oninput="recalcular()">
                </div>

                <div class="card">
                    <h3><i class="fas fa-plus-circle"></i> Agregar Insumo</h3>
                    <form method="POST">
                        <input type="hidden" name="agregar_insumo" value="1">
                        <select name="insumo_id" class="form-control" required>
                            <?php foreach($insumos_db as $ins): ?>
                                <option value="<?php echo $ins['id']; ?>"><?php echo htmlspecialchars($ins['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="cantidad" step="0.000001" class="form-control" placeholder="Cant. por litro" required>
                        <button type="submit" class="btn" style="background: #4c51bf; color:white;">Añadir</button>
                    </form>
                </div>

                <div class="card">
                    <h3><i class="fas fa-tasks"></i> Preparación</h3>
                    <form method="POST">
                        <textarea name="descripcion" class="form-control" rows="5" placeholder="Instrucciones paso a paso..."><?php echo htmlspecialchars($formula_maestra['descripcion'] ?? ''); ?></textarea>
                        <button type="submit" name="guardar_descripcion" class="btn" style="background: #718096; color:white;">Guardar</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="resumen-costo-container">
                    <div class="costo-box base">
                        <small>Total Mínimo</small>
                        <span id="display_base">$ 0.00</span>
                    </div>
                    <div class="costo-box optimizado">
                        <small>Total Masivo</small>
                        <span id="display_masivo">$ 0.00</span>
                    </div>
                </div>

                <div style="overflow-x: auto;">
                    <table id="recipeTable" style="width:100%; border-collapse: collapse;">
                        <thead>
                            <tr style="text-align: left; border-bottom: 2px solid #edf2f7; color: #718096; font-size: 0.8rem;">
                                <th style="padding: 10px;">Insumo</th>
                                <th>Cant.</th>
                                <th>Base</th>
                                <th>Masivo</th>
                                <th style="text-align: right;">X</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($detalle_receta as $dr): 
                                $p_masivo = $dr['precio_masivo'] ?? $dr['precio_base'];
                                $c_base_unit = $dr['cantidad_por_litro'] * $dr['precio_base'];
                                $c_masivo_unit = $dr['cantidad_por_litro'] * $p_masivo;
                                $costo_base_total += $c_base_unit;
                                $costo_masivo_total += $c_masivo_unit;
                            ?>
                            <tr class="insumo-row" 
                                data-qty="<?php echo $dr['cantidad_por_litro']; ?>" 
                                data-base-cost="<?php echo $c_base_unit; ?>" 
                                data-masivo-cost="<?php echo $c_masivo_unit; ?>">
                                <td style="padding: 10px;"><?php echo htmlspecialchars($dr['nombre']); ?></td>
                                <td class="qty-cell"><?php echo (float)$dr['cantidad_por_litro']; ?></td>
                                <td class="base-cost-cell" style="color: #e53e3e;">$<?php echo number_format($c_base_unit, 2); ?></td>
                                <td class="masivo-cost-cell" style="color: #38a169; font-weight: bold;">$<?php echo number_format($c_masivo_unit, 2); ?></td>
                                <td style="text-align: right;">
                                    <a href="configurar_formula.php?id=<?php echo $id_formula; ?>&eliminar=<?php echo $dr['detalle_id']; ?>" onclick="return confirm('¿Quitar?')"><i class="fas fa-times" style="color: #cbd5e0;"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function recalcular() {
            let litros = parseFloat(document.getElementById('input_litros').value) || 0;
            let rows = document.querySelectorAll('.insumo-row');
            let totalBase = 0;
            let totalMasivo = 0;

            rows.forEach(row => {
                let qty = parseFloat(row.dataset.qty);
                let baseCost = parseFloat(row.dataset.baseCost);
                let masivoCost = parseFloat(row.dataset.masivoCost);

                let newQty = (qty * litros);
                let newBase = (baseCost * litros);
                let newMasivo = (masivoCost * litros);

                row.querySelector('.qty-cell').innerText = newQty.toLocaleString(undefined, {minimumFractionDigits: 3, maximumFractionDigits: 3});
                row.querySelector('.base-cost-cell').innerText = '$' + newBase.toFixed(2);
                row.querySelector('.masivo-cost-cell').innerText = '$' + newMasivo.toFixed(2);

                totalBase += newBase;
                totalMasivo += newMasivo;
            });

            document.getElementById('display_base').innerText = '$' + totalBase.toFixed(2);
            document.getElementById('display_masivo').innerText = '$' + totalMasivo.toFixed(2);
        }
    </script>
</body>
</html>