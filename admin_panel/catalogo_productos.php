<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// --- LOGICA DE PROCESAMIENTO (UPDATE / DELETE) ---
// Nota: Dejamos el INSERT aquí por si acaso, pero el "Nuevo" irá a catalogo_agregar.php
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $id = $_POST['id'] ?? null;
    $nombre = $_POST['nombre'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $precio = $_POST['precio'] ?? 0;
    $categoria = $_POST['categoria'] ?? ''; 
    $id_formula = !empty($_POST['id_formula_maestra']) ? $_POST['id_formula_maestra'] : null;
    $volumen_valor = $_POST['volumen_valor'] ?? 1;
    $volumen_unidad = $_POST['volumen_unidad'] ?? 'L';

    try {
        if ($_POST['action'] == 'crear') {
            $sql = "INSERT INTO productos (nombre, descripcion, precio, categoria, id_formula_maestra, volumen_valor, volumen_unidad) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$nombre, $descripcion, $precio, $categoria, $id_formula, $volumen_valor, $volumen_unidad]);
            header("Location: catalogo_productos.php?msj=Creado");
        } elseif ($_POST['action'] == 'editar') {
            $sql = "UPDATE productos SET nombre=?, descripcion=?, precio=?, categoria=?, id_formula_maestra=?, volumen_valor=?, volumen_unidad=? WHERE id=?";
            $pdo->prepare($sql)->execute([$nombre, $descripcion, $precio, $categoria, $id_formula, $volumen_valor, $volumen_unidad, $id]);
            header("Location: catalogo_productos.php?msj=Actualizado");
        } elseif ($_POST['action'] == 'eliminar') {
            $sql = "DELETE FROM productos WHERE id = ?";
            $pdo->prepare($sql)->execute([$id]);
            header("Location: catalogo_productos.php?msj=Eliminado");
        }
        exit;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// --- CONSULTA DE DATOS ---
$categorias = $pdo->query("SELECT nombre FROM categorias ORDER BY nombre ASC")->fetchAll();
$formulas_list = $pdo->query("SELECT id, nombre_formula FROM formulas_maestras ORDER BY nombre_formula ASC")->fetchAll();

$query = "SELECT p.*, f.nombre_formula 
          FROM productos p
          LEFT JOIN formulas_maestras f ON p.id_formula_maestra = f.id 
          ORDER BY p.id DESC";
$productos = $pdo->query($query)->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Productos | AHD Clean</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .formula-tag { background: #f0fdf4; color: #166534; padding: 4px 10px; border-radius: 15px; font-size: 0.85rem; text-decoration: none; border: 1px solid #bbf7d0; font-weight: 600; }
        .product-img { width: 50px; height: 50px; border-radius: 8px; object-fit: cover; background: #f1f5f9; }
        .badge-vol { background: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; margin-left: 5px; }
        .modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:2000; align-items:center; justify-content:center; padding: 15px; }
        .modal-content { background:white; padding:25px; border-radius:15px; width:100%; max-width:500px; max-height: 90vh; overflow-y: auto; }
    </style>
</head>
<body>
    
    <button class="menu-toggle" id="btnToggle"><i class="fas fa-bars"></i></button>

    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
            <h1><i class="fas fa-boxes"></i> Catálogo de Productos</h1>
            <a href="catalogo_agregar.php" class="btn-guardar" style="background:#3b82f6; color:white; text-decoration:none; padding:10px 15px; border-radius:8px; font-weight:bold;">
                <i class="fas fa-plus"></i> Nuevo Producto
            </a>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Fórmula</th>
                        <th style="text-align:right;">Precio</th>
                        <th style="text-align:center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $p): ?>
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <img src="<?php echo $p['imagen_url'] ?: '../img/placeholder.png'; ?>" class="product-img">
                                <div>
                                    <strong><?php echo htmlspecialchars($p['nombre']); ?></strong>
                                    <span class="badge-vol"><?php echo number_format($p['volumen_valor'], 0) . $p['volumen_unidad']; ?></span><br>
                                    <small style="color:#64748b;"><?php echo htmlspecialchars($p['descripcion']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge-cat"><?php echo htmlspecialchars($p['categoria']); ?></span></td>
                        <td>
                            <?php if($p['id_formula_maestra']): ?>
                                <span class="formula-tag"><i class="fas fa-flask"></i> <?php echo htmlspecialchars($p['nombre_formula']); ?></span>
                            <?php else: ?>
                                <small style="color:#cbd5e1;">Sin fórmula</small>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right; font-weight:bold; color: #059669;">$<?php echo number_format($p['precio'], 2); ?></td>
                        <td style="text-align:center;">
                            <button onclick='editar(<?php echo json_encode($p); ?>)' style="color:#3b82f6; border:none; background:none; cursor:pointer; font-size:1.1rem;"><i class="fas fa-edit"></i></button>
                            <button onclick="eliminar(<?php echo $p['id']; ?>)" style="color:#ef4444; border:none; background:none; cursor:pointer; margin-left:15px; font-size:1.1rem;"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="modalProd" class="modal-overlay">
        <div class="modal-content">
            <h2 id="modalTitle">Editar Producto</h2>
            <form id="formProd" method="POST">
                <input type="hidden" name="action" id="action" value="editar">
                <input type="hidden" name="id" id="prod_id">
                
                <div style="margin-bottom:15px;">
                    <label style="display:block; margin-bottom:5px; font-weight:600;">Nombre del Producto</label>
                    <input type="text" name="nombre" id="m_nombre" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;" required>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:15px;">
                    <div>
                        <label style="display:block; margin-bottom:5px; font-weight:600;">Volumen</label>
                        <input type="number" step="0.01" name="volumen_valor" id="m_vol_val" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:5px; font-weight:600;">Unidad</label>
                        <select name="volumen_unidad" id="m_vol_uni" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
                            <option value="L">Litros (L)</option>
                            <option value="ml">Mililitros (ml)</option>
                            <option value="Gal">Galones (Gal)</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:15px;">
                    <div>
                        <label style="display:block; margin-bottom:5px; font-weight:600;">Precio</label>
                        <input type="number" step="0.01" name="precio" id="m_precio" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;" required>
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:5px; font-weight:600;">Categoría</label>
                        <select name="categoria" id="m_cat" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;" required>
                            <?php foreach($categorias as $c): ?>
                                <option value="<?php echo $c['nombre']; ?>"><?php echo $c['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom:20px;">
                    <label style="display:block; margin-bottom:5px; font-weight:600;">Fórmula Maestra</label>
                    <select name="id_formula_maestra" id="m_form" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
                        <option value="">Ninguna</option>
                        <?php foreach($formulas_list as $f): ?>
                            <option value="<?php echo $f['id']; ?>"><?php echo $f['nombre_formula']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" onclick="cerrarModal()" style="padding:10px 15px; border:none; border-radius:8px; cursor:pointer;">Cancelar</button>
                    <button type="submit" style="padding:10px 20px; background:#3b82f6; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:bold;">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('modalProd');
        function cerrarModal() { modal.style.display = 'none'; }
        
        function editar(p) {
            document.getElementById('prod_id').value = p.id;
            document.getElementById('m_nombre').value = p.nombre;
            document.getElementById('m_precio').value = p.precio;
            document.getElementById('m_cat').value = p.categoria;
            document.getElementById('m_form').value = p.id_formula_maestra;
            document.getElementById('m_vol_val').value = p.volumen_valor;
            document.getElementById('m_vol_uni').value = p.volumen_unidad;
            modal.style.display = 'flex';
        }

        function eliminar(id) {
            if(confirm('¿Eliminar este producto permanentemente?')) {
                const f = document.createElement('form');
                f.method = 'POST';
                f.innerHTML = `<input type="hidden" name="action" value="eliminar"><input type="hidden" name="id" value="${id}">`;
                document.body.appendChild(f);
                f.submit();
            }
        }
    </script>
</body>
</html>