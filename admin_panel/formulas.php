<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// 1. PROCESAR EL GUARDADO DE LA NUEVA FÓRMULA (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_formula'])) {
    $nombre = $_POST['nombre_formula'];
    $categoria = $_POST['categoria'];
    $descripcion = $_POST['descripcion'];

    $sql_insert = "INSERT INTO formulas_maestras (nombre_formula, categoria, descripcion, stock_litros_disponibles) VALUES (?, ?, ?, 0)";
    $stmt = $pdo->prepare($sql_insert);
    $stmt->execute([$nombre, $categoria, $descripcion]);

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// 2. CONSULTA MEJORADA: Incluimos el Stock del Tanque
$sql = "SELECT f.id, f.nombre_formula, f.categoria, f.stock_litros_disponibles,
        (SELECT COUNT(*) FROM formulas fi WHERE fi.id_formula_maestra = f.id) as total_ingredientes,
        (SELECT GROUP_CONCAT(i.nombre SEPARATOR ', ') 
         FROM formulas fi 
         JOIN insumos i ON fi.insumo_id = i.id 
         WHERE fi.id_formula_maestra = f.id) as lista_insumos
        FROM formulas_maestras f 
        ORDER BY f.nombre_formula ASC";
$formulas = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recetario Maestro | AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); }
        .modal-content { background:white; width:90%; max-width:500px; margin:5% auto; padding:25px; border-radius:12px; }
        .form-group { margin-bottom: 15px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        
        .badge-count { background: #e2e8f0; color: #4a5568; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .btn-formula { background: #4c51bf; color: white; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 0.85rem; }
        .tag-categoria { font-size: 0.7rem; text-transform: uppercase; color: #2b6cb0; background: #ebf8ff; padding: 3px 8px; border-radius: 5px; border: 1px solid #bee3f8; font-weight: bold; }
        
        /* Estilos Stock de Tanque */
        .stock-tank-container { display: flex; align-items: center; gap: 8px; font-weight: 800; font-size: 1.1rem; color: #2d3748; }
        .tank-icon { color: #3182ce; font-size: 1.2rem; }
        .low-stock { color: #e53e3e !important; } /* Rojo si está vacío */

        .search-container { margin-bottom: 20px; position: relative; }
        .search-container input { padding-left: 40px; font-size: 1rem; border: 1px solid #e2e8f0; }
        .search-container i { position: absolute; left: 15px; top: 13px; color: #a0aec0; }
        .insumos-preview { display: block; font-size: 0.75rem; color: #718096; margin-top: 5px; font-style: italic; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <div>
                <h1><i class="fas fa-microscope"></i> Recetario Maestro</h1>
                <p style="color: #718096;">Gestión de mezclas base y niveles de tanques.</p>
            </div>
            <button class="btn" onclick="document.getElementById('modalNuevaFormula').style.display='block'">
                <i class="fas fa-plus-circle"></i> Nueva Fórmula Base
            </button>
        </div>

        <div class="search-container">
            <i class="fas fa-search"></i>
            <input type="text" id="recipeSearch" class="form-control" onkeyup="filterRecipes()" placeholder="Buscar por mezcla o insumo...">
        </div>

        <div class="card" style="background: white; border-radius: 10px; border: 1px solid #e2e8f0; overflow: hidden;">
            <table id="recipeTable" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8fafc; text-align: left; border-bottom: 2px solid #edf2f7;">
                        <th style="padding: 15px;">Mezcla e Insumos</th>
                        <th style="padding: 15px;">Categoría</th>
                        <th style="padding: 15px; text-align: center;">Stock en Tanque</th>
                        <th style="padding: 15px; text-align: center;">Composición</th>
                        <th style="padding: 15px; text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($formulas as $f): 
                        $stock = (float)$f['stock_litros_disponibles'];
                        $clase_bajo = ($stock <= 0) ? 'low-stock' : '';
                    ?>
                    <tr class="recipe-row" style="border-bottom: 1px solid #eee;">
                        <td style="padding: 15px;">
                            <strong class="recipe-name"><?php echo htmlspecialchars($f['nombre_formula']); ?></strong>
                            <span class="insumos-list" style="display:none;"><?php echo htmlspecialchars($f['lista_insumos']); ?></span>
                            <span class="insumos-preview">
                                <i class="fas fa-list-ul" style="font-size: 0.7rem;"></i> 
                                <?php echo $f['lista_insumos'] ? mb_strimwidth($f['lista_insumos'], 0, 80, "...") : 'Sin receta configurada'; ?>
                            </span>
                        </td>
                        <td style="padding: 15px;"><span class="tag-categoria"><?php echo htmlspecialchars($f['categoria']); ?></span></td>
                        
                        <td style="padding: 15px; text-align: center;">
                            <div class="stock-tank-container <?php echo $clase_bajo; ?>" style="justify-content: center;">
                                <i class="fas fa-gas-pump tank-icon <?php echo $clase_bajo; ?>"></i>
                                <span><?php echo number_format($stock, 1); ?> Lts</span>
                            </div>
                        </td>

                        <td style="padding: 15px; text-align: center;">
                            <span class="badge-count"><?php echo $f['total_ingredientes']; ?> Insumos</span>
                        </td>
                        <td style="padding: 15px; text-align: right;">
                            <a href="configurar_formula.php?id=<?php echo $f['id']; ?>" class="btn-formula">
                                <i class="fas fa-vial"></i> Configurar
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="modalNuevaFormula" class="modal">
        <div class="modal-content">
            <h2 style="margin-top:0;"><i class="fas fa-flask"></i> Crear Nueva Fórmula Base</h2>
            <form method="POST">
                <input type="hidden" name="crear_formula" value="1">
                <div class="form-group">
                    <label>Nombre de la Fórmula:</label>
                    <input type="text" name="nombre_formula" class="form-control" placeholder="Ej. Multiusos Lavanda" required>
                </div>
                <div class="form-group">
                    <label>Categoría:</label>
                    <select name="categoria" class="form-control">
                        <option value="Automotriz">Automotriz</option>
                        <option value="Hogar">Hogar</option>
                        <option value="Industrial">Industrial</option>
                        <option value="Cuidado Personal">Cuidado Personal</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Descripción / Notas:</label>
                    <textarea name="descripcion" class="form-control" rows="3"></textarea>
                </div>
                <div style="margin-top:20px;">
                    <button type="submit" class="btn" style="width:100%; padding:12px; background:#4c51bf; color:white; border:none; border-radius:5px; cursor:pointer;">
                        Guardar y Continuar
                    </button>
                    <button type="button" onclick="document.getElementById('modalNuevaFormula').style.display='none'" 
                            style="width:100%; margin-top:10px; background:none; border:none; color:#666; cursor:pointer;">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function filterRecipes() {
            let input = document.getElementById("recipeSearch").value.toLowerCase();
            let rows = document.getElementsByClassName("recipe-row");
            for (let i = 0; i < rows.length; i++) {
                let name = rows[i].querySelector(".recipe-name").innerText.toLowerCase();
                let insumos = rows[i].querySelector(".insumos-list").innerText.toLowerCase();
                if (name.includes(input) || insumos.includes(input)) {
                    rows[i].style.display = "";
                } else {
                    rows[i].style.display = "none";
                }
            }
        }
        window.onclick = function(event) {
            let modal = document.getElementById('modalNuevaFormula');
            if (event.target == modal) { modal.style.display = "none"; }
        }
    </script>
</body>
</html>