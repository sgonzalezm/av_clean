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

// 5. CONSULTAR COMPOSICIÓN ACTUAL
$sql_detalle = "SELECT f.id as detalle_id, i.nombre, i.precio_unitario, f.cantidad_por_litro, i.unidad_medida 
                FROM formulas f 
                JOIN insumos i ON f.insumo_id = i.id 
                WHERE f.id_formula_maestra = ?";
$stmt_detalle = $pdo->prepare($sql_detalle);
$stmt_detalle->execute([$id_formula]);
$detalle_receta = $stmt_detalle->fetchAll();

$costo_total_litro = 0;
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
        .resumen-costo { background: #f0fff4; border: 1px solid #c6f6d5; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .text-success { color: #38a169; font-weight: bold; font-size: 1.2rem; }
        .form-control { width:100%; padding:10px; margin-bottom:15px; border: 1px solid #ccc; border-radius:5px; }
        
        /* Estilos Buscador */
        .search-box { position: relative; margin-bottom: 10px; }
        .search-box input { padding-left: 35px; background: #f9fafb; }
        .search-box i { position: absolute; left: 12px; top: 13px; color: #9ca3af; }
        
        .table-search { margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .table-search input { flex: 1; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div style="margin-bottom: 20px;">
            <a href="formulas.php" style="text-decoration: none; color: #4c51bf;"><i class="fas fa-arrow-left"></i> Volver a Fórmulas</a>
            <h1 style="margin-top: 10px;"><i class="fas fa-flask"></i> <?php echo htmlspecialchars($formula_maestra['nombre_formula'] ?? 'Fórmula'); ?></h1>
            <p>Define los componentes y gramajes por cada **1 Litro** de mezcla.</p>
        </div>

        <div class="grid-receta">
            <div class="card">
                <h3><i class="fas fa-plus-circle"></i> Agregar Insumo</h3>
                <hr style="margin: 15px 0;">
                
                <form method="POST">
                    <input type="hidden" name="agregar_insumo" value="1">
                    
                    <label>Buscar e Insumo:</label>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="filterSelect" class="form-control" placeholder="Escribe para filtrar lista..." onkeyup="filterOptions()">
                    </div>

                    <select name="insumo_id" id="insumoSelect" class="form-control" required size="5" style="height: auto;">
                        <?php foreach($insumos_db as $ins): ?>
                            <option value="<?php echo $ins['id']; ?>">
                                <?php echo htmlspecialchars($ins['nombre']); ?> (<?php echo $ins['unidad_medida']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label style="margin-top:10px; display:block;">Cantidad por Litro (decimales):</label>
                    <input type="number" name="cantidad" step="0.000001" class="form-control" placeholder="Ej: 0.050 (50g o 50ml)" required>
                    
                    <button type="submit" class="btn" style="width:100%; background: #4c51bf; color:white; border:none; padding:12px; border-radius:5px; cursor:pointer;">
                        Añadir a la Fórmula
                    </button>
                </form>
            </div>

            <div class="card">
                <div class="resumen-costo">
                    <span>Costo Materia Prima por 1 Litro:</span><br>
                    <span class="text-success" id="total_display">$ 0.00</span>
                </div>

                <div class="table-search">
                    <i class="fas fa-filter" style="color:#9ca3af;"></i>
                    <input type="text" id="filterTable" placeholder="Filtrar componentes agregados..." onkeyup="filterTableItems()">
                </div>

                <table id="recipeTable" style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 2px solid #edf2f7; color: #718096; font-size: 0.8rem;">
                            <th style="padding: 10px;">Insumo</th>
                            <th>Cantidad</th>
                            <th>Costo Prop.</th>
                            <th style="text-align: right;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($detalle_receta as $dr): 
                            $costo_proporcional = $dr['cantidad_por_litro'] * $dr['precio_unitario'];
                            $costo_total_litro += $costo_proporcional;
                        ?>
                        <tr style="border-bottom: 1px solid #edf2f7;">
                            <td class="insumo-name" style="padding: 10px;"><strong><?php echo htmlspecialchars($dr['nombre']); ?></strong></td>
                            <td><?php echo (float)$dr['cantidad_por_litro'] . ' ' . $dr['unidad_medida']; ?></td>
                            <td>$<?php echo number_format($costo_proporcional, 2); ?></td>
                            <td style="text-align: right;">
                                <a href="configurar_formula.php?id=<?php echo $id_formula; ?>&eliminar=<?php echo $dr['detalle_id']; ?>" 
                                   style="color:red;" onclick="return confirm('¿Quitar este insumo?')">
                                   <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // 1. Calcular costo total al cargar
        document.getElementById('total_display').innerText = '$<?php echo number_format($costo_total_litro, 2); ?>';

        // 2. Filtrar el SELECT de insumos
        function filterOptions() {
            let input = document.getElementById('filterSelect').value.toLowerCase();
            let select = document.getElementById('insumoSelect');
            let options = select.options;

            for (let i = 0; i < options.length; i++) {
                let text = options[i].text.toLowerCase();
                options[i].style.display = text.includes(input) ? '' : 'none';
            }
        }

        // 3. Filtrar los items de la TABLA
        function filterTableItems() {
            let input = document.getElementById('filterTable').value.toLowerCase();
            let rows = document.querySelectorAll('#recipeTable tbody tr');

            rows.forEach(row => {
                let text = row.querySelector('.insumo-name').textContent.toLowerCase();
                row.style.display = text.includes(input) ? '' : 'none';
            });
        }
    </script>
</body>
</html>