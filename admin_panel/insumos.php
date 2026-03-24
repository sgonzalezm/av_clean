<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$mensaje_exito = "";
$error = "";

// 1. PROCESAR GUARDADO DE NUEVO INSUMO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nuevo_insumo'])) {
    $nombre = $_POST['nombre'];
    $unidad = $_POST['unidad'];
    $cant_minima = $_POST['cantidad_minima'] ?? 0;
    $precio = $_POST['precio'];
    $id_prov = $_POST['id_proveedor']; 

    $sql_insert = "INSERT INTO insumos (nombre, unidad_medida, cantidad_minima, precio_unitario, id_proveedor) 
                   VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql_insert);
    $stmt->execute([$nombre, $unidad, $cant_minima, $precio, $id_prov]);
    header("Location: insumos.php"); exit();
}

// 2. PROCESAR AGREGAR PRESENTACIÓN (Capacidad + Precio total)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_presentacion'])) {
    $id_insumo = $_POST['id_insumo_pres'];
    $capacidad = $_POST['capacidad'];
    $precio_pres = $_POST['precio_pres']; // Nuevo campo de precio
    
    $stmt = $pdo->prepare("INSERT INTO insumo_presentaciones (id_insumo, cantidad_capacidad, precio_presentacion) VALUES (?, ?, ?)");
    $stmt->execute([$id_insumo, $capacidad, $precio_pres]);
    header("Location: insumos.php"); exit();
}

// 3. ELIMINAR PRESENTACIÓN
if (isset($_GET['del_pres'])) {
    $pdo->prepare("DELETE FROM insumo_presentaciones WHERE id = ?")->execute([$_GET['del_pres']]);
    header("Location: insumos.php"); exit();
}

// 4. CONSULTA DE INSUMOS Y SUS PRESENTACIONES (Actualizada para mostrar el precio)
$query_insumos = "
    SELECT i.*, p.nombre_empresa,
    (SELECT GROUP_CONCAT(CONCAT(cantidad_capacidad, ' ', unidad_medida, ' ($', precio_presentacion, ')') ORDER BY cantidad_capacidad ASC SEPARATOR ', ') 
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
    <title>Materias Primas | AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); }
        .modal-content { background:white; width:90%; max-width:450px; margin:5% auto; padding:25px; border-radius:12px; }
        .form-group { margin-bottom: 15px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        .badge-pres { background: #ebf8ff; color: #2b6cb0; padding: 4px 10px; border-radius: 8px; font-size: 0.8rem; margin-bottom: 5px; display: inline-block; border: 1px solid #bee3f8; }
        .btn-pres { background: #4c51bf; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; transition: 0.3s; }
        .btn-pres:hover { background: #3c366b; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <div class="header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h1><i class="fas fa-flask"></i> Materias Primas / Insumos</h1>
            <button class="btn" onclick="document.getElementById('modalInsumo').style.display='block'">
                <i class="fas fa-plus"></i> Nuevo Insumo
            </button>
        </div>

        <div class="search-container" style="margin-bottom:20px;">
            <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Buscar insumo o proveedor..." class="form-control" style="max-width:400px;">
        </div>

        <table id="insumosTable" style="width:100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
            <thead>
                <tr style="background: #f8fafc; text-align: left; border-bottom: 2px solid #edf2f7;">
                    <th style="padding:15px;">Nombre / Proveedor</th>
                    <th>Presentaciones y Precios</th>
                    <th>Precio Base (Kg/Lt)</th>
                    <th>Stock Actual</th>
                    <th style="text-align:center;">Gestión</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($insumos as $i): ?>
                <tr style="border-bottom: 1px solid #edf2f7;">
                    <td style="padding:15px;">
                        <span class="nombre-insumo" style="font-weight:600; display:block;"><?php echo htmlspecialchars($i['nombre']); ?></span>
                        <span class="nombre-proveedor" style="font-size:0.8rem; color:#718096;"><?php echo htmlspecialchars($i['nombre_empresa'] ?? 'N/A'); ?></span>
                    </td>
                    <td>
                        <div id="lista-pres-<?php echo $i['id']; ?>">
                            <?php if($i['presentaciones_lista']): ?>
                                <?php 
                                    $tags = explode(', ', $i['presentaciones_lista']);
                                    foreach($tags as $tag) echo "<span class='badge-pres'>$tag</span> ";
                                ?>
                            <?php else: ?>
                                <small style="color:#a0aec0;">Sin múltiplos configurados</small>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>$<?php echo number_format($i['precio_unitario'], 2); ?></td>
                    <td><strong><?php echo (float)$i['stock_actual']; ?></strong> <small><?php echo $i['unidad_medida']; ?></small></td>
                    <td style="text-align:center;">
                        <button class="btn-pres" onclick="abrirModalPres(<?php echo $i['id']; ?>, '<?php echo $i['nombre']; ?>')">
                            <i class="fas fa-boxes"></i> Configurar
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="modalInsumo" class="modal">
        <div class="modal-content">
            <h3 style="margin-top:0;"><i class="fas fa-plus-circle"></i> Registrar Nuevo Insumo</h3>
            <form method="POST">
                <input type="hidden" name="nuevo_insumo" value="1">
                <div class="form-group"><label>Nombre del Insumo:</label><input type="text" name="nombre" class="form-control" placeholder="Ej. Ácido Cítrico" required></div>
                <div style="display:flex; gap:10px;">
                    <div class="form-group" style="flex:1;"><label>Unidad:</label><input type="text" name="unidad" class="form-control" placeholder="KG/LT" required></div>
                    <div class="form-group" style="flex:1;"><label>Precio Base U.:</label><input type="number" name="precio" step="0.001" class="form-control" required></div>
                </div>
                <div class="form-group">
                    <label>Proveedor Responsable:</label>
                    <select name="id_proveedor" class="form-control" required>
                        <?php foreach ($proveedores as $p): ?>
                            <option value="<?php echo $p['id_proveedor']; ?>"><?php echo $p['nombre_empresa']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn" style="width:100%; background:#28a745; color:white; border:none; padding:12px; border-radius:6px; cursor:pointer;">Guardar en Catálogo</button>
                <button type="button" onclick="document.getElementById('modalInsumo').style.display='none'" style="width:100%; background:none; border:none; color:#666; margin-top:10px; cursor:pointer;">Cancelar</button>
            </form>
        </div>
    </div>

    <div id="modalPres" class="modal">
        <div class="modal-content">
            <h3 id="pres_nombre_insumo" style="margin-top:0;">Gestionar Múltiplos</h3>
            <p style="font-size:0.85rem; color:#4a5568; margin-bottom:20px;">Registra los formatos de venta del proveedor para este insumo.</p>
            
            <form method="POST" style="background: #f7fafc; padding: 15px; border-radius: 8px; border: 1px solid #edf2f7;">
                <input type="hidden" name="add_presentacion" value="1">
                <input type="hidden" name="id_insumo_pres" id="id_insumo_pres">
                
                <div class="form-group">
                    <label>Capacidad del Envase (Múltiplo):</label>
                    <input type="number" name="capacidad" step="0.001" class="form-control" placeholder="Ej: 20" required>
                </div>
                
                <div class="form-group">
                    <label>Precio Total del Envase ($):</label>
                    <input type="number" name="precio_pres" step="0.01" class="form-control" placeholder="Ej: 450.00" required>
                    <small style="color: #718096; display:block; margin-top:5px;">El sistema calculará automáticamente el costo prorrateado por kilo.</small>
                </div>
                
                <button type="submit" class="btn" style="width:100%; background: #4c51bf; color:white; border:none; padding:10px; border-radius:6px; cursor:pointer; font-weight:600;">
                    Añadir Presentación
                </button>
            </form>
            
            <button type="button" onclick="document.getElementById('modalPres').style.display='none'" style="width:100%; margin-top:15px; background:#edf2f7; border:none; padding:10px; border-radius:6px; cursor:pointer; color:#4a5568;">Cerrar</button>
        </div>
    </div>

    <script>
        function abrirModalPres(id, nombre) {
            document.getElementById('id_insumo_pres').value = id;
            document.getElementById('pres_nombre_insumo').innerText = "Configurar: " + nombre;
            document.getElementById('modalPres').style.display = 'block';
        }

        function filterTable() {
            let filter = document.getElementById("searchInput").value.toLowerCase();
            let rows = document.querySelectorAll("#insumosTable tbody tr");
            rows.forEach(row => {
                let nombre = row.querySelector(".nombre-insumo").innerText.toLowerCase();
                let prov = row.querySelector(".nombre-proveedor").innerText.toLowerCase();
                row.style.display = (nombre.includes(filter) || prov.includes(filter)) ? "" : "none";
            });
        }

        window.onclick = function(event) {
            if (event.target.className === 'modal') event.target.style.display = "none";
        }
    </script>
</body>
</html>