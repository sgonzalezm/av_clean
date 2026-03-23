<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// 1. OBTENER LISTA DE PROVEEDORES PARA EL SELECT DEL MODAL
$stmt_prov = $pdo->query("SELECT id_proveedor, nombre_empresa FROM proveedores ORDER BY nombre_empresa ASC");
$proveedores = $stmt_prov->fetchAll();

// 2. PROCESAR GUARDADO DE NUEVO INSUMO
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

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// 3. CONSULTA DE INSUMOS CON JOIN
$query_insumos = "
    SELECT i.*, p.nombre_empresa 
    FROM insumos i 
    LEFT JOIN proveedores p ON i.id_proveedor = p.id_proveedor 
    ORDER BY i.nombre ASC";
$insumos = $pdo->query($query_insumos)->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materias Primas - AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); }
        .modal-content { background:white; width:90%; max-width:450px; margin:5% auto; padding:25px; border-radius:12px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .form-group { margin-bottom: 15px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        .stock-alerta { color: red; font-weight: bold; }
        
        /* Estilos para el buscador */
        .search-container { margin-bottom: 20px; position: relative; max-width: 400px; }
        .search-container input { width: 100%; padding: 12px 40px 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; outline: none; transition: border 0.3s; }
        .search-container input:focus { border-color: #4c51bf; box-shadow: 0 0 5px rgba(76, 81, 191, 0.2); }
        .search-container i { position: absolute; right: 15px; top: 15px; color: #aaa; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <div class="header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h1><i class="fas fa-flask"></i> Materias Primas / Insumos</h1>
            <button class="btn" onclick="document.getElementById('modalInsumo').style.display='block'" style="padding:10px 20px; cursor:pointer;">
                <i class="fas fa-plus"></i> Nuevo Insumo
            </button>
        </div>

        <div class="search-container">
            <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Buscar insumo o proveedor...">
            <i class="fas fa-search"></i>
        </div>

        <table id="insumosTable" border="1" style="width:100%; border-collapse: collapse; background: white;">
            <thead>
                <tr style="background: #f4f4f4;">
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Proveedor</th>
                    <th>Unidad</th>
                    <th>Precio Unit.</th>
                    <th>Stock Actual</th>
                    <th>Mínimo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($insumos as $i): ?>
                <tr>
                    <td><?php echo $i['id']; ?></td>
                    <td class="nombre-insumo"><strong><?php echo htmlspecialchars($i['nombre']); ?></strong></td>
                    <td class="nombre-proveedor"><?php echo htmlspecialchars($i['nombre_empresa'] ?? 'SIN PROVEEDOR'); ?></td>
                    <td><?php echo $i['unidad_medida']; ?></td>
                    <td>$<?php echo number_format($i['precio_unitario'], 2); ?></td>
                    <td class="<?php echo ($i['stock_actual'] <= $i['cantidad_minima']) ? 'stock-alerta' : ''; ?>">
                        <?php echo $i['stock_actual']; ?>
                    </td>
                    <td><?php echo $i['cantidad_minima']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="modalInsumo" class="modal">
        <div class="modal-content">
            <h2 style="margin-top:0;"><i class="fas fa-edit"></i> Registrar Insumo</h2>
            <hr>
            <form method="POST">
                <input type="hidden" name="nuevo_insumo" value="1">
                <div class="form-group">
                    <label>Nombre del Insumo:</label>
                    <input type="text" name="nombre" class="form-control" placeholder="Ej. Ácido Cítrico" required>
                </div>
                <div style="display:flex; gap:10px;">
                    <div class="form-group" style="flex:1;">
                        <label>Unidad:</label>
                        <input type="text" name="unidad" class="form-control" placeholder="KG o LT" required>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Precio Unit.:</label>
                        <input type="number" name="precio" step="0.001" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Stock Mínimo (Alerta):</label>
                    <input type="number" name="cantidad_minima" class="form-control" value="0" required>
                </div>
                <div class="form-group">
                    <label>Proveedor Responsable:</label>
                    <select name="id_proveedor" class="form-control" required>
                        <option value="">-- Seleccione --</option>
                        <?php foreach ($proveedores as $p): ?>
                            <option value="<?php echo $p['id_proveedor']; ?>">
                                <?php echo htmlspecialchars($p['nombre_empresa']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-top:20px;">
                    <button type="submit" class="btn" style="width:100%; padding:12px; background:#28a745; color:white; border:none; border-radius:5px; cursor:pointer;">
                        <i class="fas fa-save"></i> Guardar en Inventario
                    </button>
                    <button type="button" onclick="document.getElementById('modalInsumo').style.display='none'" 
                            style="width:100%; margin-top:10px; background:none; border:none; color:#666; cursor:pointer;">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Lógica del Buscador en tiempo real
        function filterTable() {
            let input = document.getElementById("searchInput");
            let filter = input.value.toLowerCase();
            let table = document.getElementById("insumosTable");
            let tr = table.getElementsByTagName("tr");

            // Recorrer todas las filas de la tabla (excepto el encabezado)
            for (let i = 1; i < tr.length; i++) {
                let tdNombre = tr[i].getElementsByClassName("nombre-insumo")[0];
                let tdProveedor = tr[i].getElementsByClassName("nombre-proveedor")[0];
                
                if (tdNombre || tdProveedor) {
                    let txtNombre = tdNombre.textContent || tdNombre.innerText;
                    let txtProveedor = tdProveedor.textContent || tdProveedor.innerText;
                    
                    // Si el filtro coincide con el nombre o el proveedor, mostrar fila
                    if (txtNombre.toLowerCase().indexOf(filter) > -1 || 
                        txtProveedor.toLowerCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        }

        window.onclick = function(event) {
            let modal = document.getElementById('modalInsumo');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>