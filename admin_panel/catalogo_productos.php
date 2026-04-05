<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// --- 1. PROCESAMIENTO DE DATOS (NUEVO / EDITAR / ELIMINAR) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $id = $_POST['id'] ?? null;
    $nombre = $_POST['nombre'] ?? '';
    $precio = $_POST['precio'] ?? 0;
    $categoria = $_POST['categoria'] ?? ''; 
    $id_formula = !empty($_POST['id_formula_maestra']) ? $_POST['id_formula_maestra'] : null;
    $vol_val = $_POST['volumen_valor'] ?? 1;
    $vol_uni = $_POST['volumen_unidad'] ?? 'L';

    try {
        // ACCIÓN: NUEVO PRODUCTO
        if ($_POST['action'] == 'nuevo') {
            $pdo->beginTransaction();
            $sql = "INSERT INTO productos (nombre, precio, categoria, id_formula_maestra, volumen_valor, volumen_unidad) VALUES (?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$nombre, $precio, $categoria, $id_formula, $vol_val, $vol_uni]);
            $nuevo_id = $pdo->lastInsertId();
            
            // Creamos entrada automática en inventario para evitar errores de integridad después
            $pdo->prepare("INSERT INTO inventario (producto_id, stock) VALUES (?, 0)")->execute([$nuevo_id]);
            
            $pdo->commit();
            header("Location: catalogo_productos.php?msj=Creado"); exit;
        } 
        
        // ACCIÓN: EDITAR PRODUCTO
        elseif ($_POST['action'] == 'editar') {
            $sql = "UPDATE productos SET nombre=?, precio=?, categoria=?, id_formula_maestra=?, volumen_valor=?, volumen_unidad=? WHERE id=?";
            $pdo->prepare($sql)->execute([$nombre, $precio, $categoria, $id_formula, $vol_val, $vol_uni, $id]);
            header("Location: catalogo_productos.php?msj=Actualizado"); exit;
        } 
        
        // ACCIÓN: ELIMINAR (CON TRANSACCIÓN)
        elseif ($_POST['action'] == 'eliminar' && $id > 0) {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM inventario WHERE producto_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM productos WHERE id = ?")->execute([$id]);
            $pdo->commit();
            header("Location: catalogo_productos.php?msj=Eliminado"); exit;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// --- 2. CONSULTA DE DATOS ---
$query = "SELECT p.*, f.nombre_formula,
          (SELECT p2.precio FROM productos p2 WHERE p2.id_formula_maestra = p.id_formula_maestra AND p2.volumen_valor = 1 LIMIT 1) as precio_ref_1l,
          (SELECT SUM(fr.cantidad_por_litro * i.precio_unitario) FROM formulas fr JOIN insumos i ON fr.insumo_id = i.id WHERE fr.id_formula_maestra = p.id_formula_maestra) as costo_l_base,
          (SELECT SUM(fr.cantidad_por_litro * COALESCE((SELECT MIN(ip.precio_presentacion / ip.cantidad_capacidad) FROM insumo_presentaciones ip WHERE ip.id_insumo = fr.insumo_id), i.precio_unitario)) FROM formulas fr JOIN insumos i ON fr.insumo_id = i.id WHERE fr.id_formula_maestra = p.id_formula_maestra) as costo_l_masivo
          FROM productos p
          LEFT JOIN formulas_maestras f ON p.id_formula_maestra = f.id 
          ORDER BY p.categoria ASC, p.nombre ASC";

$productos = $pdo->query($query)->fetchAll();
$categorias = $pdo->query("SELECT nombre FROM categorias ORDER BY nombre ASC")->fetchAll();
$formulas_list = $pdo->query("SELECT id, nombre_formula FROM formulas_maestras ORDER BY nombre_formula ASC")->fetchAll();

// --- 3. EXPORTACIÓN EXCEL ---
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Catalogo_AHD.xls");
    echo "\xEF\xBB\xBF<table border='1'><tr><th>Categoría</th><th>Producto</th><th>Volumen</th><th>Costo M</th><th>Venta</th></tr>";
    foreach ($productos as $p) {
        echo "<tr><td>{$p['categoria']}</td><td>{$p['nombre']}</td><td>{$p['volumen_valor']}</td><td>" . number_format($p['costo_l_masivo']*$p['volumen_valor'],2) . "</td><td>{$p['precio']}</td></tr>";
    }
    echo "</table>"; exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Gestión de Catálogo | AHD Clean</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .badge-vol { background: #f1f5f9; color: #475569; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; }
        .col-money { text-align: right; font-weight: bold; font-family: 'Inter', sans-serif; }
        .rent-tag { padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: bold; min-width: 50px; display: inline-block; text-align: center; }
        .rent-alta { color: #059669; background: #ecfdf5; border: 1px solid #bbf7d0; } 
        .rent-media { color: #d97706; background: #fffbeb; border: 1px solid #fef3c7; } 
        .rent-baja { color: #dc2626; background: #fef2f2; border: 1px solid #fee2e2; } 
        .search-container input { width: 100%; padding: 10px 15px 10px 40px; border: 2px solid #3b82f6; border-radius: 10px; outline: none; }
        .modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:2000; align-items:center; justify-content:center; }
        .modal-content { background:white; padding:25px; border-radius:15px; width:100%; max-width:500px; }
        .form-input { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; box-sizing: border-box; margin-top:5px; }

        @media print {
            .sidebar, .header, .no-print, .acciones-cell, .menu-toggle { display: none !important; }
            .main { margin-left: 0 !important; padding: 0 !important; }
            table { width: 100% !important; border-collapse: collapse !important; font-size: 9pt !important; }
            td, th { border: 1px solid #eee !important; padding: 5px !important; }
            body::before { content: "CATÁLOGO DE PRECIOS - AHD CLEAN"; display: block; text-align: center; font-size: 14pt; font-weight: 800; margin-bottom: 15px; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header no-print" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 25px; gap:15px;">
            <h1><i class="fas fa-boxes"></i> Catálogo Maestro</h1>
            
            <div style="display:flex; gap:10px; align-items:center; flex-wrap: wrap;">
                <a href="?export=excel" style="background:#107c10; color:white; padding:10px 15px; border-radius:8px; text-decoration:none; font-weight:bold; font-size:0.8rem;"><i class="fas fa-file-excel"></i> Excel</a>
                <a href="javascript:void(0)" onclick="window.print()" style="background:#e11d48; color:white; padding:10px 15px; border-radius:8px; text-decoration:none; font-weight:bold; font-size:0.8rem;"><i class="fas fa-print"></i> PDF</a>
                <div style="position:relative; width:200px;">
                    <i class="fas fa-search" style="position:absolute; left:12px; top:12px; color:#3b82f6;"></i>
                    <input type="text" id="busqueda" placeholder="Buscar..." style="width:100%; padding:10px 10px 10px 35px; border:2px solid #3b82f6; border-radius:10px;">
                </div>
                <button onclick="abrirModalNuevo()" style="background:#3b82f6; color:white; border:none; padding:10px 15px; border-radius:8px; font-weight:bold; cursor:pointer;"><i class="fas fa-plus"></i> Nuevo</button>
            </div>
        </div>

        <?php if(isset($error)) echo "<div style='color:red; background:#fee2e2; padding:10px; margin-bottom:20px; border-radius:8px; border:1px solid #f87171;'>$error</div>"; ?>

        <div class="table-container">
            <table>
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; font-size: 0.8rem;">
                        <th style="padding:12px; text-align:left;">Producto</th>
                        <th class="no-print" style="text-align:right;">Costo (B)</th>
                        <th class="no-print" style="text-align:right;">Costo (M)</th>
                        <th style="text-align:right;">P. Venta</th>
                        <th class="no-print" style="text-align:right;">Utilidad (M)</th>
                        <th class="no-print" style="text-align:center;">Margen</th>
                        <th class="no-print" style="text-align:center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $p): 
                        $c_base = ($p['costo_l_base'] ?? 0) * $p['volumen_valor'];
                        $c_masivo = ($p['costo_l_masivo'] ?? 0) * $p['volumen_valor'];
                        $util_m = $p['precio'] - $c_masivo;
                        $marg_m = ($p['precio'] > 0) ? ($util_m / $p['precio']) * 100 : 0;
                        $clase_r = ($marg_m >= 45) ? 'rent-alta' : (($marg_m >= 25) ? 'rent-media' : 'rent-baja');
                        $search = strtolower(htmlspecialchars($p['nombre'] . ' ' . $p['categoria']));
                    ?>
                    <tr class="fila-producto" data-search="<?php echo $search; ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($p['nombre']); ?></strong>
                            <span class="badge-vol"><?php echo (float)$p['volumen_valor'].$p['volumen_unidad']; ?></span>
                            <br class="no-print"><small class="no-print" style="color:#94a3b8;"><?php echo htmlspecialchars($p['categoria']); ?></small>
                        </td>
                        <td class="col-money no-print" style="color:#94a3b8; font-weight:400; font-size:0.85rem;">$<?php echo number_format($c_base, 2); ?></td>
                        <td class="col-money no-print" style="color:#1e293b; font-size:0.85rem;">$<?php echo number_format($c_masivo, 2); ?></td>
                        <td class="col-money" style="color:#3b82f6;">$<?php echo number_format($p['precio'], 2); ?></td>
                        <td class="col-money no-print" style="color:#059669;">$<?php echo number_format($util_m, 2); ?></td>
                        <td class="no-print" style="text-align:center;"><span class="rent-tag <?php echo $clase_r; ?>"><?php echo number_format($marg_m, 1); ?>%</span></td>
                        <td class="no-print acciones-cell" style="text-align:center;">
                            <button onclick='abrirEditar(<?php echo json_encode($p); ?>)' title="Editar" style="color:#3b82f6; border:none; background:none; cursor:pointer; font-size:1.1rem;"><i class="fas fa-edit"></i></button>
                            <button onclick="confirmarEliminar(<?php echo $p['id']; ?>)" title="Eliminar" style="color:#ef4444; border:none; background:none; cursor:pointer; margin-left:10px; font-size:1.1rem;"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="modalProd" class="modal-overlay">
        <div class="modal-content">
            <h2 id="modal_titulo" style="margin-top:0;"><i class="fas fa-edit"></i> Editar Producto</h2>
            <form method="POST">
                <input type="hidden" name="action" id="m_action" value="editar">
                <input type="hidden" name="id" id="m_id">
                <input type="hidden" id="precio_ref_1l">

                <div style="margin-bottom:15px;"><label style="font-weight:600;">Nombre Comercial</label><input type="text" name="nombre" id="m_nombre" class="form-input" required></div>
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                    <div><label style="font-weight:600;">Volumen</label><input type="number" step="0.01" name="volumen_valor" id="m_vol_val" class="form-input" oninput="recalc()" required></div>
                    <div><label style="font-weight:600;">Unidad</label><select name="volumen_unidad" id="m_vol_uni" class="form-input"><option value="L">Litros (L)</option><option value="ml">Mililitros (ml)</option></select></div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px; background:#fffaf0; padding:12px; border-radius:10px; border:1px solid #fbd38d;">
                    <div><label style="color:#9c4221; font-weight:600;">Desc. Vol %</label><input type="number" id="m_desc" class="form-input" style="background:white; color:#9c4221; font-weight:bold;" oninput="recalc()"></div>
                    <div><label style="font-weight:600;">Precio Final</label><input type="number" step="0.01" name="precio" id="m_precio" class="form-input" style="font-weight:bold;" required></div>
                </div>

                <div style="margin-bottom:15px;"><label style="font-weight:600;">Categoría</label><select name="categoria" id="m_cat" class="form-input"><?php foreach($categorias as $c): ?><option value="<?php echo $c['nombre']; ?>"><?php echo $c['nombre']; ?></option><?php endforeach; ?></select></div>
                
                <div style="margin-bottom:20px;"><label style="font-weight:600;">Fórmula Maestra</label><select name="id_formula_maestra" id="m_form" class="form-input"><option value="">Ninguna</option><?php foreach($formulas_list as $f): ?><option value="<?php echo $f['id']; ?>"><?php echo $f['nombre_formula']; ?></option><?php endforeach; ?></select></div>

                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" onclick="cerrarModal()" style="padding:10px 15px; border-radius:8px; border:none; cursor:pointer;">Cancelar</button>
                    <button type="submit" id="btn_submit" style="padding:10px 20px; background:#3b82f6; color:white; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const inputB = document.getElementById('busqueda');
        function filtrar(v) { document.querySelectorAll('.fila-producto').forEach(f => { f.style.display = f.getAttribute('data-search').includes(v.toLowerCase()) ? "" : "none"; }); }
        inputB.addEventListener('keyup', () => { filtrar(inputB.value); localStorage.setItem('ahd_cat_f', inputB.value); });
        window.onload = () => { const g = localStorage.getItem('ahd_cat_f'); if(g){ inputB.value = g; filtrar(g); } };

        const modal = document.getElementById('modalProd');

        // FUNCIÓN: NUEVO PRODUCTO
        function abrirModalNuevo() {
            document.getElementById('modal_titulo').innerHTML = '<i class="fas fa-plus"></i> Nuevo Producto';
            document.getElementById('m_action').value = 'nuevo';
            document.getElementById('m_id').value = '';
            document.getElementById('m_nombre').value = '';
            document.getElementById('m_precio').value = '';
            document.getElementById('m_vol_val').value = '1';
            document.getElementById('m_desc').value = '';
            document.getElementById('precio_ref_1l').value = '0';
            document.getElementById('btn_submit').innerText = 'Crear Producto';
            modal.style.display = 'flex';
        }

        // FUNCIÓN: EDITAR PRODUCTO
        function abrirEditar(p) {
            document.getElementById('modal_titulo').innerHTML = '<i class="fas fa-edit"></i> Editar Producto';
            document.getElementById('m_action').value = 'editar';
            document.getElementById('btn_submit').innerText = 'Guardar Cambios';
            document.getElementById('m_id').value = p.id; 
            document.getElementById('m_nombre').value = p.nombre; 
            document.getElementById('m_precio').value = p.precio;
            document.getElementById('m_vol_val').value = p.volumen_valor; 
            document.getElementById('m_vol_uni').value = p.volumen_unidad;
            document.getElementById('m_cat').value = p.categoria; 
            document.getElementById('m_form').value = p.id_formula_maestra || "";
            
            const ref = parseFloat(p.precio_ref_1l) || 0; 
            document.getElementById('precio_ref_1l').value = ref;
            const teorico = ref * parseFloat(p.volumen_valor);
            document.getElementById('m_desc').value = (teorico > 0 && p.volumen_valor > 1) ? (((teorico - p.precio) / teorico) * 100).toFixed(1) : "";
            modal.style.display = 'flex';
        }

        function recalc() {
            const ref = parseFloat(document.getElementById('precio_ref_1l').value) || 0;
            const vol = parseFloat(document.getElementById('m_vol_val').value) || 0;
            const desc = parseFloat(document.getElementById('m_desc').value) || 0;
            if(ref > 0 && vol > 0) document.getElementById('m_precio').value = ((ref * vol) * (1 - (desc / 100))).toFixed(2);
        }

        function confirmarEliminar(id) {
            if(confirm('¿Seguro que deseas eliminar este producto permanentemente?')) {
                const f = document.createElement('form');
                f.method = 'POST';
                f.innerHTML = `<input type="hidden" name="action" value="eliminar"><input type="hidden" name="id" value="${id}">`;
                document.body.appendChild(f); f.submit();
            }
        }

        function cerrarModal() { modal.style.display = 'none'; }
        window.onclick = (e) => { if(e.target == modal) cerrarModal(); }
    </script>
</body>
</html>