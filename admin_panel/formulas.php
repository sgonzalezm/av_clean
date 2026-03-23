<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// 1. PROCESAR EL GUARDADO DE LA NUEVA FÓRMULA (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_formula'])) {
    $nombre = $_POST['nombre_formula'];
    $categoria = $_POST['categoria'];
    $descripcion = $_POST['descripcion'];

    $sql_insert = "INSERT INTO formulas_maestras (nombre_formula, categoria, descripcion) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql_insert);
    $stmt->execute([$nombre, $categoria, $descripcion]);

    // Redirigir a la misma página para ver la nueva fórmula en la lista
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// 2. CONSULTA PARA LA TABLA
$sql = "SELECT f.id, f.nombre_formula, f.categoria, 
        (SELECT COUNT(*) FROM formulas fi WHERE fi.id_formula_maestra = f.id) as total_ingredientes
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
        /* Estilos del Modal */
        .modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); }
        .modal-content { background:white; width:90%; max-width:500px; margin:5% auto; padding:25px; border-radius:12px; }
        .form-group { margin-bottom: 15px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        
        .badge-count { background: #e2e8f0; color: #4a5568; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .btn-formula { background: #4c51bf; color: white; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 0.85rem; }
        .tag-categoria { font-size: 0.7rem; text-transform: uppercase; color: #2b6cb0; background: #ebf8ff; padding: 3px 8px; border-radius: 5px; border: 1px solid #bee3f8; font-weight: bold; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <div>
                <h1><i class="fas fa-microscope"></i> Recetario Maestro</h1>
                <p style="color: #718096;">Definición de composiciones químicas base.</p>
            </div>
            <button class="btn" onclick="document.getElementById('modalNuevaFormula').style.display='block'">
                <i class="fas fa-plus-circle"></i> Nueva Fórmula Base
            </button>
        </div>

        <div class="card" style="background: white; border-radius: 10px; border: 1px solid #e2e8f0; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8fafc; text-align: left;">
                        <th style="padding: 15px;">Nombre de la Mezcla</th>
                        <th style="padding: 15px;">Categoría</th>
                        <th style="padding: 15px; text-align: center;">Composición</th>
                        <th style="padding: 15px; text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($formulas as $f): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 15px;"><strong><?php echo htmlspecialchars($f['nombre_formula']); ?></strong></td>
                        <td style="padding: 15px;"><span class="tag-categoria"><?php echo htmlspecialchars($f['categoria']); ?></span></td>
                        <td style="padding: 15px; text-align: center;">
                            <span class="badge-count"><?php echo $f['total_ingredientes']; ?> Insumos</span>
                        </td>
                        <td style="padding: 15px; text-align: right;">
                            <a href="configurar_formula.php?id=<?php echo $f['id']; ?>" class="btn-formula">
                                <i class="fas fa-vial"></i> Definir Receta
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
            <p style="font-size: 0.9rem; color: #666;">Define el nombre de la mezcla química base.</p>
            <hr style="margin: 15px 0;">
            
            <form method="POST">
                <input type="hidden" name="crear_formula" value="1">
                
                <div class="form-group">
                    <label>Nombre de la Fórmula:</label>
                    <input type="text" name="nombre_formula" class="form-control" placeholder="Ej. Desengrasante Multiusos V1" required>
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
                    <textarea name="descripcion" class="form-control" rows="3" placeholder="Opcional: indicaciones de mezclado..."></textarea>
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
        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            let modal = document.getElementById('modalNuevaFormula');
            if (event.target == modal) { modal.style.display = "none"; }
        }
    </script>
</body>
</html>