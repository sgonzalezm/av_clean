<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// --- LÓGICA DE EXPORTACIÓN A EXCEL (NUEVO) ---
if (isset($_GET['exportar']) && $_GET['exportar'] == 'excel') {
    // 1. Limpieza total de búferes para evitar fugas de HTML
    if (ob_get_length()) ob_end_clean();

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Reporte_Insumos_AHD_Clean.xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    // Consulta de insumos idéntica a la del panel principal para coherencia de datos
    $query_export = "
        SELECT i.*, p.nombre_empresa,
        (SELECT GROUP_CONCAT(CONCAT(cantidad_capacidad, ' ', i.unidad_medida, ' ($', precio_presentacion, ')') SEPARATOR ' / ') 
         FROM insumo_presentaciones ip WHERE ip.id_insumo = i.id) as presentaciones_texto
        FROM insumos i 
        LEFT JOIN proveedores p ON i.id_proveedor = p.id_proveedor 
        ORDER BY i.nombre ASC";
    $insumos_export = $pdo->query($query_export)->fetchAll();

    // Estructura de Tabla Excel Nativa compatible
    echo '<table border="1" style="font-family: Arial, sans-serif;">';
    echo '<tr style="background-color: #3b82f6; color: white; font-weight: bold;">';
    echo '<th>ID</th>';
    echo '<th>Materia Prima / Insumo</th>';
    echo '<th>Proveedor</th>';
    echo '<th>Unidad Medida</th>';
    echo '<th>Precio Base Unitario</th>';
    echo '<th>Stock Actual</th>';
    echo '<th>Múltiplos / Presentaciones</th>';
    echo '</tr>';

    foreach ($insumos_export as $ie) {
        $presentaciones = !empty($ie['presentaciones_texto']) ? $ie['presentaciones_texto'] : 'Única';
        $proveedor = !empty($ie['nombre_empresa']) ? $ie['nombre_empresa'] : 'Sin Proveedor';
        
        echo '<tr>';
        echo '<td>' . $ie['id'] . '</td>';
        echo '<td>' . htmlspecialchars($ie['nombre']) . '</td>';
        echo '<td>' . htmlspecialchars($proveedor) . '</td>';
        echo '<td style="text-align: center;">' . htmlspecialchars($ie['unidad_medida']) . '</td>';
        echo '<td>$' . number_format($ie['precio_unitario'], 4) . '</td>';
        echo '<td style="font-weight: bold;">' . (float)$ie['stock_actual'] . '</td>';
        echo '<td>' . htmlspecialchars($presentaciones) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    exit();
}

// --- PROCESAMIENTO DE DATOS ---

// 1. Eliminar Múltiplo
if (isset($_GET['eliminar_pres'])) {
    $id_pres = $_GET['eliminar_pres'];
    $sql = "DELETE FROM insumo_presentaciones WHERE id = ?";
    $pdo->prepare($sql)->execute([$id_pres]);
    header("Location: insumos.php"); exit();
}

// 2. Editar Múltiplo Individual
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar_presentacion_unica'])) {
    $id_pres = $_POST['id']; 
    $capacidad = $_POST['capacidad_edit'];
    $precio = $_POST['precio_edit'];

    $sql = "UPDATE insumo_presentaciones SET cantidad_capacidad = ?, precio_presentacion = ? WHERE id = ?";
    $pdo->prepare($sql)->execute([$capacidad, $precio, $id_pres]);
    header("Location: insumos.php"); exit();
}

// 3. Editar Datos Básicos del Insumo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar_insumo_completo'])) {
    $id_insumo = $_POST['id_insumo_edit_full'];
    $nombre = $_POST['nombre_edit'];
    $unidad = $_POST['unidad_edit'];
    $id_prov = $_POST['id_proveedor_edit'];

    $sql_update_full = "UPDATE insumos SET nombre = ?, unidad_medida = ?, id_proveedor = ? WHERE id = ?";
    $pdo->prepare($sql_update_full)->execute([$nombre, $unidad, $id_prov, $id_insumo]);
    header("Location: insumos.php"); exit();
}

// 4. Procesar actualización de precio base
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_precio'])) {
    $id_insumo = $_POST['id_insumo_edit'];
    $nuevo_precio = $_POST['nuevo_precio'];
    $pdo->prepare("UPDATE insumos SET precio_unitario = ? WHERE id = ?")->execute([$nuevo_precio, $id_insumo]);
    header("Location: insumos.php"); exit();
}

// 5. Procesar nuevo insumo (RESTAURADO)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nuevo_insumo'])) {
    $nombre = $_POST['nombre'];
    $unidad = $_POST['unidad'];
    $precio = $_POST['precio'];
    $id_prov = !empty($_POST['id_proveedor']) ? $_POST['id_proveedor'] : null;

    $pdo->prepare("INSERT INTO insumos (nombre, unidad_medida, precio_unitario, id_proveedor) VALUES (?, ?, ?, ?)")
        ->execute([$nombre, $unidad, $precio, $id_prov]);
    header("Location: insumos.php"); exit();
}

// 6. Procesar agregar múltiplo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_presentacion'])) {
    $pdo->prepare("INSERT INTO insumo_presentaciones (id_insumo, cantidad_capacidad, precio_presentacion) VALUES (?, ?, ?)")
        ->execute([$_POST['id_insumo_pres'], $_POST['capacidad'], $_POST['precio_pres']]);
    header("Location: insumos.php"); exit();
}

// --- CONSULTA ---
$query_insumos = "
    SELECT i.*, p.nombre_empresa,
    (SELECT GROUP_CONCAT(CONCAT(id, ':', cantidad_capacidad, ':', precio_presentacion) SEPARATOR '||') 
     FROM insumo_presentaciones ip WHERE ip.id_insumo = i.id) as presentaciones_data
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
        .badge-pres { background: #ebf8ff; color: #2b6cb0; padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; margin: 2px; display: inline-block; border: 1px solid #bee3f8; }
        .modal { display:none; position:fixed; z-index:2000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.7); overflow-y: auto; }
        .modal-content { background:white; width:90%; max-width:450px; margin: 5% auto; padding:25px; border-radius:12px; position: relative; }
        .form-control { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        .btn-save { background: #28a745; color: white; border: none; padding: 12px; border-radius: 8px; width: 100%; font-weight: bold; cursor: pointer; margin-top: 10px; }
        .list-edit-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; border: 1px solid #eee; border-radius: 8px; margin-bottom: 8px; background: #fafafa; }
        .btn-edit-small { background: none; border: none; color: #3b82f6; cursor: pointer; font-size: 0.9rem; }
        .btn-del { color: #dc3545; background: none; border: none; cursor: pointer; margin-left: 10px; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
            <h1><i class="fas fa-flask"></i> Materias Primas</h1>
            <div style="display:flex; gap:10px;">
                <a href="insumos.php?exportar=excel" class="btn" style="background:#10b981; color:white; text-decoration:none; display:inline-flex; align-items:center; gap:8px; padding:10px 20px; border-radius:8px; font-weight:bold;">
                    <i class="fas fa-file-excel"></i> Exportar Excel
                </a>
                <button class="btn" onclick="document.getElementById('modalInsumo').style.display='block'" style="background:#3b82f6; color:white; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; font-weight:bold;">
                    <i class="fas fa-plus"></i> Nuevo Insumo
                </button>
            </div>
        </div>

        <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Buscar materia prima..." class="search-box" style="width:100%; padding:12px; margin-bottom:20px; border-radius:8px; border:1px solid #ddd;">

        <table id="insumosTable">
            <thead>
                <tr>
                    <th>Insumo / Proveedor</th>
                    <th>Presentaciones</th>
                    <th>Precio Base</th>
                    <th>Stock</th>
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
                        <?php 
                        if($i['presentaciones_data']): 
                            $pres = explode('||', $i['presentaciones_data']);
                            foreach($pres as $p_item) {
                                $data = explode(':', $p_item);
                                if(count($data) == 3){
                                    echo "<span class='badge-pres'>{$data[1]} {$i['unidad_medida']} ($${data[2]})</span>";
                                }
                            }
                        else: echo "<small style='color:#ccc;'>Única</small>"; endif; 
                        ?>
                    </td>
                    <td>
                        $<?php echo number_format($i['precio_unitario'], 4); ?>
                        <button class="btn-edit-small" onclick="abrirModalPrecio(<?php echo $i['id']; ?>, '<?php echo addslashes($i['nombre']); ?>', <?php echo $i['precio_unitario']; ?>)"><i class="fas fa-edit"></i></button>
                    </td>
                    <td><strong><?php echo (float)$i['stock_actual']; ?> <?php echo $i['unidad_medida']; ?></strong></td>
                    <td style="white-space:nowrap;">
                        <button class="btn" style="background:#f59e0b; color:white; padding:6px 10px; border-radius:6px; border:none; cursor:pointer;" 
                                onclick='abrirModalEditar(<?php echo json_encode($i); ?>)'>
                            <i class="fas fa-pen"></i> Editar
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="modalInsumo" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-plus-circle"></i> Nuevo Insumo</h3>
            <form method="POST">
                <input type="hidden" name="nuevo_insumo" value="1">
                <label>Nombre:</label>
                <input type="text" name="nombre" class="form-control" required placeholder="Nombre de la materia prima">
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div>
                        <label>Unidad:</label>
                        <input type="text" name="unidad" class="form-control" required placeholder="Kg, L, Pz">
                    </div>
                    <div>
                        <label>Precio Unitario:</label>
                        <input type="number" name="precio" step="0.0001" class="form-control" required placeholder="0.00">
                    </div>
                </div>

                <label>Proveedor:</label>
                <select name="id_proveedor" class="form-control">
                    <option value="">Sin proveedor</option>
                    <?php foreach ($proveedores as $p): ?>
                        <option value="<?php echo $p['id_proveedor']; ?>"><?php echo htmlspecialchars($p['nombre_empresa']); ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn-save">Guardar Materia Prima</button>
                <button type="button" onclick="document.getElementById('modalInsumo').style.display='none'" style="width:100%; margin-top:10px; background:none; border:none; color:#94a3b8; cursor:pointer;">Cancelar</button>
            </form>
        </div>
    </div>

    <div id="modalEditarFull" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom:10px;"><i class="fas fa-pen"></i> Editar Insumo</h3>
            <form method="POST">
                <input type="hidden" name="editar_insumo_completo" value="1">
                <input type="hidden" name="id_insumo_edit_full" id="id_insumo_edit_full">
                
                <label>Nombre:</label>
                <input type="text" name="nombre_edit" id="nombre_edit" class="form-control" required>
                
                <div style="display:flex; gap:10px;">
                    <div style="flex:1;">
                        <label>Unidad:</label>
                        <input type="text" name="unidad_edit" id="unidad_edit" class="form-control" required>
                    </div>
                    <div style="flex:2;">
                        <label>Proveedor:</label>
                        <select name="id_proveedor_edit" id="id_proveedor_edit" class="form-control">
                            <?php foreach ($proveedores as $p): ?>
                                <option value="<?php echo $p['id_proveedor']; ?>"><?php echo $p['nombre_empresa']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-save">Actualizar Datos Básicos</button>
            </form>

            <hr style="margin:20px 0; border:0; border-top:1px solid #eee;">
            
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h4>Múltiplos</h4>
                <button type="button" class="btn" style="font-size:0.8rem; background:#4c51bf; color:white; border:none; padding:5px 10px; border-radius:5px; cursor:pointer;" onclick="abrirModalPresDirecto()">
                    <i class="fas fa-plus"></i> Nuevo Múltiplo
                </button>
            </div>

            <div id="lista_multiplos_edit" style="margin-top:10px;"></div>
            
            <button type="button" onclick="document.getElementById('modalEditarFull').style.display='none'" style="width:100%; margin-top:15px; background:#eee; border:none; padding:10px; border-radius:8px; cursor:pointer;">Cerrar</button>
        </div>
    </div>

    <div id="modalEditSinglePres" class="modal" style="z-index:3000;">
        <div class="modal-content" style="max-width:350px; margin-top:15%;">
            <h4>Editar Múltiplo</h4>
            <form method="POST">
                <input type="hidden" name="editar_presentacion_unica" value="1">
                <input type="hidden" name="id" id="id_pres_val">
                <label>Capacidad:</label>
                <input type="number" name="capacidad_edit" id="cap_val" step="0.001" class="form-control" required>
                <label>Precio:</label>
                <input type="number" name="precio_edit" id="pre_val" step="0.01" class="form-control" required>
                <button type="submit" class="btn-save" style="background:#3b82f6;">Guardar Cambio</button>
                <button type="button" onclick="document.getElementById('modalEditSinglePres').style.display='none'" style="width:100%; margin-top:10px; border:none; background:none; color:red; cursor:pointer;">Cancelar</button>
            </form>
        </div>
    </div>

    <div id="modalPres" class="modal">
        <div class="modal-content">
            <h3 id="pres_nombre_insumo">Agregar Múltiplo</h3>
            <form method="POST">
                <input type="hidden" name="add_presentacion" value="1">
                <input type="hidden" name="id_insumo_pres" id="id_insumo_pres">
                <label>Capacidad:</label>
                <input type="number" name="capacidad" step="0.001" class="form-control" required>
                <label>Precio:</label>
                <input type="number" name="precio_pres" step="0.01" class="form-control" required>
                <button type="submit" class="btn-save" style="background:#4c51bf;">Guardar Múltiplo</button>
                <button type="button" onclick="document.getElementById('modalPres').style.display='none'" style="width:100%; margin-top:10px; background:none; border:none; cursor:pointer;">Cerrar</button>
            </form>
        </div>
    </div>

    <div id="modalPrecio" class="modal">
        <div class="modal-content">
            <h3 id="edit_nombre_insumo">Actualizar Precio</h3>
            <form method="POST">
                <input type="hidden" name="update_precio" value="1">
                <input type="hidden" name="id_insumo_edit" id="id_insumo_edit">
                <label>Nuevo Precio Base:</label>
                <input type="number" name="nuevo_precio" id="nuevo_precio_input" step="0.0001" class="form-control" required>
                <button type="submit" class="btn-save" style="background:#3b82f6;">Actualizar</button>
            </form>
        </div>
    </div>

    <script>
        let currentInsumoId = null;
        let currentInsumoNombre = "";

        function abrirModalEditar(insumo) {
            currentInsumoId = insumo.id;
            currentInsumoNombre = insumo.nombre;
            document.getElementById('id_insumo_edit_full').value = insumo.id;
            document.getElementById('nombre_edit').value = insumo.nombre;
            document.getElementById('unidad_edit').value = insumo.unidad_medida;
            document.getElementById('id_proveedor_edit').value = insumo.id_proveedor;

            const listaDiv = document.getElementById('lista_multiplos_edit');
            listaDiv.innerHTML = "";
            
            if (insumo.presentaciones_data) {
                const pres = insumo.presentaciones_data.split('||');
                pres.forEach(p => {
                    const [idp, cap, pre] = p.split(':');
                    listaDiv.innerHTML += `
                        <div class="list-edit-item">
                            <span><strong>${cap}</strong> ${insumo.unidad_medida} - $${pre}</span>
                            <div>
                                <button type="button" class="btn-edit-small" onclick="editarUnSoloMultiplo(${idp}, ${cap}, ${pre})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn-del" onclick="eliminarMultiplo(${idp})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>`;
                });
            } else {
                listaDiv.innerHTML = "<p style='color:#999; font-size:0.9rem;'>No hay múltiplos registrados.</p>";
            }
            document.getElementById('modalEditarFull').style.display = 'block';
        }

        function abrirModalPresDirecto() {
            document.getElementById('id_insumo_pres').value = currentInsumoId;
            document.getElementById('pres_nombre_insumo').innerText = "Nuevo Múltiplo para: " + currentInsumoNombre;
            document.getElementById('modalPres').style.display = 'block';
        }

        function editarUnSoloMultiplo(id, cap, pre) {
            document.getElementById('id_pres_val').value = id;
            document.getElementById('cap_val').value = cap;
            document.getElementById('pre_val').value = pre;
            document.getElementById('modalEditSinglePres').style.display = 'block';
        }

        function eliminarMultiplo(id) {
            if(confirm('¿Seguro que deseas eliminar este múltiplo?')) {
                window.location.href = 'insumos.php?eliminar_pres=' + id;
            }
        }

        function abrirModalPrecio(id, nombre, precioActual) {
            document.getElementById('id_insumo_edit').value = id;
            document.getElementById('edit_nombre_insumo').innerText = "Editar precio: " + nombre;
            document.getElementById('nuevo_precio_input').value = precioActual;
            document.getElementById('modalPrecio').style.display = 'block';
        }

        function filterTable() {
            let filter = document.getElementById("searchInput").value.toLowerCase();
            let rows = document.querySelectorAll("#insumosTable tbody tr");
            rows.forEach(row => { row.style.display = row.innerText.toLowerCase().includes(filter) ? "" : "none"; });
        }

        window.onclick = function(e) { if (e.target.className === 'modal') e.target.style.display = "none"; }
    </script>
</body>
</html>