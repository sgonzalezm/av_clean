<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// --- 1. LÓGICA DE PROCESAMIENTO (UPDATE / DELETE) ---
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
        if ($_POST['action'] == 'editar') {
            $sql = "UPDATE productos SET nombre=?, descripcion=?, precio=?, categoria=?, id_formula_maestra=?, volumen_valor=?, volumen_unidad=? WHERE id=?";
            $pdo->prepare($sql)->execute([$nombre, $descripcion, $precio, $categoria, $id_formula, $volumen_valor, $volumen_unidad, $id]);
            header("Location: catalogo_productos.php?msj=Actualizado");
        } elseif ($_POST['action'] == 'eliminar') {
            $sql = "DELETE FROM productos WHERE id = ?";
            $pdo->prepare($sql)->execute([$id]);
            header("Location: catalogo_productos.php?msj=Eliminado");
        }
        exit;
    } catch (Exception $e) { $error = "Error: " . $e->getMessage(); }
}

// --- 2. CONSULTA DE DATOS CON CÁLCULO DE COSTO ---
$categorias = $pdo->query("SELECT nombre FROM categorias ORDER BY nombre ASC")->fetchAll();
$formulas_list = $pdo->query("SELECT id, nombre_formula FROM formulas_maestras ORDER BY nombre_formula ASC")->fetchAll();

$query = "SELECT p.*, f.nombre_formula,
          (SELECT SUM(fr.cantidad_por_litro * i.precio_unitario) 
           FROM formulas fr 
           JOIN insumos i ON fr.insumo_id = i.id 
           WHERE fr.id_formula_maestra = p.id_formula_maestra) as costo_por_litro
          FROM productos p
          LEFT JOIN formulas_maestras f ON p.id_formula_maestra = f.id 
          ORDER BY p.id DESC";
$productos = $pdo->query($query)->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Inventario y Rentabilidad | AHD Clean</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .badge-vol { background: #f1f5f9; color: #475569; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; }
        .product-img { width: 45px; height: 45px; border-radius: 8px; object-fit: cover; background: #eee; }
        .col-money { text-align: right; font-weight: bold; }
        .rent-alta { color: #059669; background: #ecfdf5; padding: 4px 8px; border-radius: 6px; } 
        .rent-media { color: #d97706; background: #fffbeb; padding: 4px 8px; border-radius: 6px; } 
        .rent-baja { color: #dc2626; background: #fef2f2; padding: 4px 8px; border-radius: 6px; } 

        /* Buscador */
        .search-container { position: relative; width: 300px; }
        .search-container input { width: 100%; padding: 10px 15px 10px 40px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.9rem; }
        .search-container i { position: absolute; left: 15px; top: 12px; color: #94a3b8; }

        .modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:2000; align-items:center; justify-content:center; }
        .modal-content { background:white; padding:25px; border-radius:15px; width:100%; max-width:500px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #4a5568; font-size: 0.9rem; }
        .form-input { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; box-sizing: border-box; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 25px;">
            <div>
                <h1><i class="fas fa-chart-line"></i> Catálogo y Rentabilidad</h1>
            </div>
            
            <div style="display:flex; gap:15px; align-items:center;">
                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" id="busqueda" placeholder="Buscar producto o fórmula...">
                </div>

                <a href="catalogo_agregar.php" class="btn-guardar" style="background:#3b82f6; color:white; text-decoration:none; padding:10px 15px; border-radius:8px; font-weight:bold;">
                    <i class="fas fa-plus"></i> Nuevo Producto
                </a>
            </div>
        </div>

        <div class="table-container">
            <table id="tablaProductos">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                        <th style="padding:15px; text-align:left;">Producto</th>
                        <th>Costo Fab.</th>
                        <th>Venta</th>
                        <th>Utilidad</th>
                        <th>Margen</th>
                        <th style="text-align:center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $p): 
                        $costo_unidad = ($p['costo_por_litro'] ?? 0) * $p['volumen_valor'];
                        $utilidad = $p['precio'] - $costo_unidad;
                        $margen = ($p['precio'] > 0) ? ($utilidad / $p['precio']) * 100 : 0;
                        $clase_rent = ($margen >= 40) ? 'rent-alta' : (($margen >= 20) ? 'rent-media' : 'rent-baja');
                        
                        // Data para búsqueda
                        $search_data = strtolower(htmlspecialchars($p['nombre'] . ' ' . ($p['nombre_formula'] ?? '')));
                    ?>
                    <tr class="fila-producto" data-search="<?php echo $search_data; ?>" style="border-bottom: 1px solid #f1f5f9;">
                        <td>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <img src="<?php echo $p['imagen_url'] ?: '../img/placeholder.png'; ?>" class="product-img">
                                <div>
                                    <strong class="nombre-prod"><?php echo htmlspecialchars($p['nombre']); ?></strong>
                                    <span class="badge-vol"><?php echo (float)$p['volumen_valor'] . $p['volumen_unidad']; ?></span><br>
                                    <small style="color:#64748b;"><?php echo $p['nombre_formula'] ?: 'Sin fórmula'; ?></small>
                                </div>
                            </div>
                        </td>
                        <td class="col-money" style="color:#64748b;">$<?php echo number_format($costo_unidad, 2); ?></td>
                        <td class="col-money">$<?php echo number_format($p['precio'], 2); ?></td>
                        <td class="col-money" style="color:#059669;">$<?php echo number_format($utilidad, 2); ?></td>
                        <td style="text-align:right;"><span class="<?php echo $clase_rent; ?>"><?php echo number_format($margen, 1); ?>%</span></td>
                        <td style="text-align:center;">
                            <button onclick='abrirEditar(<?php echo json_encode($p); ?>)' style="color:#3b82f6; border:none; background:none; cursor:pointer; font-size:1.1rem;"><i class="fas fa-edit"></i></button>
                            <button onclick="confirmarEliminar(<?php echo $p['id']; ?>)" style="color:#ef4444; border:none; background:none; cursor:pointer; margin-left:10px;"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="modalProd" class="modal-overlay">
        <div class="modal-content">
            <h2 style="margin-top:0; margin-bottom:20px;"><i class="fas fa-edit"></i> Modificar Producto</h2>
            <form method="POST">
                <input type="hidden" name="action" value="editar">
                <input type="hidden" name="id" id="m_id">
                
                <div class="form-group">
                    <label>Nombre del Producto</label>
                    <input type="text" name="nombre" id="m_nombre" class="form-input" required>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Volumen</label>
                        <input type="number" step="0.01" name="volumen_valor" id="m_vol_val" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label>Unidad</label>
                        <select name="volumen_unidad" id="m_vol_uni" class="form-input">
                            <option value="L">Litros (L)</option>
                            <option value="ml">Mililitros (ml)</option>
                            <option value="Gal">Galones (Gal)</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Precio de Venta</label>
                        <input type="number" step="0.01" name="precio" id="m_precio" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label>Categoría</label>
                        <select name="categoria" id="m_cat" class="form-input">
                            <?php foreach($categorias as $c): ?>
                                <option value="<?php echo $c['nombre']; ?>"><?php echo $c['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Fórmula Maestra Asociada</label>
                    <select name="id_formula_maestra" id="m_form" class="form-input">
                        <option value="">Ninguna (Producto sin receta)</option>
                        <?php foreach($formulas_list as $f): ?>
                            <option value="<?php echo $f['id']; ?>"><?php echo $f['nombre_formula']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" onclick="cerrarModal()" style="padding:10px 20px; border:none; border-radius:8px; cursor:pointer; background:#e2e8f0;">Cancelar</button>
                    <button type="submit" style="padding:10px 25px; background:#3b82f6; color:white; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">Actualizar Datos</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // LÓGICA DEL BUSCADOR
        document.getElementById('busqueda').addEventListener('keyup', function() {
            let filtro = this.value.toLowerCase();
            let filas = document.querySelectorAll('.fila-producto');

            filas.forEach(fila => {
                let texto = fila.getAttribute('data-search');
                if (texto.includes(filtro)) {
                    fila.style.display = "";
                } else {
                    fila.style.display = "none";
                }
            });
        });

        const modal = document.getElementById('modalProd');

        function abrirEditar(p) {
            document.getElementById('m_id').value = p.id;
            document.getElementById('m_nombre').value = p.nombre;
            document.getElementById('m_precio').value = p.precio;
            document.getElementById('m_vol_val').value = p.volumen_valor;
            document.getElementById('m_vol_uni').value = p.volumen_unidad;
            document.getElementById('m_cat').value = p.categoria;
            document.getElementById('m_form').value = p.id_formula_maestra || "";
            modal.style.display = 'flex';
        }

        function cerrarModal() { modal.style.display = 'none'; }

        function confirmarEliminar(id) {
            if(confirm('¿Seguro que deseas eliminar este producto?')) {
                const f = document.createElement('form');
                f.method = 'POST';
                f.innerHTML = `<input type="hidden" name="action" value="eliminar"><input type="hidden" name="id" value="${id}">`;
                document.body.appendChild(f);
                f.submit();
            }
        }

        window.onclick = function(event) { if (event.target == modal) { cerrarModal(); } }
    </script>
</body>
</html>