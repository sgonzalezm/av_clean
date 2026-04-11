<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// 1. PROCESAR EL GUARDADO DE LA NUEVA FÓRMULA
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

// 2. CONSULTA: Incluimos ingredientes e insumos para el buscador
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Recetario Maestro | AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        :root { --accent: #4c51bf; --dark: #1e293b; --bg: #f8fafc; }
        body { background: var(--bg); margin: 0; }

        /* Estilos Header Mobile */
        .header-mobile { display: none; position: fixed; top: 0; left: 0; right: 0; height: 60px; background: var(--dark); color: white; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 2000; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        
        .main { transition: 0.3s; padding: 20px; }

        /* Buscador optimizado */
        .search-container { margin-bottom: 25px; position: relative; }
        .search-container input { width: 100%; padding: 15px 15px 15px 45px; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 1rem; box-sizing: border-box; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .search-container i { position: absolute; left: 15px; top: 18px; color: #a0aec0; }

        /* Vista de Tabla (Desktop) */
        .desktop-table { background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .desktop-table table { width: 100%; border-collapse: collapse; }
        .desktop-table th { background: #f8fafc; padding: 15px; text-align: left; color: #64748b; font-size: 0.85rem; text-transform: uppercase; }
        .desktop-table td { padding: 15px; border-top: 1px solid #edf2f7; }

        /* Vista de Tarjetas (Mobile) */
        .mobile-cards { display: none; flex-direction: column; gap: 15px; }
        .formula-card { background: white; border-radius: 15px; padding: 20px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; }
        .tag-categoria { font-size: 0.65rem; text-transform: uppercase; color: #2b6cb0; background: #ebf8ff; padding: 4px 10px; border-radius: 6px; font-weight: 800; border: 1px solid #bee3f8; }
        
        /* Indicador de Tanque */
        .tank-display { background: #f1f5f9; padding: 15px; border-radius: 12px; display: flex; align-items: center; gap: 12px; margin-bottom: 15px; }
        .tank-icon { font-size: 1.5rem; color: #3182ce; }
        .tank-info strong { display: block; font-size: 1.2rem; color: #1e293b; }
        .tank-info small { color: #64748b; font-size: 0.7rem; font-weight: bold; }
        .low-stock { border: 2px solid #fee2e2; background: #fff5f5; }
        .low-stock .tank-icon { color: #e53e3e; }

        /* Modal UX */
        .modal { display:none; position:fixed; z-index:3000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter: blur(3px); }
        .modal-content { background:white; width:90%; max-width:500px; margin:10% auto; padding:30px; border-radius:20px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .form-control { width: 100%; padding: 12px; border: 1px solid #cbd5e0; border-radius: 10px; margin-top: 5px; font-size: 1rem; box-sizing: border-box; }

        /* Media Queries */
        @media (max-width: 768px) {
            .header-mobile { display: flex; }
            .main { margin-left: 0 !important; padding: 80px 15px 100px 15px !important; }
            .desktop-table { display: none; }
            .mobile-cards { display: flex; }
            .hide-mobile { display: none; }
            .sidebar { position: fixed; left: -260px; z-index: 2500; }
            .sidebar.active { left: 0; }
        }

        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2200; }
        .overlay.active { display: block; }
    </style>
</head>
<body>
    <div class="overlay" id="overlay" onclick="toggleMenu()"></div>
    
    <div class="header-mobile">
        <button onclick="toggleMenu()" style="background:none; border:none; color:white; font-size:1.5rem;"><i class="fas fa-bars"></i></button>
        <span style="font-weight: 900;">AHD RECETARIO</span>
        <button class="btn" onclick="document.getElementById('modalNuevaFormula').style.display='block'" style="padding:5px 10px; font-size:0.8rem;"><i class="fas fa-plus"></i></button>
    </div>

    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <div class="hide-mobile" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <div>
                <h1><i class="fas fa-flask"></i> Recetario Maestro</h1>
                <p style="color: #64748b;">Niveles de tanques y configuraciones base.</p>
            </div>
            <button class="btn" onclick="document.getElementById('modalNuevaFormula').style.display='block'" style="background: var(--accent); color:white; padding: 12px 20px; border-radius:10px; border:none; font-weight:bold; cursor:pointer;">
                <i class="fas fa-plus-circle"></i> Nueva Mezcla Base
            </button>
        </div>

        <div class="search-container">
            <i class="fas fa-search"></i>
            <input type="text" id="recipeSearch" onkeyup="filterRecipes()" placeholder="Buscar mezcla (ej. Lavanda, Cloro)...">
        </div>

        <div class="desktop-table">
            <table>
                <thead>
                    <tr>
                        <th>Nombre y Composición</th>
                        <th>Categoría</th>
                        <th style="text-align: center;">Nivel Tanque</th>
                        <th style="text-align: center;">Insumos</th>
                        <th style="text-align: right;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($formulas as $f): 
                        $stock = (float)$f['stock_litros_disponibles'];
                    ?>
                    <tr class="recipe-row">
                        <td>
                            <strong class="recipe-name"><?php echo htmlspecialchars($f['nombre_formula']); ?></strong>
                            <span class="insumos-list" style="display:none;"><?php echo htmlspecialchars($f['lista_insumos']); ?></span>
                            <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 5px;">
                                <?php echo $f['lista_insumos'] ? mb_strimwidth($f['lista_insumos'], 0, 70, "...") : 'Sin ingredientes configurados'; ?>
                            </div>
                        </td>
                        <td><span class="tag-categoria"><?php echo $f['categoria']; ?></span></td>
                        <td style="text-align: center;">
                            <span style="font-weight: 800; color: <?php echo ($stock <= 0) ? '#e53e3e' : '#2d3748'; ?>;">
                                <i class="fas fa-gas-pump"></i> <?php echo number_format($stock, 1); ?> L
                            </span>
                        </td>
                        <td style="text-align: center;"><span style="background:#f1f5f9; padding:4px 10px; border-radius:15px; font-size:0.75rem; font-weight:bold;"><?php echo $f['total_ingredientes']; ?></span></td>
                        <td style="text-align: right;">
                            <a href="configurar_formula.php?id=<?php echo $f['id']; ?>" style="color:var(--accent); text-decoration:none; font-weight:bold;"><i class="fas fa-cog"></i> Configurar</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mobile-cards">
            <?php foreach ($formulas as $f): 
                $stock = (float)$f['stock_litros_disponibles'];
                $is_low = ($stock <= 0);
            ?>
            <div class="formula-card recipe-row">
                <div class="card-header">
                    <div>
                        <strong class="recipe-name" style="font-size: 1.1rem; color:#1e293b; display:block;"><?php echo htmlspecialchars($f['nombre_formula']); ?></strong>
                        <span class="tag-categoria"><?php echo $f['categoria']; ?></span>
                    </div>
                    <a href="configurar_formula.php?id=<?php echo $f['id']; ?>" style="background:var(--accent); color:white; width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; text-decoration:none;">
                        <i class="fas fa-cog"></i>
                    </a>
                </div>

                <div class="tank-display <?php echo $is_low ? 'low-stock' : ''; ?>">
                    <i class="fas fa-gas-pump tank-icon"></i>
                    <div class="tank-info">
                        <small>STOCK EN TANQUE</small>
                        <strong><?php echo number_format($stock, 1); ?> Litros</strong>
                    </div>
                </div>

                <div style="font-size: 0.8rem; color: #64748b; line-height: 1.4;">
                    <i class="fas fa-vial" style="margin-right:5px;"></i> 
                    <strong>Receta:</strong> <?php echo $f['lista_insumos'] ? mb_strimwidth($f['lista_insumos'], 0, 100, "...") : 'Pendiente'; ?>
                    <span class="insumos-list" style="display:none;"><?php echo htmlspecialchars($f['lista_insumos']); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="modalNuevaFormula" class="modal">
        <div class="modal-content">
            <h2 style="margin-top:0; color:var(--dark);"><i class="fas fa-flask"></i> Nueva Fórmula Base</h2>
            <form method="POST">
                <input type="hidden" name="crear_formula" value="1">
                <div style="margin-bottom:15px;">
                    <label style="font-size:0.8rem; font-weight:bold; color:#64748b;">NOMBRE DE LA MEZCLA</label>
                    <input type="text" name="nombre_formula" class="form-control" placeholder="Ej. Desengrasante Limón" required>
                </div>
                <div style="margin-bottom:15px;">
                    <label style="font-size:0.8rem; font-weight:bold; color:#64748b;">CATEGORÍA</label>
                    <select name="categoria" class="form-control">
                        <option value="Hogar">Hogar</option>
                        <option value="Automotriz">Automotriz</option>
                        <option value="Industrial">Industrial</option>
                        <option value="Cuidado Personal">Cuidado Personal</option>
                    </select>
                </div>
                <div style="margin-bottom:20px;">
                    <label style="font-size:0.8rem; font-weight:bold; color:#64748b;">NOTAS ADICIONALES</label>
                    <textarea name="descripcion" class="form-control" rows="3" placeholder="Instrucciones breves..."></textarea>
                </div>
                <button type="submit" style="width:100%; padding:15px; background:var(--accent); color:white; border:none; border-radius:12px; font-weight:bold; font-size:1rem; cursor:pointer;">
                    Crear y Configurar Insumos
                </button>
                <button type="button" onclick="document.getElementById('modalNuevaFormula').style.display='none'" style="width:100%; margin-top:10px; background:none; border:none; color:#94a3b8; cursor:pointer; font-weight:bold;">
                    Cancelar
                </button>
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
                rows[i].style.display = (name.includes(input) || insumos.includes(input)) ? "" : "none";
            }
        }

        function toggleMenu() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('active');
        }

        window.onclick = function(event) {
            let modal = document.getElementById('modalNuevaFormula');
            if (event.target == modal) { modal.style.display = "none"; }
        }
    </script>
</body>
</html>