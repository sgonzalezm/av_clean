<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// --- LOGICA DE PROCESAMIENTO (INSERT / UPDATE / DELETE) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $id = $_POST['id'] ?? null;
    $nombre = $_POST['nombre'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $precio = $_POST['precio'] ?? 0;
    $categoria = $_POST['categoria'] ?? ''; 
    $id_formula = !empty($_POST['id_formula_maestra']) ? $_POST['id_formula_maestra'] : null;

    try {
        if ($_POST['action'] == 'crear') {
            $sql = "INSERT INTO productos (nombre, descripcion, precio, categoria, id_formula_maestra) VALUES (?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$nombre, $descripcion, $precio, $categoria, $id_formula]);
            header("Location: catalogo_productos.php?msj=Creado");
        } elseif ($_POST['action'] == 'editar') {
            $sql = "UPDATE productos SET nombre=?, descripcion=?, precio=?, categoria=?, id_formula_maestra=? WHERE id=?";
            $pdo->prepare($sql)->execute([$nombre, $descripcion, $precio, $categoria, $id_formula, $id]);
            header("Location: productos.php?msj=Actualizado");
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
        /* --- ESTILOS DE RESPONSIVIDAD Y UI --- */
        .menu-toggle {
            display: none; position: fixed; top: 15px; left: 15px; z-index: 1100;
            background: #1e293b; color: white; border: none; padding: 10px 15px; border-radius: 8px; cursor: pointer;
        }

        .sidebar-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 1040; backdrop-filter: blur(2px);
        }

        .search-container {
            margin: 20px 0; position: relative;
        }
        .search-container input {
            width: 100%; padding: 12px 12px 12px 45px; border-radius: 10px;
            border: 1px solid #e2e8f0; font-size: 1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .search-container i { position: absolute; left: 15px; top: 15px; color: #94a3b8; }

        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .main { padding: 75px 15px 20px 15px !important; margin-left: 0 !important; }
            .sidebar { position: fixed; left: -260px; top: 0; height: 100vh; width: 250px; transition: 0.3s; z-index: 1050; background: #1e293b; }
            .sidebar.active { left: 0; }
            .sidebar.active + .sidebar-overlay { display: block; }

            thead { display: none; }
            tr { display: block; background: white; margin-bottom: 15px; border-radius: 12px; padding: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
            td { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border: none !important; }
            td:first-child { display: block; border-bottom: 1px solid #f1f5f9 !important; padding-bottom: 15px; }
            td:nth-child(2)::before { content: "Categoría:"; font-weight: bold; color: #64748b; }
            td:nth-child(3)::before { content: "Fórmula:"; font-weight: bold; color: #64748b; }
            td:nth-child(4)::before { content: "Precio:"; font-weight: bold; color: #64748b; }
            td:last-child { justify-content: center; background: #f8fafc; margin: 10px -15px -15px -15px; padding: 15px; border-radius: 0 0 12px 12px; gap: 20px; }
        }

        .formula-tag { background: #f0fdf4; color: #166534; padding: 4px 10px; border-radius: 15px; font-size: 0.85rem; text-decoration: none; border: 1px solid #bbf7d0; font-weight: 600; }
        .product-img { width: 50px; height: 50px; border-radius: 8px; object-fit: cover; }
        .badge-cat { background: #e2e8f0; padding: 3px 8px; border-radius: 5px; font-size: 0.75rem; color: #475569; font-weight: bold; }
        .modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:2000; align-items:center; justify-content:center; padding: 15px; }
        .modal-content { background:white; padding:25px; border-radius:15px; width:100%; max-width:500px; max-height: 90vh; overflow-y: auto; }
    </style>
</head>
<body>
    
    <button class="menu-toggle" id="btnToggle"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay" id="overlay" onclick="toggleMenu()"></div>

    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header" style="display:flex; justify-content:space-between; align-items:center;">
            <h1><i class="fas fa-boxes"></i> Productos</h1>
            <button class="btn-guardar" onclick="abrirModal()" style="background:#3b82f6; color:white; border:none; padding:10px 15px; border-radius:8px; cursor:pointer;">
                <i class="fas fa-plus"></i> Nuevo
            </button>
        </div>

        <div class="search-container">
            <i class="fas fa-search"></i>
            <input type="text" id="productSearch" placeholder="Buscar por nombre, categoría o fórmula...">
        </div>

        <div class="table-container">
            <table id="productTable">
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
                    <tr class="product-row">
                        <td>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <img src="<?php echo $p['imagen_url'] ?: '../img/placeholder.png'; ?>" class="product-img">
                                <div>
                                    <strong class="search-name"><?php echo htmlspecialchars($p['nombre']); ?></strong><br>
                                    <small class="search-desc" style="color:#64748b;"><?php echo htmlspecialchars($p['descripcion']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge-cat search-cat"><?php echo htmlspecialchars($p['categoria']); ?></span></td>
                        <td>
                            <?php if($p['id_formula_maestra']): ?>
                                <a href="formulas_ver.php?id=<?php echo $p['id_formula_maestra']; ?>" class="formula-tag search-formula">
                                    <i class="fas fa-flask"></i> <?php echo htmlspecialchars($p['nombre_formula']); ?>
                                </a>
                            <?php else: ?>
                                <small style="color:#cbd5e1;">Sin fórmula</small>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right; font-weight:bold; color: #059669;">$<?php echo number_format($p['precio'], 2); ?></td>
                        <td>
                            <button onclick='editar(<?php echo json_encode($p); ?>)' style="color:#3b82f6; border:none; background:none; cursor:pointer;"><i class="fas fa-edit"></i></button>
                            <button onclick="eliminar(<?php echo $p['id']; ?>)" style="color:#ef4444; border:none; background:none; cursor:pointer; margin-left:15px;"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="modalProd" class="modal-overlay">
        <div class="modal-content">
            <h2 id="modalTitle">Nuevo Producto</h2>
            <form id="formProd" method="POST">
                <input type="hidden" name="action" id="action" value="crear">
                <input type="hidden" name="id" id="prod_id">
                <div style="margin-bottom:15px;">
                    <label style="display:block; margin-bottom:5px; font-weight:600;">Nombre</label>
                    <input type="text" name="nombre" id="m_nombre" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;" required>
                </div>
                <div style="margin-bottom:15px;">
                    <label style="display:block; margin-bottom:5px; font-weight:600;">Descripción</label>
                    <textarea name="descripcion" id="m_desc" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; height:80px;"></textarea>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:15px;">
                    <div>
                        <label style="display:block; margin-bottom:5px; font-weight:600;">Precio</label>
                        <input type="number" step="0.01" name="precio" id="m_precio" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;" required>
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:5px; font-weight:600;">Categoría</label>
                        <select name="categoria" id="m_cat" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;" required>
                            <option value="">Seleccionar...</option>
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
                    <button type="submit" style="padding:10px 20px; background:#3b82f6; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:bold;">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // --- BUSCADOR EN TIEMPO REAL ---
        document.getElementById('productSearch').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('.product-row');
            
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? "" : "none";
            });
        });

        // --- LÓGICA DEL SIDEBAR Y OVERLAY ---
        const btnToggle = document.getElementById('btnToggle');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('overlay');

        function toggleMenu() {
            sidebar.classList.toggle('active');
            overlay.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
        }

        btnToggle.addEventListener('click', toggleMenu);

        // --- MODAL ---
        const modal = document.getElementById('modalProd');
        function abrirModal() {
            document.getElementById('formProd').reset();
            document.getElementById('modalTitle').innerText = "Nuevo Producto";
            document.getElementById('action').value = "crear";
            modal.style.display = 'flex';
        }
        function cerrarModal() { modal.style.display = 'none'; }
        function editar(p) {
            document.getElementById('modalTitle').innerText = "Editar Producto";
            document.getElementById('action').value = "editar";
            document.getElementById('prod_id').value = p.id;
            document.getElementById('m_nombre').value = p.nombre;
            document.getElementById('m_desc').value = p.descripcion;
            document.getElementById('m_precio').value = p.precio;
            document.getElementById('m_cat').value = p.categoria;
            document.getElementById('m_form').value = p.id_formula_maestra;
            modal.style.display = 'flex';
        }
        function eliminar(id) {
            if(confirm('¿Eliminar este producto?')) {
                const f = document.createElement('form');
                f.method = 'POST';
                f.innerHTML = `<input type="hidden" name="action" value="eliminar"><input type="hidden" name="id" value="${id}">`;
                document.body.appendChild(f);
                f.submit();
            }
        }
        window.onclick = function(e) { if (e.target == modal) cerrarModal(); }
    </script>
</body>
</html>