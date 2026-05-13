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
    $id_envase = !empty($_POST['id_insumo_envase']) ? $_POST['id_insumo_envase'] : null;
    $id_etiqueta = !empty($_POST['id_insumo_etiqueta']) ? $_POST['id_insumo_etiqueta'] : null;

    try {
        if ($_POST['action'] == 'nuevo') {
            $pdo->beginTransaction();
            $sql = "INSERT INTO productos (nombre, precio, categoria, id_formula_maestra, volumen_valor, volumen_unidad, id_insumo_envase, id_insumo_etiqueta) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$nombre, $precio, $categoria, $id_formula, $vol_val, $vol_uni, $id_envase, $id_etiqueta]);
            $nuevo_id = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO inventario (producto_id, stock) VALUES (?, 0)")->execute([$nuevo_id]);
            $pdo->commit();
            header("Location: catalogo_productos.php?msj=Creado"); exit;
        } 
        elseif ($_POST['action'] == 'editar') {
            $sql = "UPDATE productos SET nombre=?, precio=?, categoria=?, id_formula_maestra=?, volumen_valor=?, volumen_unidad=?, id_insumo_envase=?, id_insumo_etiqueta=? WHERE id=?";
            $pdo->prepare($sql)->execute([$nombre, $precio, $categoria, $id_formula, $vol_val, $vol_uni, $id_envase, $id_etiqueta, $id]);
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

// --- LÓGICA DE AGRUPACIÓN ---
$productos_agrupados = [];
foreach ($productos_raw as $p) {
    $nombre_base = trim(preg_replace('/\s*\([\d\.]+m?L\)$/i', '', $p['nombre']));
    $key = $p['categoria'] . "_" . $nombre_base;

    if (!isset($productos_agrupados[$key])) {
        $productos_agrupados[$key] = [
            'nombre_base' => $nombre_base,
            'categoria' => $p['categoria'],
            'presentaciones' => []
        ];
    }
    $vol_key = ($p['volumen_unidad'] == 'ml') ? ($p['volumen_valor'] / 1000) : (float)$p['volumen_valor'];
    $productos_agrupados[$key]['presentaciones'][(string)$vol_key] = $p;
}

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
        .header-mobile { display: none; position: fixed; top: 0; left: 0; right: 0; height: 60px; background: var(--dark); color: white; align-items: center; justify-content: space-between; padding: 0 15px; z-index: 2000; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
        .main { padding: 25px; transition: 0.3s; }
        .badge-vol { background: #f1f5f9; color: #475569; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; }
        
        /* Estilos Administrativos (Se verán en pantalla) */
        .rent-tag { padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: bold; display: inline-block; margin-top: 3px; }
        .rent-alta { color: #059669; background: #ecfdf5; border: 1px solid #bbf7d0; } 
        .rent-media { color: #d97706; background: #fffbeb; border: 1px solid #fef3c7; } 
        .rent-baja { color: #dc2626; background: #fef2f2; border: 1px solid #fee2e2; } 
        .costo-v { display: block; font-size: 0.6rem; color: #94a3b8; margin-top: 2px; }

        .desktop-table { background: white; border-radius: 15px; overflow: hidden; border: 1px solid #e2e8f0; }
        .desktop-table table { width: 100%; border-collapse: collapse; }
        .desktop-table th { background: #f8fafc; padding: 12px; text-align: center; color: #64748b; font-size: 0.75rem; text-transform: uppercase; }
        .desktop-table td { padding: 10px; border-top: 1px solid #f1f5f9; text-align: center; vertical-align: middle; }
        .precio-v { display: block; font-weight: 800; color: var(--accent); font-size: 0.95rem; }

        @media (max-width: 992px) {
            .header-mobile { display: flex; }
            .main { margin-left: 0 !important; padding: 80px 15px 120px 15px !important; }
            .desktop-table { display: none; }
            .mobile-cards { display: flex; flex-direction: column; gap: 12px; }
        }

        /* CONFIGURACIÓN ESPECIAL PARA EL PDF / IMPRESIÓN */
        @media print {
            @page { size: letter; margin: 0.8cm; }
            .sidebar, .header-mobile, .no-print, .acciones-cell, .hide-mobile, .search-container, #overlay { display: none !important; }
            .main { margin: 0 !important; padding: 0 !important; }
            .print-header { display: block !important; text-align: center; margin-bottom: 15px; border-bottom: 2px solid #000; padding-bottom: 10px; }
            .desktop-table { display: block !important; border: 0.5px solid #ccc !important; border-radius: 0 !important; }
            table { width: 100% !important; border-collapse: collapse !important; }
            th { background: #eee !important; color: #000 !important; font-size: 7.5pt !important; padding: 5px !important; border: 0.5px solid #ccc !important; }
            td { padding: 5px !important; font-size: 7.5pt !important; border-bottom: 0.5px solid #eee !important; border-left: 0.5px solid #eee !important; }
            
            /* Ocultar información interna en el PDF */
            .rent-tag, .costo-v, small, .badge-vol { display: none !important; }
            .precio-v { color: #000 !important; font-weight: bold; }
            strong { font-size: 8.5pt !important; }
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
        <h2 style="margin:0;">AHD CLEAN - CATÁLOGO DE PRECIOS</h2>
        <p style="margin:5px 0;">Emisión: <?php echo date('d/m/Y'); ?></p>
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
            <h1><i class="fas fa-columns"></i> Catálogo Maestro</h1>
            <div style="display:flex; gap:10px;">
                <a href="?export=excel" style="background:var(--success); color:white; padding:12px 18px; border-radius:10px; text-decoration:none; font-weight:bold;"><i class="fas fa-file-excel"></i> Excel</a>
                <button onclick="window.print()" style="background:#e11d48; color:white; border:none; padding:12px 18px; border-radius:10px; font-weight:bold; cursor:pointer;"><i class="fas fa-print"></i> PDF Clientes</button>
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
                        <th style="text-align:left; width:220px; padding-left:20px;">Línea de Producto</th>
                        <th>500ml</th>
                        <th>1 Litro</th>
                        <th>4 Litros</th>
                        <th>10 Litros</th>
                        <th>20 Litros</th>
                        <th class="no-print">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos_agrupados as $ag): ?>
                    <tr class="fila-producto" data-search="<?php echo strtolower($ag['nombre_base'] . ' ' . $ag['categoria']); ?>">
                        <td style="text-align:left; padding-left:20px;">
                            <strong><?php echo htmlspecialchars($ag['nombre_base']); ?></strong><br>
                            <small class="admin-only" style="color:#94a3b8; font-size:0.7rem; text-transform:uppercase;"><?php echo htmlspecialchars($ag['categoria']); ?></small>
                        </td>
                        
                        <?php foreach(['0.5', '1', '4', '10', '20'] as $v): 
                            $p = $ag['presentaciones'][$v] ?? null;
                        ?>
                        <td>
                            <?php if($p): 
                                $c_liq = ($p['costo_l_masivo'] ?? 0) * $p['volumen_valor'];
                                $c_emp = ($p['costo_envase_unit'] ?? 0) + ($p['costo_etiqueta_unit'] ?? 0);
                                $total_costo = $c_liq + $c_emp;
                                $util_m = $p['precio'] - $total_costo;
                                $marg_m = ($p['precio'] > 0) ? ($util_m / $p['precio']) * 100 : 0;
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
                            <div style="display:flex; flex-direction:column; gap:3px; align-items:center;">
                                <?php foreach(['0.5', '1', '4', '10', '20'] as $v): 
                                    if(isset($ag['presentaciones'][$v])): ?>
                                    <button onclick='abrirEditar(<?php echo json_encode($ag['presentaciones'][$v]); ?>)' style="color:var(--accent); border:none; background:none; cursor:pointer; font-size:0.65rem;"><i class="fas fa-edit"></i> <?php echo ($v < 1) ? '500ml' : $v.'L'; ?></button>
                                <?php endif; endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mobile-cards no-print">
            <?php foreach ($productos_raw as $p): 
                 $c_liq = ($p['costo_l_masivo'] ?? 0) * $p['volumen_valor'];
                 $c_emp = ($p['costo_envase_unit'] ?? 0) + ($p['costo_etiqueta_unit'] ?? 0);
                 $total_costo = $c_liq + $c_emp;
                 $util_m = $p['precio'] - $total_costo;
                 $marg_m = ($p['precio'] > 0) ? ($util_m / $p['precio']) * 100 : 0;
                 $clase_r = ($marg_m >= 45) ? 'rent-alta' : (($marg_m >= 25) ? 'rent-media' : 'rent-baja');
            ?>
            <div class="prod-card fila-producto" data-search="<?php echo strtolower($p['nombre'] . ' ' . $p['categoria']); ?>">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <h3 style="margin:0; font-size:1.1rem;"><?php echo htmlspecialchars($p['nombre']); ?></h3>
                        <span class="badge-vol"><?php echo (float)$p['volumen_valor'].$p['volumen_unidad']; ?></span>
                    </div>
                    <span class="rent-tag <?php echo $clase_r; ?>"><?php echo number_format($marg_m, 1); ?>%</span>
                </div>
                <div style="font-size:1.3rem; font-weight:900; color:var(--accent); margin:10px 0;">$<?php echo number_format($p['precio'], 2); ?></div>
                <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #f1f5f9; padding-top:10px;">
                    <small style="color:#64748b;">Costo: $<?php echo number_format($total_costo, 2); ?></small>
                    <div>
                        <button onclick='abrirEditar(<?php echo json_encode($p); ?>)' style="color:var(--accent); border:none; background:none; font-size:1.2rem;"><i class="fas fa-edit"></i></button>
                        <button onclick="confirmarEliminar(<?php echo $p['id']; ?>)" style="color:#ef4444; border:none; background:none; font-size:1.2rem; margin-left:15px;"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- MODAL PRODUCTO -->
    <div id="modalProd" class="modal-overlay">
        <div class="modal-content">
            <h2 id="modal_titulo" style="margin-top:0;">Nuevo Producto</h2>
            <form method="POST">
                <input type="hidden" name="action" id="m_action" value="editar">
                <input type="hidden" name="id" id="m_id">
                <input type="hidden" id="precio_ref_1l">

                <div style="margin-bottom:12px;">
                    <label style="font-weight:bold; font-size:0.8rem;">Nombre Comercial (Ej: Multiusos (1L))</label>
                    <input type="text" name="nombre" id="m_nombre" class="form-input" required>
                </div>
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div><label style="font-weight:bold; font-size:0.8rem;">Volumen</label><input type="number" step="0.01" name="volumen_valor" id="m_vol_val" class="form-input" oninput="recalc()"></div>
                    <div><label style="font-weight:bold; font-size:0.8rem;">Unidad</label><select name="volumen_unidad" id="m_vol_uni" class="form-input"><option value="L">Litros (L)</option><option value="ml">Mililitros (ml)</option></select></div>
                </div>

                <div style="background:#f8fafc; padding:12px; border-radius:12px; margin-bottom:12px; border:1px solid #e2e8f0;">
                    <p style="margin:0 0 10px 0; font-size:0.75rem; font-weight:bold; color:#64748b; text-transform:uppercase;">Configuración de Empaque</p>
                    <div style="margin-bottom:8px;">
                        <label style="font-size:0.8rem;">Insumo Envase</label>
                        <select name="id_insumo_envase" id="m_envase" class="form-input">
                            <option value="">-- Sin Envase --</option>
                            <?php foreach($insumos_empaque as $i): ?><option value="<?php echo $i['id']; ?>"><?php echo htmlspecialchars($i['nombre']); ?> ($<?php echo $i['precio_unitario']; ?>)</option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:0.8rem;">Insumo Etiqueta</label>
                        <select name="id_insumo_etiqueta" id="m_etiqueta" class="form-input">
                            <option value="">-- Sin Etiqueta --</option>
                            <?php foreach($insumos_empaque as $i): ?><option value="<?php echo $i['id']; ?>"><?php echo htmlspecialchars($i['nombre']); ?> ($<?php echo $i['precio_unitario']; ?>)</option><?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="background:#f0f9ff; padding:12px; border-radius:12px; margin-bottom:12px; border:1px solid #bae6fd;">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div><label style="color:#0369a1; font-weight:bold; font-size:0.8rem;">Desc. % (Sobre 1L)</label><input type="number" id="m_desc" class="form-input" oninput="recalc()"></div>
                        <div><label style="font-weight:bold; font-size:0.8rem;">Precio Final ($)</label><input type="number" step="0.01" name="precio" id="m_precio" class="form-input" required style="font-weight:900; color:var(--accent);"></div>
                    </div>
                </div>

                <div style="margin-bottom:12px;"><label style="font-weight:bold; font-size:0.8rem;">Categoría</label><select name="categoria" id="m_cat" class="form-input"><?php foreach($categorias as $c): ?><option value="<?php echo $c['nombre']; ?>"><?php echo $c['nombre']; ?></option><?php endforeach; ?></select></div>
                <div style="margin-bottom:15px;"><label style="font-weight:bold; font-size:0.8rem;">Fórmula Maestra</label><select name="id_formula_maestra" id="m_form" class="form-input"><option value="">Ninguna</option><?php foreach($formulas_list as $f): ?><option value="<?php echo $f['id']; ?>"><?php echo $f['nombre_formula']; ?></option><?php endforeach; ?></select></div>

                <button type="submit" style="width:100%; padding:15px; background:var(--accent); color:white; border:none; border-radius:12px; font-weight:900; font-size:1.1rem; cursor:pointer;">GUARDAR PRODUCTO</button>
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
            document.getElementById('m_envase').value = p.id_insumo_envase || "";
            document.getElementById('m_etiqueta').value = p.id_insumo_etiqueta || "";
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

        function toggleMenu() { document.querySelector('.sidebar').classList.toggle('active'); document.getElementById('overlay').classList.toggle('active'); }
        function cerrarModal() { document.getElementById('modalProd').style.display = 'none'; }
    </script>
</body>
</html>