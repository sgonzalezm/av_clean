<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$error = "";

// --- 1. PROCESAMIENTO DE DATOS (NUEVO / EDITAR / ELIMINAR) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $id = $_POST['id'] ?? null;
    $nombre = $_POST['nombre'] ?? '';
    $precio = $_POST['precio'] ?? 0;
    $categoria = $_POST['categoria'] ?? ''; 
    $tipo = $_POST['tipo_producto'] ?? 'producto'; // Captura el nuevo tipo
    $vol_val = $_POST['volumen_valor'] ?? 1;
    $vol_uni = $_POST['volumen_unidad'] ?? 'L';

    // Lógica para asignar valores según el tipo
    if ($tipo === 'servicio') {
        $id_formula = null;
        $id_envase = null;
        $id_etiqueta = null;
    } else {
        $id_formula = !empty($_POST['id_formula_maestra']) ? $_POST['id_formula_maestra'] : null;
        $id_envase = !empty($_POST['id_insumo_envase']) ? $_POST['id_insumo_envase'] : null;
        $id_etiqueta = !empty($_POST['id_insumo_etiqueta']) ? $_POST['id_insumo_etiqueta'] : null;
    }

    // --- LÓGICA DE SUBIDA DE IMAGEN (SE MANTIENE IGUAL) ---
    $imagen_db = null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['imagen']['tmp_name'];
        $fileName = $_FILES['imagen']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $nuevoNombreImagen = md5(time() . $fileName) . '.' . $fileExtension;
        $uploadFileDir = '../img/';
        if (!is_dir($uploadFileDir)) { mkdir($uploadFileDir, 0777, true); }
        $dest_path = $uploadFileDir . $nuevoNombreImagen;
        if (move_uploaded_file($fileTmpPath, $dest_path)) {
            $imagen_db = '../img/' . $nuevoNombreImagen;
        }
    }

    try {
        if ($_POST['action'] == 'nuevo') {
            $pdo->beginTransaction();
            $sql = "INSERT INTO productos (nombre, precio, categoria, id_formula_maestra, volumen_valor, volumen_unidad, id_insumo_envase, id_insumo_etiqueta, imagen_url, tipo_producto) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$nombre, $precio, $categoria, $id_formula, $vol_val, $vol_uni, $id_envase, $id_etiqueta, $imagen_db, $tipo]);
            $nuevo_id = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO inventario (producto_id, stock) VALUES (?, 0)")->execute([$nuevo_id]);
            $pdo->commit();
            header("Location: catalogo_productos.php?msj=Creado"); exit;
        } 
        elseif ($_POST['action'] == 'editar') {
            $params = [$nombre, $precio, $categoria, $id_formula, $vol_val, $vol_uni, $id_envase, $id_etiqueta];
            $sql_img = ($imagen_db !== null) ? ", imagen_url=?" : "";
            if ($imagen_db !== null) $params[] = $imagen_db;
            $params[] = $tipo; // Agregamos tipo al update
            $params[] = $id;   // ID al final
            
            $sql = "UPDATE productos SET nombre=?, precio=?, categoria=?, id_formula_maestra=?, volumen_valor=?, volumen_unidad=?, id_insumo_envase=?, id_insumo_etiqueta=? $sql_img, tipo_producto=? WHERE id=?";
            $pdo->prepare($sql)->execute($params);
            header("Location: catalogo_productos.php?msj=Actualizado"); exit;
        } 
        // ... (el bloque eliminar queda igual)
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// --- 2. CONSULTA DE DATOS OPTIMIZADA ---
$query = "SELECT p.*, f.nombre_formula,
          (SELECT p2.precio FROM productos p2 WHERE p2.id_formula_maestra = p.id_formula_maestra AND p2.volumen_valor = 1 LIMIT 1) as precio_ref_1l,
          (SELECT precio_unitario FROM insumos WHERE id = p.id_insumo_envase) as costo_envase_unit,
          (SELECT precio_unitario FROM insumos WHERE id = p.id_insumo_etiqueta) as costo_etiqueta_unit,
          (SELECT SUM(fr.cantidad_por_litro * COALESCE((SELECT MIN(ip.precio_presentacion / ip.cantidad_capacidad) FROM insumo_presentaciones ip WHERE ip.id_insumo = fr.insumo_id), i.precio_unitario)) 
           FROM formulas fr JOIN insumos i ON fr.insumo_id = i.id WHERE fr.id_formula_maestra = p.id_formula_maestra) as costo_l_masivo
          FROM productos p
          LEFT JOIN formulas_maestras f ON p.id_formula_maestra = f.id 
          ORDER BY p.categoria ASC, p.nombre ASC";

$productos_raw = $pdo->query($query)->fetchAll();

// --- LOGICA DE DETECCION DE COLUMNAS / PRESENTACIONES PRESENTES ---
$columnas_presentaciones = []; // Almacenará valores únicos detectados en la BD de forma ordenada (Ej: 0.5, 1, 4, 5, 10, 20)

// --- LÓGICA DE AGRUPACIÓN ---
$productos_agrupados = [];
foreach ($productos_raw as $p) {
    // Limpiamos los sufijos del nombre para agruparlos bajo el mismo elemento base
    $nombre_base = trim(preg_replace('/\s*\([\d\.]+m?L\)$/i', '', $p['nombre']));
    $key = $p['categoria'] . "_" . $nombre_base;
    
    if (!isset($productos_agrupados[$key])) {
        $productos_agrupados[$key] = ['nombre_base' => $nombre_base, 'categoria' => $p['categoria'], 'presentaciones' => []];
    }
    
    // Homologar key de volumen a litros (float)
    $vol_key = ($p['volumen_unidad'] == 'ml') ? ($p['volumen_valor'] / 1000) : (float)$p['volumen_valor'];
    $productos_agrupados[$key]['presentaciones'][(string)$vol_key] = $p;
    
    // Registramos la presentación si no ha sido guardada para armar los headers dinámicos
    if (!in_array((string)$vol_key, $columnas_presentaciones)) {
        $columnas_presentaciones[] = (string)$vol_key;
    }
}
// Ordenamos las columnas de menor a mayor capacidad
sort($columnas_presentaciones, SORT_NUMERIC);

$categorias = $pdo->query("SELECT nombre FROM categorias ORDER BY nombre ASC")->fetchAll();
$formulas_list = $pdo->query("SELECT id, nombre_formula FROM formulas_maestras ORDER BY nombre_formula ASC")->fetchAll();
$insumos_empaque = $pdo->query("SELECT id, nombre, precio_unitario FROM insumos ORDER BY nombre ASC")->fetchAll();

// --- 3. EXPORTACIÓN EXCEL ---
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Catalogo_AHD_Clean.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); 
    fputcsv($output, ['Categoría', 'Producto', 'Volumen', 'Costo Total', 'Precio Venta', 'Utilidad', 'Margen %']);
    foreach ($productos_raw as $p) {
        $c_liq = ($p['costo_l_masivo'] ?? 0) * $p['volumen_valor'];
        $c_emp = ($p['costo_envase_unit'] ?? 0) + ($p['costo_etiqueta_unit'] ?? 0);
        $total_c = $c_liq + $c_emp;
        $util = $p['precio'] - $total_c;
        $margen = ($p['precio'] > 0) ? ($util / $p['precio']) * 100 : 0;
        fputcsv($output, [$p['categoria'], $p['nombre'], $p['volumen_valor'] . $p['volumen_unidad'], number_format($total_c, 2), number_format($p['precio'], 2), number_format($util, 2), number_format($margen, 1) . '%']);
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
        .main { padding: 25px; transition: 0.3s; }
        .rent-tag { padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: bold; display: inline-block; margin-top: 3px; }
        .rent-alta { color: #059669; background: #ecfdf5; border: 1px solid #bbf7d0; } 
        .rent-media { color: #d97706; background: #fffbeb; border: 1px solid #fef3c7; } 
        .rent-baja { color: #dc2626; background: #fef2f2; border: 1px solid #fee2e2; } 
        
        .desktop-table { background: white; border-radius: 15px; overflow: hidden; border: 1px solid #e2e8f0; }
        .desktop-table table { width: 100%; border-collapse: collapse; }
        .desktop-table th { background: #f8fafc; padding: 10px; text-align: center; color: #64748b; font-size: 0.7rem; text-transform: uppercase; }
        .desktop-table td { padding: 8px 10px; border-top: 1px solid #f1f5f9; text-align: center; vertical-align: middle; }
        .precio-v { display: block; font-weight: 800; color: var(--accent); font-size: 0.9rem; }
        .costo-v { display: block; font-size: 0.58rem; color: #94a3b8; margin-top: 1px; }

        @media (max-width: 992px) {
            .header-mobile { display: flex; }
            .main { margin-left: 0 !important; padding: 80px 10px !important; }
            .desktop-table { display: block; overflow-x: auto; }
        }

        @media print {
            @page { size: letter; margin: 0.8cm; }
            .sidebar, .header-mobile, .no-print, .acciones-cell, #overlay, .search-container { display: none !important; }
            .main { margin: 0 !important; padding: 0 !important; }
            .print-header { display: block !important; text-align: center; margin-bottom: 10px; border-bottom: 2px solid #000; }
            .desktop-table { border: 0.5px solid #ccc !important; border-radius: 0 !important; }
            th { font-size: 7pt !important; padding: 4px !important; border: 0.5px solid #ccc !important; background: #f2f2f2 !important; }
            td { font-size: 7pt !important; padding: 3px !important; border-bottom: 0.5px solid #eee !important; }
            .rent-tag, .costo-v, small { display: none !important; }
            .precio-v { color: black !important; font-weight: bold; }
        }

        .print-header { display: none; }
        .modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:4000; align-items:center; justify-content:center; backdrop-filter: blur(4px); }
        .modal-content { background:white; padding:25px; border-radius:20px; width:90%; max-width:550px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); max-height: 90vh; overflow-y: auto; }
        .form-input { width: 100%; padding: 12px; border: 1px solid #cbd5e0; border-radius: 10px; box-sizing: border-box; font-size: 1rem; margin-top:5px; }
    </style>
</head>
<body>
    <div class="overlay" id="overlay" onclick="toggleMenu()"></div>
    <div class="print-header">
        <h2 style="margin:0;">AHD CLEAN - LISTA DE PRECIOS</h2>
        <p style="margin:5px 0;">Vigencia: <?php echo date('d/m/Y'); ?></p>
    </div>

    <div class="header-mobile no-print">
        <button onclick="toggleMenu()" style="background:none; border:none; color:white; font-size:1.5rem;"><i class="fas fa-bars"></i></button>
        <span style="font-weight: 900;">AHD CATÁLOGO</span>
        <div style="display:flex; gap:15px;">
            <a href="?export=excel"><i class="fas fa-file-excel" style="color:#22c55e;"></i></a>
            <a href="javascript:void(0)" onclick="window.print()"><i class="fas fa-print" style="color:white;"></i></a>
            <button onclick="abrirModalNuevo()" style="background:none; border:none; color:white;"><i class="fas fa-plus-circle"></i></button>
        </div>
    </div>

    <?php include 'sidebar.php'; ?>

    <div class="main">
        <?php if(!empty($error)): ?>
            <div class="no-print" style="background:#fef2f2; border:1px solid #fca5a5; color:#b91c1c; padding:15px; border-radius:10px; margin-bottom:15px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="header no-print hide-mobile" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
            <h1><i class="fas fa-table"></i> Catálogo Maestro</h1>
            <div style="display:flex; gap:10px;">
                <a href="?export=excel" style="background:var(--success); color:white; padding:10px 15px; border-radius:8px; text-decoration:none; font-weight:bold; font-size:0.9rem;"><i class="fas fa-file-excel"></i> Excel</a>
                <button onclick="window.print()" style="background:#e11d48; color:white; border:none; padding:10px 15px; border-radius:8px; font-weight:bold; cursor:pointer; font-size:0.9rem;"><i class="fas fa-print"></i> PDF Clientes</button>
                <button onclick="abrirModalNuevo()" style="background:var(--accent); color:white; border:none; padding:10px 15px; border-radius:8px; font-weight:bold; cursor:pointer; font-size:0.9rem;"><i class="fas fa-plus"></i> Nuevo</button>
            </div>
        </div>

        <div class="search-container no-print" style="position:relative; margin-bottom:20px;">
            <input type="text" id="busqueda" placeholder="Buscar producto..." style="width:100%; padding:12px 12px 12px 40px; border:2px solid #e2e8f0; border-radius:10px; outline:none;">
            <i class="fas fa-search" style="position:absolute; left:12px; top:15px; color:var(--accent);"></i>
        </div>

        <div class="desktop-table">
            <table>
                <thead>
                    <tr>
                        <th style="text-align:left; width:220px; padding-left:15px;">Producto</th>
                        <?php foreach($columnas_presentaciones as $col): ?>
                            <th><?php echo ($col < 1) ? ($col * 1000).'ml' : $col.'L'; ?></th>
                        <?php endforeach; ?>
                        <th class="no-print">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos_agrupados as $ag): ?>
                    <tr class="fila-producto" data-search="<?php echo strtolower($ag['nombre_base'] . ' ' . $ag['categoria']); ?>">
                        <td style="text-align:left; padding-left:15px;">
                            <strong style="font-size:0.85rem;"><?php echo htmlspecialchars($ag['nombre_base']); ?></strong><br>
                            <small style="color:#94a3b8; font-size:0.65rem;"><?php echo htmlspecialchars($ag['categoria']); ?></small>
                        </td>
                        <?php foreach($columnas_presentaciones as $v): 
                            $p = $ag['presentaciones'][$v] ?? null;
                        ?>
                        <td>
                            <?php if($p): 
                                $c_liq = ($p['costo_l_masivo'] ?? 0) * $p['volumen_valor'];
                                $c_emp = ($p['costo_envase_unit'] ?? 0) + ($p['costo_etiqueta_unit'] ?? 0);
                                $total_costo = $c_liq + $c_emp;
                                $marg_m = ($p['precio'] > 0) ? (($p['precio'] - $total_costo) / $p['precio']) * 100 : 0;
                                $clase_r = ($marg_m >= 45) ? 'rent-alta' : (($marg_m >= 25) ? 'rent-media' : 'rent-baja');
                            ?>
                                <span class="precio-v">$<?php echo number_format($p['precio'], 2); ?></span>
                                <span class="rent-tag <?php echo $clase_r; ?>"><?php echo number_format($marg_m, 1); ?>%</span>
                                <span class="costo-v">C:$<?php echo number_format($total_costo, 2); ?></span>
                            <?php else: ?>
                                <span style="color:#e2e8f0;">-</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        
                        <td class="no-print acciones-cell">
                            <div style="display:flex; flex-direction:column; gap:2px; align-items:center;">
                                <?php foreach($columnas_presentaciones as $v): 
                                    if(isset($ag['presentaciones'][$v])): ?>
                                    <button onclick='abrirEditar(<?php echo json_encode($ag['presentaciones'][$v]); ?>)' style="color:var(--accent); border:none; background:none; cursor:pointer; font-size:0.6rem;">Edit <?php echo ($v < 1) ? ($v * 1000).'ml' : $v.'L'; ?></button>
                                <?php endif; endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="modalProd" class="modal-overlay">
        <div class="modal-content">
            <h2 id="modal_titulo" style="margin-top:0;">Producto</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" id="m_action">
                <input type="hidden" name="id" id="m_id">
                <input type="hidden" id="precio_ref_1l">

                <div style="margin-bottom:10px;">
                    <label style="font-weight:bold; font-size:0.8rem;">Nombre Comercial completo</label>
                    <input type="text" name="nombre" id="m_nombre" class="form-input" required>
                </div>

                <div style="margin-bottom:10px;">
                    <label style="font-weight:bold; font-size:0.8rem;">Categoría</label>
                    <select name="categoria" id="m_categoria" class="form-input" required>
                        <option value="">-- Seleccionar Categoría --</option>
                        <?php foreach($categorias as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['nombre']); ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                    <div><label style="font-size:0.8rem;">Volumen</label><input type="number" step="0.01" name="volumen_valor" id="m_vol_val" class="form-input" oninput="recalc()"></div>
                    <div><label style="font-size:0.8rem;">Unidad</label><select name="volumen_unidad" id="m_vol_uni" class="form-input"><option value="L">L</option><option value="ml">ml</option></select></div>
                </div>

                <div style="background:#f8fafc; padding:10px; border-radius:10px; margin-bottom:10px; border:1px solid #e2e8f0;">
                    <p style="margin:0 0 5px 0; font-size:0.7rem; font-weight:bold; color:#64748b;">INSUMOS</p>
                    <select name="id_insumo_envase" id="m_envase" class="form-input" style="margin-bottom:5px;">
                        <option value="">-- Envase --</option>
                        <?php foreach($insumos_empaque as $i): ?><option value="<?php echo $i['id']; ?>"><?php echo htmlspecialchars($i['nombre']); ?></option><?php endforeach; ?>
                    </select>
                    <select name="id_insumo_etiqueta" id="m_etiqueta" class="form-input">
                        <option value="">-- Etiqueta --</option>
                        <?php foreach($insumos_empaque as $i): ?><option value="<?php echo $i['id']; ?>"><?php echo htmlspecialchars($i['nombre']); ?></option><?php endforeach; ?>
                    </select>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; background:#f0f9ff; padding:10px; border-radius:10px; margin-bottom:10px;">
                    <div><label style="font-size:0.8rem;">Desc %</label><input type="number" id="m_desc" class="form-input" oninput="recalc()"></div>
                    <div><label style="font-size:0.8rem; font-weight:bold;">Precio $</label><input type="number" step="0.01" name="precio" id="m_precio" class="form-input" required style="color:var(--accent); font-weight:bold;"></div>
                </div>

                <div style="margin-bottom:10px;">
                    <label style="font-size:0.8rem;">Fórmula</label>
                    <select name="id_formula_maestra" id="m_form" class="form-input">
                        <option value="">Ninguna</option>
                        <?php foreach($formulas_list as $f): ?><option value="<?php echo $f['id']; ?>"><?php echo $f['nombre_formula']; ?></option><?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom:15px; background:#fbfbfb; padding:10px; border:1px dashed #cbd5e0; border-radius:10px;">
                    <label style="font-weight:bold; font-size:0.8rem; display:block; margin-bottom:5px;"><i class="fas fa-image"></i> Imagen del Producto</label>
                    <input type="file" name="imagen" id="m_imagen" class="form-input" accept="image/*" style="padding:5px;">
                    <div id="preview_contenedor" style="margin-top:10px; text-align:center; display:none;">
                        <p style="font-size:0.65rem; margin:0; color:#64748b;">Imagen Actual / Previa:</p>
                        <img id="img_preview" src="" style="max-height:90px; max-width:100%; object-fit:contain; border:1px solid #e2e8f0; border-radius:8px; margin-top:5px;">
                    </div>
                </div>

                <div style="margin-bottom:10px;">
                    <label style="font-weight:bold; font-size:0.8rem;">Tipo de Ítem</label>
                    <select name="tipo_producto" id="m_tipo" class="form-input" onchange="toggleCampos()" required>
                        <option value="producto">Producto (Requiere Fórmula)</option>
                        <option value="servicio">Servicio / Accesorio</option>
                    </select>
                </div>

                <div id="campos_producto">
                    <div style="margin-bottom:10px;">
                        <label style="font-size:0.8rem;">Fórmula Maestra</label>
                        <select name="id_formula_maestra" id="m_form" class="form-input">
                            <option value="">Ninguna</option>
                            <?php foreach($formulas_list as $f): ?>
                                <option value="<?php echo $f['id']; ?>"><?php echo $f['nombre_formula']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="background:#f8fafc; padding:10px; border-radius:10px; margin-bottom:10px; border:1px solid #e2e8f0;">
                        <p style="margin:0 0 5px 0; font-size:0.7rem; font-weight:bold; color:#64748b;">INSUMOS</p>
                        <select name="id_insumo_envase" id="m_envase" class="form-input" style="margin-bottom:5px;">
                            <option value="">-- Envase --</option>
                            <?php foreach($insumos_empaque as $i): ?>
                                <option value="<?php echo $i['id']; ?>"><?php echo htmlspecialchars($i['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="id_insumo_etiqueta" id="m_etiqueta" class="form-input">
                            <option value="">-- Etiqueta --</option>
                            <?php foreach($insumos_empaque as $i): ?>
                                <option value="<?php echo $i['id']; ?>"><?php echo htmlspecialchars($i['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <button type="submit" style="width:100%; padding:15px; background:var(--accent); color:white; border:none; border-radius:10px; font-weight:bold; cursor:pointer;">GUARDAR</button>
                <button type="button" onclick="cerrarModal()" style="width:100%; margin-top:10px; background:none; border:none; color:#94a3b8; cursor:pointer;">Cancelar</button>
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
            document.getElementById('m_action').value = 'nuevo';
            document.getElementById('m_id').value = '';
            document.getElementById('m_nombre').value = '';
            document.getElementById('m_categoria').value = '';
            document.getElementById('m_precio').value = '';
            document.getElementById('m_vol_val').value = '1';
            document.getElementById('m_vol_uni').value = 'L';
            document.getElementById('m_form').value = '';
            document.getElementById('m_envase').value = '';
            document.getElementById('m_etiqueta').value = '';
            document.getElementById('precio_ref_1l').value = '0';
            document.getElementById('m_desc').value = '';
            document.getElementById('m_imagen').value = '';
            document.getElementById('preview_contenedor').style.display = 'none';
            document.getElementById('img_preview').src = '';
            document.getElementById('modal_titulo').innerText = 'Nuevo Producto';
            document.getElementById('modalProd').style.display = 'flex';
        }

        function abrirEditar(p) {
            document.getElementById('m_action').value = 'editar';
            document.getElementById('m_id').value = p.id; 
            document.getElementById('m_nombre').value = p.nombre; 
            document.getElementById('m_categoria').value = p.categoria || "";
            document.getElementById('m_precio').value = p.precio;
            document.getElementById('m_vol_val').value = p.volumen_valor; 
            document.getElementById('m_vol_uni').value = p.volumen_unidad;
            document.getElementById('m_form').value = p.id_formula_maestra || "";
            document.getElementById('m_envase').value = p.id_insumo_envase || "";
            document.getElementById('m_etiqueta').value = p.id_insumo_etiqueta || "";
            document.getElementById('precio_ref_1l').value = parseFloat(p.precio_ref_1l) || 0;
            document.getElementById('m_desc').value = '';
            document.getElementById('m_imagen').value = '';
            
            // Renderizado dinámico de la previsualización de imagen guardada en BD
            const prevContenedor = document.getElementById('preview_contenedor');
            const imgPreview = document.getElementById('img_preview');
            if (p.imagen_url && p.imagen_url.trim() !== '') {
                imgPreview.src = p.imagen_url; 
                prevContenedor.style.display = 'block';
            } else {
                imgPreview.src = '';
                prevContenedor.style.display = 'none';
            }

            document.getElementById('modal_titulo').innerText = 'Editar Presentación';
            document.getElementById('modalProd').style.display = 'flex';
        }

        function recalc() {
            const ref = parseFloat(document.getElementById('precio_ref_1l').value) || 0;
            const vol = parseFloat(document.getElementById('m_vol_val').value) || 0;
            const desc = parseFloat(document.getElementById('m_desc').value) || 0;
            if(ref > 0 && vol > 0) document.getElementById('m_precio').value = ((ref * vol) * (1 - (desc / 100))).toFixed(2);
        }

        function toggleMenu() { document.querySelector('.sidebar').classList.toggle('active'); document.getElementById('overlay').classList.toggle('active'); }
        function cerrarModal() { document.getElementById('modalProd').style.display = 'none'; }

        function toggleCampos() {
            const tipo = document.getElementById('m_tipo').value;
            const camposProd = document.getElementById('campos_producto');
            
            if (tipo === 'servicio') {
                camposProd.style.display = 'none';
                // Limpiar valores para no enviar basura
                document.getElementById('m_form').value = '';
                document.getElementById('m_envase').value = '';
                document.getElementById('m_etiqueta').value = '';
            } else {
                camposProd.style.display = 'block';
            }
        }

        // Actualiza abrirEditar para manejar el nuevo tipo
        function abrirEditar(p) {
            // ... código anterior ...
            document.getElementById('m_tipo').value = p.tipo_producto || 'producto';
            toggleCampos(); // Ejecuta para mostrar/ocultar al abrir
            // ... resto del código ...
        }
    </script>
</body>
</html>