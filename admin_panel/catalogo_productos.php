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
        if ($_POST['action'] == 'nuevo') {
            $pdo->beginTransaction();
            $sql = "INSERT INTO productos (nombre, precio, categoria, id_formula_maestra, volumen_valor, volumen_unidad) VALUES (?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$nombre, $precio, $categoria, $id_formula, $vol_val, $vol_uni]);
            $nuevo_id = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO inventario (producto_id, stock) VALUES (?, 0)")->execute([$nuevo_id]);
            $pdo->commit();
            header("Location: catalogo_productos.php?msj=Creado"); exit;
        } 
        elseif ($_POST['action'] == 'editar') {
            $sql = "UPDATE productos SET nombre=?, precio=?, categoria=?, id_formula_maestra=?, volumen_valor=?, volumen_unidad=? WHERE id=?";
            $pdo->prepare($sql)->execute([$nombre, $precio, $categoria, $id_formula, $vol_val, $vol_uni, $id]);
            header("Location: catalogo_productos.php?msj=Actualizado"); exit;
        } 
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

// --- 3. EXPORTACIÓN EXCEL (FIX PARA IPHONE - CSV UTF-8) ---
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Catalogo_AHD_Clean.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); 
    fputcsv($output, ['Categoría', 'Producto', 'Volumen', 'Costo Masivo', 'Precio Venta', 'Utilidad', 'Margen %']);
    foreach ($productos as $p) {
        $c_masivo = ($p['costo_l_masivo'] ?? 0) * $p['volumen_valor'];
        $util = $p['precio'] - $c_masivo;
        $margen = ($p['precio'] > 0) ? ($util / $p['precio']) * 100 : 0;
        fputcsv($output, [$p['categoria'], $p['nombre'], $p['volumen_valor'] . $p['volumen_unidad'], number_format($c_masivo, 2), number_format($p['precio'], 2), number_format($util, 2), number_format($margen, 1) . '%']);
    }
    fclose($output); exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Catálogo Maestro | AHD Clean</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        :root { --accent: #3b82f6; --dark: #1e293b; --success: #059669; }
        body { background: #f8fafc; margin: 0; font-family: 'Segoe UI', sans-serif; }

        /* Estilos UI e Interfaz */
        .header-mobile { display: none; position: fixed; top: 0; left: 0; right: 0; height: 60px; background: var(--dark); color: white; align-items: center; justify-content: space-between; padding: 0 15px; z-index: 2000; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
        .main { padding: 25px; transition: 0.3s; }
        .badge-vol { background: #f1f5f9; color: #475569; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; }
        
        /* Rentabilidad Tags */
        .rent-tag { padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: bold; min-width: 50px; display: inline-block; text-align: center; }
        .rent-alta { color: #059669; background: #ecfdf5; border: 1px solid #bbf7d0; } 
        .rent-media { color: #d97706; background: #fffbeb; border: 1px solid #fef3c7; } 
        .rent-baja { color: #dc2626; background: #fef2f2; border: 1px solid #fee2e2; } 

        /* Tabla Escritorio */
        .desktop-table { background: white; border-radius: 15px; overflow: hidden; border: 1px solid #e2e8f0; }
        .desktop-table table { width: 100%; border-collapse: collapse; }
        .desktop-table th { background: #f8fafc; padding: 12px; text-align: left; color: #64748b; font-size: 0.8rem; text-transform: uppercase; }
        .desktop-table td { padding: 12px; border-top: 1px solid #f1f5f9; }

        /* Vista Mobile */
        .mobile-cards { display: none; flex-direction: column; gap: 12px; }
        .prod-card { background: white; padding: 20px; border-radius: 15px; border: 1px solid #e2e8f0; }
        .prod-card h3 { margin: 0; font-size: 1.1rem; color: var(--dark); }

        @media (max-width: 992px) {
            .header-mobile { display: flex; }
            .main { margin-left: 0 !important; padding: 80px 15px 120px 15px !important; }
            .desktop-table { display: none; }
            .mobile-cards { display: flex; }
            .sidebar { position: fixed; left: -260px; z-index: 3000; }
            .sidebar.active { left: 0; }
            .hide-mobile { display: none !important; }
        }

        /* --- OPTIMIZACIÓN PDF (PRINT) --- */
        @media print {
            @page { size: letter; margin: 1cm; }
            .sidebar, .header-mobile, .no-print, .acciones-cell, .hide-mobile, .btn, .search-container, #overlay { display: none !important; }
            .main { margin: 0 !important; padding: 0 !important; }
            .print-header { display: block !important; text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
            .desktop-table { display: block !important; border: none !important; }
            table { width: 100% !important; border-collapse: collapse !important; }
            th { border-bottom: 2px solid #333 !important; font-size: 9pt !important; background: #eee !important; color: #000 !important; }
            td { border-bottom: 1px solid #ddd !important; padding: 6px !important; font-size: 9pt !important; }
            tr { page-break-inside: avoid; }
        }
        .print-header { display: none; }

        /* Modales */
        .modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:4000; align-items:center; justify-content:center; backdrop-filter: blur(4px); }
        .modal-content { background:white; padding:25px; border-radius:20px; width:90%; max-width:500px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .form-input { width: 100%; padding: 12px; border: 1px solid #cbd5e0; border-radius: 10px; box-sizing: border-box; font-size: 1rem; margin-top:5px; }
    </style>
</head>
<body>
    <div class="overlay" id="overlay" onclick="toggleMenu()"></div>

    <div class="print-header">
        <h2 style="margin:0;">AHD CLEAN - CATÁLOGO OFICIAL</h2>
        <p style="margin:5px 0;">Lista de Precios | Emisión: <?php echo date('d/m/Y'); ?></p>
    </div>

    <div class="header-mobile no-print">
        <button onclick="toggleMenu()" style="background:none; border:none; color:white; font-size:1.5rem;"><i class="fas fa-bars"></i></button>
        <span style="font-weight: 900;">AHD CATÁLOGO</span>
        <div style="display:flex; gap:15px; align-items:center;">
            <a href="?export=excel"><i class="fas fa-file-excel" style="color:#22c55e; font-size:1.3rem;"></i></a>
            <a href="javascript:void(0)" onclick="window.print()"><i class="fas fa-print" style="color:white; font-size:1.3rem;"></i></a>
            <button onclick="abrirModalNuevo()" style="background:none; border:none; color:white; font-size:1.3rem;"><i class="fas fa-plus-circle"></i></button>
        </div>
    </div>

    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header no-print hide-mobile" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 25px;">
            <h1><i class="fas fa-boxes"></i> Catálogo Maestro</h1>
            <div style="display:flex; gap:10px;">
                <a href="?export=excel" style="background:var(--success); color:white; padding:12px 18px; border-radius:10px; text-decoration:none; font-weight:bold;"><i class="fas fa-file-excel"></i> Excel</a>
                <button onclick="window.print()" style="background:#e11d48; color:white; border:none; padding:12px 18px; border-radius:10px; font-weight:bold; cursor:pointer;"><i class="fas fa-print"></i> PDF</button>
                <button onclick="abrirModalNuevo()" style="background:var(--accent); color:white; border:none; padding:12px 18px; border-radius:10px; font-weight:bold; cursor:pointer;"><i class="fas fa-plus"></i> Nuevo</button>
            </div>
        </div>

        <div class="search-container no-print" style="position:relative; margin-bottom:20px;">
            <i class="fas fa-search" style="position:absolute; left:15px; top:15px; color:var(--accent);"></i>
            <input type="text" id="busqueda" placeholder="Buscar por nombre o categoría..." style="width:100%; padding:15px 15px 15px 45px; border:2px solid #e2e8f0; border-radius:12px; outline:none;">
        </div>

        <div class="desktop-table">
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th class="no-print">Costo (M)</th>
                        <th>Precio Venta</th>
                        <th class="no-print">Utilidad</th>
                        <th class="no-print" style="text-align:center;">Margen</th>
                        <th class="no-print" style="text-align:center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $p): 
                        $c_masivo = ($p['costo_l_masivo'] ?? 0) * $p['volumen_valor'];
                        $util_m = $p['precio'] - $c_masivo;
                        $marg_m = ($p['precio'] > 0) ? ($util_m / $p['precio']) * 100 : 0;
                        $clase_r = ($marg_m >= 45) ? 'rent-alta' : (($marg_m >= 25) ? 'rent-media' : 'rent-baja');
                    ?>
                    <tr class="fila-producto" data-search="<?php echo strtolower($p['nombre'] . ' ' . $p['categoria']); ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($p['nombre']); ?></strong>
                            <span class="badge-vol"><?php echo (float)$p['volumen_valor'].$p['volumen_unidad']; ?></span><br>
                            <small class="no-print" style="color:#94a3b8;"><?php echo htmlspecialchars($p['categoria']); ?></small>
                        </td>
                        <td class="no-print" style="color:#64748b;">$<?php echo number_format($c_masivo, 2); ?></td>
                        <td style="color:var(--accent); font-weight:bold;">$<?php echo number_format($p['precio'], 2); ?></td>
                        <td class="no-print" style="color:var(--success); font-weight:bold;">$<?php echo number_format($util_m, 2); ?></td>
                        <td class="no-print" style="text-align:center;"><span class="rent-tag <?php echo $clase_r; ?>"><?php echo number_format($marg_m, 1); ?>%</span></td>
                        <td class="no-print acciones-cell" style="text-align:center;">
                            <button onclick='abrirEditar(<?php echo json_encode($p); ?>)' style="color:var(--accent); border:none; background:none; cursor:pointer;"><i class="fas fa-edit"></i></button>
                            <button onclick="confirmarEliminar(<?php echo $p['id']; ?>)" style="color:#ef4444; border:none; background:none; cursor:pointer; margin-left:10px;"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mobile-cards no-print">
            <?php foreach ($productos as $p): 
                 $c_masivo = ($p['costo_l_masivo'] ?? 0) * $p['volumen_valor'];
                 $util_m = $p['precio'] - $c_masivo;
                 $marg_m = ($p['precio'] > 0) ? ($util_m / $p['precio']) * 100 : 0;
                 $clase_r = ($marg_m >= 45) ? 'rent-alta' : (($marg_m >= 25) ? 'rent-media' : 'rent-baja');
            ?>
            <div class="prod-card fila-producto" data-search="<?php echo strtolower($p['nombre'] . ' ' . $p['categoria']); ?>">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <h3><?php echo htmlspecialchars($p['nombre']); ?></h3>
                        <span class="badge-vol"><?php echo (float)$p['volumen_valor'].$p['volumen_unidad']; ?></span>
                        <small style="display:block; color:#64748b; margin-top:5px;"><?php echo htmlspecialchars($p['categoria']); ?></small>
                    </div>
                    <span class="rent-tag <?php echo $clase_r; ?>"><?php echo number_format($marg_m, 1); ?>%</span>
                </div>
                <div style="font-size:1.3rem; font-weight:900; color:var(--accent); margin:10px 0;">$<?php echo number_format($p['precio'], 2); ?></div>
                <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #f1f5f9; padding-top:10px;">
                    <small style="color:var(--success); font-weight:bold;">Utilidad: $<?php echo number_format($util_m, 2); ?></small>
                    <div>
                        <button onclick='abrirEditar(<?php echo json_encode($p); ?>)' style="color:var(--accent); border:none; background:none; font-size:1.2rem;"><i class="fas fa-edit"></i></button>
                        <button onclick="confirmarEliminar(<?php echo $p['id']; ?>)" style="color:#ef4444; border:none; background:none; font-size:1.2rem; margin-left:15px;"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="modalProd" class="modal-overlay">
        <div class="modal-content">
            <h2 id="modal_titulo" style="margin-top:0;">Nuevo Producto</h2>
            <form method="POST">
                <input type="hidden" name="action" id="m_action" value="editar">
                <input type="hidden" name="id" id="m_id">
                <input type="hidden" id="precio_ref_1l">

                <div style="margin-bottom:12px;">
                    <label style="font-weight:bold; font-size:0.8rem;">Nombre Comercial</label>
                    <input type="text" name="nombre" id="m_nombre" class="form-input" required placeholder="Ej. Multiusos Lavanda">
                </div>
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div><label style="font-weight:bold; font-size:0.8rem;">Volumen</label><input type="number" step="0.01" name="volumen_valor" id="m_vol_val" class="form-input" oninput="recalc()" inputmode="decimal"></div>
                    <div><label style="font-weight:bold; font-size:0.8rem;">Unidad</label><select name="volumen_unidad" id="m_vol_uni" class="form-input"><option value="L">Litros (L)</option><option value="ml">Mililitros (ml)</option></select></div>
                </div>

                <div style="background:#f0f9ff; padding:12px; border-radius:12px; margin-bottom:12px; border:1px solid #bae6fd;">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div><label style="color:#0369a1; font-weight:bold; font-size:0.8rem;">Desc. %</label><input type="number" id="m_desc" class="form-input" oninput="recalc()" placeholder="Opcional" inputmode="decimal"></div>
                        <div><label style="font-weight:bold; font-size:0.8rem;">Precio Final ($)</label><input type="number" step="0.01" name="precio" id="m_precio" class="form-input" required style="font-weight:900; color:var(--accent);" inputmode="decimal"></div>
                    </div>
                </div>

                <div style="margin-bottom:12px;"><label style="font-weight:bold; font-size:0.8rem;">Categoría</label><select name="categoria" id="m_cat" class="form-input"><?php foreach($categorias as $c): ?><option value="<?php echo $c['nombre']; ?>"><?php echo $c['nombre']; ?></option><?php endforeach; ?></select></div>
                <div style="margin-bottom:15px;"><label style="font-weight:bold; font-size:0.8rem;">Fórmula Maestra</label><select name="id_formula_maestra" id="m_form" class="form-input"><option value="">Ninguna</option><?php foreach($formulas_list as $f): ?><option value="<?php echo $f['id']; ?>"><?php echo $f['nombre_formula']; ?></option><?php endforeach; ?></select></div>

                <button type="submit" id="btn_submit" style="width:100%; padding:15px; background:var(--accent); color:white; border:none; border-radius:12px; font-weight:900; font-size:1.1rem; cursor:pointer;">GUARDAR PRODUCTO</button>
                <button type="button" onclick="cerrarModal()" style="width:100%; margin-top:10px; background:none; border:none; color:#94a3b8; font-weight:bold; cursor:pointer;">Cancelar</button>
            </form>
        </div>
    </div>

    <script>
        const inputB = document.getElementById('busqueda');
        inputB.addEventListener('keyup', () => { 
            const v = inputB.value.toLowerCase();
            document.querySelectorAll('.fila-producto').forEach(f => {
                f.style.display = f.getAttribute('data-search').includes(v) ? "" : "none";
            });
        });

        function abrirModalNuevo() {
            document.getElementById('modal_titulo').innerText = 'Nuevo Producto';
            document.getElementById('m_action').value = 'nuevo';
            document.getElementById('m_id').value = '';
            document.getElementById('m_nombre').value = '';
            document.getElementById('m_precio').value = '';
            document.getElementById('m_vol_val').value = '1';
            document.getElementById('m_desc').value = '';
            document.getElementById('precio_ref_1l').value = '0';
            document.getElementById('modalProd').style.display = 'flex';
        }

        function abrirEditar(p) {
            document.getElementById('modal_titulo').innerText = 'Editar Producto';
            document.getElementById('m_action').value = 'editar';
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
            document.getElementById('modalProd').style.display = 'flex';
        }

        function recalc() {
            const ref = parseFloat(document.getElementById('precio_ref_1l').value) || 0;
            const vol = parseFloat(document.getElementById('m_vol_val').value) || 0;
            const desc = parseFloat(document.getElementById('m_desc').value) || 0;
            if(ref > 0 && vol > 0) document.getElementById('m_precio').value = ((ref * vol) * (1 - (desc / 100))).toFixed(2);
        }

        function confirmarEliminar(id) {
            if(confirm('¿Seguro que deseas eliminar este producto?')) {
                const f = document.createElement('form');
                f.method = 'POST';
                f.innerHTML = `<input type="hidden" name="action" value="eliminar"><input type="hidden" name="id" value="${id}">`;
                document.body.appendChild(f); f.submit();
            }
        }

        function toggleMenu() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('active');
        }

        function cerrarModal() { document.getElementById('modalProd').style.display = 'none'; }
        window.onclick = (e) => { if(e.target == document.getElementById('modalProd')) cerrarModal(); }
    </script>
</body>
</html>