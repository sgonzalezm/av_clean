<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$mensaje_exito = "";

// --- FUNCIÓN DE AUDITORÍA INTEGRADA ---

// --- PROCESAMIENTO DE DATOS ---

// NUEVO: Procesar actualización de precio
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_precio'])) {
    $id_insumo = $_POST['id_insumo_edit'];
    $nuevo_precio = $_POST['nuevo_precio'];

    $sql_update = "UPDATE insumos SET precio_unitario = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql_update);
    if($stmt->execute([$nuevo_precio, $id_insumo])) {
        // registrarAuditoria($pdo, 'UPDATE', 'insumos', $id_insumo, "Actualizó precio a: $nuevo_precio");
    }
    header("Location: insumos.php"); exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nuevo_insumo'])) {
    $nombre = $_POST['nombre'];
    $unidad = $_POST['unidad'];
    $precio = $_POST['precio'];
    $id_prov = $_POST['id_proveedor']; 

    $sql_insert = "INSERT INTO insumos (nombre, unidad_medida, precio_unitario, id_proveedor) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql_insert);
    if($stmt->execute([$nombre, $unidad, $precio, $id_prov])) {
       $id_nuevo = $pdo->lastInsertId();
        //registrarAuditoria($pdo, 'INSERT', 'insumos', $id_nuevo, "Creó el insumo: $nombre");
    }
    header("Location: insumos.php"); exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_presentacion'])) {
    $id_insumo = $_POST['id_insumo_pres'];
    $capacidad = $_POST['capacidad'];
    $precio_pres = $_POST['precio_pres']; 
    
    $sql_pres = "INSERT INTO insumo_presentaciones (id_insumo, cantidad_capacidad, precio_presentacion) VALUES (?, ?, ?)";
    if($pdo->prepare($sql_pres)->execute([$id_insumo, $capacidad, $precio_pres])) {
        //registrarAuditoria($pdo, 'INSERT', 'insumo_presentaciones', $id_insumo, "Agregó múltiplo de $capacidad a un insumo");
    }
    header("Location: insumos.php"); exit();
}

// --- CONSULTA ---
$query_insumos = "
    SELECT i.*, p.nombre_empresa,
    (SELECT GROUP_CONCAT(CONCAT(cantidad_capacidad, ' ', i.unidad_medida, ' ($', precio_presentacion, ')') ORDER BY cantidad_capacidad ASC SEPARATOR '||') 
     FROM insumo_presentaciones ip WHERE ip.id_insumo = i.id) as presentaciones_lista
    FROM insumos i 
    LEFT JOIN proveedores p ON i.id_proveedor = p.id_proveedor 
    ORDER BY i.nombre ASC";
$insumos = $pdo->query($query_insumos)->fetchAll();
$proveedores = $pdo->query("SELECT id_proveedor, nombre_empresa FROM proveedores ORDER BY nombre_empresa ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materias Primas | AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .badge-pres { 
            background: #ebf8ff; color: #2b6cb0; padding: 4px 8px; 
            border-radius: 6px; font-size: 0.75rem; margin: 2px; 
            display: inline-block; border: 1px solid #bee3f8;
        }
        .modal { display:none; position:fixed; z-index:2000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.7); }
        .modal-content { background:white; width:90%; max-width:400px; margin: 10% auto; padding:25px; border-radius:12px; }
        .form-control { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        .btn-save { background: #28a745; color: white; border: none; padding: 12px; border-radius: 8px; width: 100%; font-weight: bold; cursor: pointer; }
        .btn-edit-small { background: none; border: none; color: #3b82f6; cursor: pointer; font-size: 0.9rem; margin-left: 5px; }
        .btn-edit-small:hover { color: #2563eb; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
            <h1><i class="fas fa-flask"></i> Materias Primas</h1>
            <button class="btn" onclick="document.getElementById('modalInsumo').style.display='block'" style="background:#3b82f6; color:white; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; font-weight:bold;">
                <i class="fas fa-plus"></i> Nuevo Insumo
            </button>
        </div>

        <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Buscar materia prima..." class="search-box" style="width:100%; padding:12px; margin-bottom:20px; border-radius:8px; border:1px solid #ddd;">

        <table id="insumosTable">
            <thead>
                <tr>
                    <th>Insumo / Proveedor</th>
                    <th>Presentaciones (Múltiplos)</th>
                    <th>Precio Base</th>
                    <th>Stock Actual</th>
                    <th>Gestión</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($insumos as $i): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($i['nombre']); ?></strong><br>
                        <small><?php echo htmlspecialchars($i['nombre_empresa'] ?? 'S/P'); ?></small>
                    </td>
                    <td>
                        <?php if($i['presentaciones_lista']): 
                            $tags = explode('||', $i['presentaciones_lista']);
                            foreach($tags as $tag) echo "<span class='badge-pres'>$tag</span>";
                        else: echo "<small style='color:#ccc;'>Única presentación</small>"; endif; ?>
                    </td>
                    <td>
                        $<?php echo number_format($i['precio_unitario'], 2); ?>
                        <button class="btn-edit-small" title="Editar Precio" onclick="abrirModalPrecio(<?php echo $i['id']; ?>, '<?php echo addslashes($i['nombre']); ?>', <?php echo $i['precio_unitario']; ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                    </td>
                    <td><strong><?php echo (float)$i['stock_actual']; ?> <?php echo $i['unidad_medida']; ?></strong></td>
                    <td>
                        <button class="btn-pres" onclick="abrirModalPres(<?php echo $i['id']; ?>, '<?php echo addslashes($i['nombre']); ?>')">
                            <i class="fas fa-plus"></i> Múltiplo
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="modalInsumo" class="modal">
        <div class="modal-content">
            <h3 style="margin-top:0;"><i class="fas fa-plus-circle"></i> Nuevo Insumo</h3>
            <form method="POST">
                <input type="hidden" name="nuevo_insumo" value="1">
                <label>Nombre del Químico:</label>
                <input type="text" name="nombre" class="form-control" placeholder="Ej. LESS 70%" required>
                
                <label>Unidad de Medida:</label>
                <input type="text" name="unidad" class="form-control" placeholder="Ej. KG o LT" required>
                
                <label>Precio Unitario Base:</label>
                <input type="number" name="precio" step="0.0001" class="form-control" placeholder="0.00" required>
                
                <label>Proveedor Principal:</label>
                <select name="id_proveedor" class="form-control">
                    <?php foreach ($proveedores as $p): ?>
                        <option value="<?php echo $p['id_proveedor']; ?>"><?php echo $p['nombre_empresa']; ?></option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="btn-save">Guardar Insumo</button>
                <button type="button" onclick="document.getElementById('modalInsumo').style.display='none'" style="width:100%; margin-top:10px; background:none; border:none; color:#666; cursor:pointer;">Cancelar</button>
            </form>
        </div>
    </div>

    <div id="modalPres" class="modal">
        <div class="modal-content">
            <h3 id="pres_nombre_insumo"></h3>
            <form method="POST">
                <input type="hidden" name="add_presentacion" value="1">
                <input type="hidden" name="id_insumo_pres" id="id_insumo_pres">
                <label>Capacidad (Ej. 20 para porrón):</label>
                <input type="number" name="capacidad" step="0.001" class="form-control" required>
                <label>Precio de esta presentación:</label>
                <input type="number" name="precio_pres" step="0.01" class="form-control" required>
                <button type="submit" class="btn-save" style="background:#4c51bf;">Guardar Múltiplo</button>
            </form>
            <button type="button" onclick="document.getElementById('modalPres').style.display='none'" style="width:100%; margin-top:10px; border:none; background:#eee; padding:10px; border-radius:8px;">Cerrar</button>
        </div>
    </div>

    <div id="modalPrecio" class="modal">
        <div class="modal-content">
            <h3 id="edit_nombre_insumo">Actualizar Precio</h3>
            <form method="POST">
                <input type="hidden" name="update_precio" value="1">
                <input type="hidden" name="id_insumo_edit" id="id_insumo_edit">
                
                <label>Nuevo Precio Unitario:</label>
                <input type="number" name="nuevo_precio" id="nuevo_precio_input" step="0.0001" class="form-control" required>
                
                <button type="submit" class="btn-save" style="background:#3b82f6;">Actualizar Precio</button>
                <button type="button" onclick="document.getElementById('modalPrecio').style.display='none'" style="width:100%; margin-top:10px; border:none; background:#eee; padding:10px; border-radius:8px;">Cancelar</button>
            </form>
        </div>
    </div>

    <script>
        // Función para abrir modal de múltiplos
        function abrirModalPres(id, nombre) {
            document.getElementById('id_insumo_pres').value = id;
            document.getElementById('pres_nombre_insumo').innerText = "Múltiplo para: " + nombre;
            document.getElementById('modalPres').style.display = 'block';
        }

        // NUEVO: Función para abrir modal de edición de precio
        function abrirModalPrecio(id, nombre, precioActual) {
            document.getElementById('id_insumo_edit').value = id;
            document.getElementById('edit_nombre_insumo').innerText = "Editar precio: " + nombre;
            document.getElementById('nuevo_precio_input').value = precioActual;
            document.getElementById('modalPrecio').style.display = 'block';
        }

        function filterTable() {
            let filter = document.getElementById("searchInput").value.toLowerCase();
            let rows = document.querySelectorAll("#insumosTable tbody tr");
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? "" : "none";
            });
        }

        window.onclick = function(e) { if (e.target.className === 'modal') e.target.style.display = "none"; }
    </script>
</body>
</html>