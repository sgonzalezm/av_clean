<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$id_formula = $_GET['id'] ?? null;
if (!$id_formula) { header("Location: formulas.php"); exit; }

// 1. OBTENER INFORMACIÓN DE LA FÓRMULA MAESTRA
$stmt = $pdo->prepare("SELECT * FROM formulas_maestras WHERE id = ?");
$stmt->execute([$id_formula]);
$formula_maestra = $stmt->fetch();

// 2. AGREGAR INSUMO A LA RECETA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_insumo'])) {
    $insumo_id = $_POST['insumo_id'];
    $cantidad = $_POST['cantidad'];

    $sql = "INSERT INTO formulas (id_formula_maestra, insumo_id, cantidad_por_litro) VALUES (?, ?, ?)";
    $stmt_insert = $pdo->prepare($sql);
    $stmt_insert->execute([$id_formula, $insumo_id, $cantidad]);
    
    header("Location: configurar_formula.php?id=$id_formula"); 
    exit;
}

// 3. ELIMINAR INSUMO DE LA RECETA
if (isset($_GET['eliminar'])) {
    $id_detalle = $_GET['eliminar'];
    $pdo->prepare("DELETE FROM formulas WHERE id = ?")->execute([$id_detalle]);
    header("Location: configurar_formula.php?id=$id_formula"); 
    exit;
}

// 4. CONSULTAR INSUMOS DISPONIBLES
$insumos_db = $pdo->query("SELECT id, nombre, unidad_medida FROM insumos ORDER BY nombre ASC")->fetchAll();

// 5. CONSULTAR COMPOSICIÓN Y CALCULAR RANGO DE COSTOS
// Traemos el precio base y buscamos el precio más bajo en las presentaciones
$sql_detalle = "SELECT 
                    f.id as detalle_id, 
                    i.id as insumo_id,
                    i.nombre, 
                    i.precio_unitario as precio_base, 
                    f.cantidad_por_litro, 
                    i.unidad_medida,
                    (SELECT MIN(precio_presentacion / cantidad_capacidad) 
                     FROM insumo_presentaciones 
                     WHERE id_insumo = i.id) as precio_masivo
                FROM formulas f 
                JOIN insumos i ON f.insumo_id = i.id 
                WHERE f.id_formula_maestra = ?";

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
    <title>Configurar Receta | AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .grid-receta { display: grid; grid-template-columns: 1fr 2fr; gap: 20px; }
        .card { background: white; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0; }
        
        /* Estilos del Rango de Costos */
        .resumen-costo-container { 
            display: flex; 
            justify-content: space-between; 
            gap: 10px; 
            margin-bottom: 20px; 
        }
        .costo-box { 
            flex: 1; 
            padding: 15px; 
            border-radius: 8px; 
            text-align: center;
            border: 1px solid;
        }
        .costo-box.base { background: #fff5f5; border-color: #feb2b2; color: #c53030; }
        .costo-box.optimizado { background: #f0fff4; border-color: #c6f6d5; color: #2f855a; }
        .costo-box small { font-weight: bold; text-transform: uppercase; font-size: 0.7rem; display: block; margin-bottom: 5px; }
        .costo-box span { font-size: 1.4rem; font-weight: 800; }

        .form-control { width:100%; padding:10px; margin-bottom:15px; border: 1px solid #ccc; border-radius:5px; }
        .search-box { position: relative; margin-bottom: 10px; }
        .search-box i { position: absolute; left: 12px; top: 13px; color: #9ca3af; }
        .search-box input { padding-left: 35px; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div style="margin-bottom: 20px;">
            <a href="formulas.php" style="text-decoration: none; color: #4c51bf;"><i class="fas fa-arrow-left"></i> Volver</a>
            <h1 style="margin-top: 10px;"><i class="fas fa-flask"></i> <?php echo htmlspecialchars($formula_maestra['nombre_formula']); ?></h1>
        </div>

        <div class="grid-receta">
            <div class="card">
                <h3><i class="fas fa-plus-circle"></i> Agregar Insumo</h3>
                <form method="POST">
                    <input type="hidden" name="agregar_insumo" value="1">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="filterSelect" class="form-control" placeholder="Filtrar insumos..." onkeyup="filterOptions()">
                    </div>
                    <select name="insumo_id" id="insumoSelect" class="form-control" required size="6" style="height: auto;">
                        <?php foreach($insumos_db as $ins): ?>
                            <option value="<?php echo $ins['id']; ?>">
                                <?php echo htmlspecialchars($ins['nombre']); ?> (<?php echo $ins['unidad_medida']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label>Cantidad por 1 Litro:</label>
                    <input type="number" name="cantidad" step="0.000001" class="form-control" placeholder="Ej: 0.100 para 100ml o 100g" required>
                    
                    <button type="submit" class="btn" style="width:100%; background: #4c51bf; color:white; border:none; padding:12px; border-radius:5px; cursor:pointer;">
                        Añadir a la Mezcla
                    </button>
                </form>
            </div>

            <div class="card">
                <div class="resumen-costo-container">
                    <div class="costo-box base">
                        <small><i class="fas fa-shopping-cart"></i> Inversión Mínima</small>
                        <span id="display_base">$ 0.00</span>
                    </div>
                    <div class="costo-box optimizado">
                        <small><i class="fas fa-truck-loading"></i> Inversión Masiva</small>
                        <span id="display_masivo">$ 0.00</span>
                    </div>
                </div>

                <table id="recipeTable" style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 2px solid #edf2f7; color: #718096; font-size: 0.8rem;">
                            <th style="padding: 10px;">Insumo</th>
                            <th>Cantidad</th>
                            <th>Costo Base</th>
                            <th>Costo Masivo</th>
                            <th style="text-align: right;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($detalle_receta as $dr): 
                            // Precio masivo: si no hay presentaciones registradas, usa el precio base
                            $p_masivo = $dr['precio_masivo'] ?? $dr['precio_base'];
                            
                            $c_base = $dr['cantidad_por_litro'] * $dr['precio_base'];
                            $c_masivo = $dr['cantidad_por_litro'] * $p_masivo;

                            $costo_base_total += $c_base;
                            $costo_masivo_total += $c_masivo;
                        ?>
                        <tr style="border-bottom: 1px solid #edf2f7;">
                            <td style="padding: 12px 10px;">
                                <strong><?php echo htmlspecialchars($dr['nombre']); ?></strong>
                            </td>
                            <td><?php echo (float)$dr['cantidad_por_litro'] . ' ' . $dr['unidad_medida']; ?></td>
                            <td style="color: #e53e3e;">$<?php echo number_format($c_base, 2); ?></td>
                            <td style="color: #38a169; font-weight: bold;">$<?php echo number_format($c_masivo, 2); ?></td>
                            <td style="text-align: right;">
                                <a href="configurar_formula.php?id=<?php echo $id_formula; ?>&eliminar=<?php echo $dr['detalle_id']; ?>" 
                                   style="color:#cbd5e0;" onclick="return confirm('¿Quitar insumo?')">
                                   <i class="fas fa-times-circle"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if($costo_base_total > 0): ?>
                    <div style="margin-top: 15px; text-align: right; font-size: 0.85rem; color: #718096;">
                        <i class="fas fa-info-circle"></i> Ahorro potencial por litro comprando masivo: 
                        <strong style="color: #38a169;">$<?php echo number_format($costo_base_total - $costo_masivo_total, 2); ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Actualizar displays de costos
        document.getElementById('display_base').innerText = '$<?php echo number_format($costo_base_total, 2); ?>';
        document.getElementById('display_masivo').innerText = '$<?php echo number_format($costo_masivo_total, 2); ?>';

        function filterOptions() {
            let input = document.getElementById('filterSelect').value.toLowerCase();
            let select = document.getElementById('insumoSelect');
            let options = select.options;
            for (let i = 0; i < options.length; i++) {
                let text = options[i].text.toLowerCase();
                options[i].style.display = text.includes(input) ? '' : 'none';
            }
        }
    </script>
</body>
</html>