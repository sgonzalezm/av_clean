<?php
// =========================================================================
// ENDPOINT AJAX EN LÍNEA 1: Evita cualquier fuga de HTML o caracteres extraños
// =========================================================================
if (isset($_GET['ajax_get_cotizacion'])) {
    require_once '../includes/conexion.php';
    $id_req = $_GET['ajax_get_cotizacion'];
    
    $coti_data = $pdo->prepare("SELECT * FROM cotizaciones WHERE id = ?");
    $coti_data->execute([$id_req]);
    $encabezado = $coti_data->fetch(PDO::FETCH_ASSOC);

    $detalles_data = $pdo->prepare("SELECT d.*, p.nombre AS producto_nombre, p.precio AS precio_producto FROM detalle_cotizacion d LEFT JOIN productos p ON d.producto_id = p.id WHERE d.cotizacion_id = ?");
    $detalles_data->execute([$id_req]);
    $detalles = $detalles_data->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode(['encabezado' => $encabezado, 'detalles' => $detalles]);
    exit;
}

require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$mensaje_exito = "";
$error = "";

// ==========================================
// 1. PROCESAMIENTO DE DATOS (CRUD COMPLETADO)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    // --- ACCIÓN: CREAR O MODIFICAR COTIZACIÓN ---
    if ($_POST['action'] == 'nueva_cotizacion' || $_POST['action'] == 'editar_cotizacion') {
        try {
            $pdo->beginTransaction();

            $cliente_id = $_POST['cliente_id'];
            $usuario_id = $_SESSION['admin_id'] ?? 1;
            $fecha_vencimiento = $_POST['fecha_vencimiento'];
            $notas = $_POST['notas'] ?? '';
            $estado = $_POST['estado'] ?? 'Pendiente';
            
            $productos = $_POST['productos'] ?? [];
            $cantidades = $_POST['cantidades'] ?? [];
            $precios = $_POST['precios'] ?? [];

            if (empty($productos)) {
                throw new Exception("Debes agregar al menos un producto a la cotización.");
            }

            // Consultar el descuento real del cliente desde la BD para validación del Servidor
            $stmt_desc = $pdo->prepare("SELECT tc.descuento_porcentaje FROM clientes cl INNER JOIN tipos_cliente tc ON cl.tipo_cliente_id = tc.id WHERE cl.id = ?");
            $stmt_desc->execute([$cliente_id]);
            $pct_descuento = floatval($stmt_desc->fetchColumn() ?? 0);

            // Calcular Totales con Descuento Aplicado
            $subtotal_general = 0;
            foreach ($productos as $index => $prod_id) {
                $cant = floatval($cantidades[$index]);
                $prec_original = floatval($precios[$index]);
                $subtotal_general += ($cant * $prec_original);
            }
            
            // Deducción del descuento asignado al perfil del cliente
            $descuento_total = $subtotal_general * ($pct_descuento / 100);
            $total_general = $subtotal_general - $descuento_total;

            if ($_POST['action'] == 'nueva_cotizacion') {
                $sql_coti = "INSERT INTO cotizaciones (cliente_id, usuario_id, fecha_vencimiento, subtotal, total, notas, estado) 
                             VALUES (?, ?, ?, ?, ?, ?, 'Pendiente')";
                $stmt_coti = $pdo->prepare($sql_coti);
                $stmt_coti->execute([$cliente_id, $usuario_id, $fecha_vencimiento, $subtotal_general, $total_general, $notas]);
                $cotizacion_id = $pdo->lastInsertId();
                $mensaje_exito = "Cotización #$cotizacion_id guardada con éxito.";
            } else {
                $cotizacion_id = $_POST['cotizacion_id'];
                $sql_coti = "UPDATE cotizaciones SET cliente_id = ?, fecha_vencimiento = ?, subtotal = ?, total = ?, notas = ?, estado = ? WHERE id = ?";
                $stmt_coti = $pdo->prepare($sql_coti);
                $stmt_coti->execute([$cliente_id, $fecha_vencimiento, $subtotal_general, $total_general, $notas, $estado, $cotizacion_id]);
                
                $stmt_clear = $pdo->prepare("DELETE FROM detalle_cotizacion WHERE cotizacion_id = ?");
                $stmt_clear->execute([$cotizacion_id]);
                $mensaje_exito = "Cotización #$cotizacion_id actualizada con éxito.";
            }

            $sql_det = "INSERT INTO detalle_cotizacion (cotizacion_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)";
            $stmt_det = $pdo->prepare($sql_det);

            foreach ($productos as $index => $prod_id) {
                $cant = floatval($cantidades[$index]);
                $prec_original = floatval($precios[$index]);
                
                // Guardamos en el detalle el precio neto (ya con su descuento calculado individualmente)
                $prec_neto = $prec_original * (1 - ($pct_descuento / 100));
                $sub_linea = $cant * $prec_neto;
                
                $stmt_det->execute([$cotizacion_id, $prod_id, $cant, $prec_neto, $sub_linea]);
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al procesar la cotización: " . $e->getMessage();
        }
    }

    // --- ACCIÓN: REGISTRO RÁPIDO DE CLIENTE CORREGIDO (TELEFONO) ---
    if ($_POST['action'] == 'rapido_cliente') {
        $nombre = trim($_POST['nombre_completo']);
        $telefono = trim($_POST['telefono'] ?? '');
        if (!empty($nombre)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO clientes (nombre_completo, telefono) VALUES (?, ?)"); 
                $stmt->execute([$nombre, $telefono]);
                $mensaje_exito = "Cliente registrado con éxito.";
            } catch (PDOException $e) {
                $error = "Error al insertar en la base de datos: " . $e->getMessage();
            }
        } else {
            $error = "El nombre del cliente no puede estar vacío.";
        }
    }

    // --- ACCIÓN: ELIMINAR COTIZACIÓN ---
    if ($_POST['action'] == 'eliminar') {
        $id_borrar = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM cotizaciones WHERE id = ?");
        if ($stmt->execute([$id_borrar])) {
            $mensaje_exito = "Cotización eliminada correctamente.";
        } else {
            $error = "No se pudo eliminar la cotización.";
        }
    }
}

// Queries estructuradas de la interfaz
$query_coti = "SELECT c.*, cl.nombre_completo as cliente_nombre, u.nombre as vendedor_nombre 
               FROM cotizaciones c
               INNER JOIN clientes cl ON c.cliente_id = cl.id
               LEFT JOIN usuarios_admin u ON c.usuario_id = u.id
               ORDER BY c.id DESC";
$cotizaciones = $pdo->query($query_coti)->fetchAll();

// Traemos a los clientes emparejados con su porcentaje de descuento asignado por tipo
$query_clientes = "SELECT cl.id, cl.nombre_completo, COALESCE(tc.descuento_porcentaje, 0) as descuento_porcentaje 
                   FROM clientes cl 
                   LEFT JOIN tipos_cliente tc ON cl.tipo_cliente_id = tc.id 
                   ORDER BY cl.nombre_completo ASC";
$clientes = $pdo->query($query_clientes)->fetchAll();
$productos_bd = $pdo->query("SELECT id, nombre, precio FROM productos ORDER BY nombre ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Cotizador - AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        :root {
            --accent: #10b981;
            --primary: #3182ce;
            --success: #38a169;
            --danger: #e53e3e;
            --dark: #1e293b;
            --bg: #f8fafc;
            --pdf-color: #dc2626;
            --view-color: #4f46e5;
        }
        body { margin: 0; background: var(--bg); font-family: sans-serif; overflow-x: hidden; color: #334155; }
        .layout-container { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; width: 100%; }
        .sidebar-wrapper { background: var(--dark); min-height: 100vh; display: block; }
        .main-content { padding: 30px; box-sizing: border-box; width: 100%; background: var(--bg); }
        .header-mobile { display: none; position: fixed; top: 0; left: 0; right: 0; height: 60px; background: var(--dark); color: white; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 2000; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
        .menu-toggle { background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; }
        .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; width: 100%; }
        .btn { padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; font-size: 0.9rem; transition: background 0.2s; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: #2b6cb0; }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-pdf { background: var(--pdf-color); color: white; }
        .btn-pdf:hover { background: #b91c1c; }
        .btn-view { background: var(--view-color); color: white; }
        .btn-view:hover { background: #4338ca; }
        .btn-outline { background: white; border: 1px solid #cbd5e0; color: var(--dark); }
        .table-responsive { width: 100%; overflow-x: auto; background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .report-table { width: 100%; border-collapse: collapse; min-width: 900px; }
        .report-table th, .report-table td { padding: 14px 15px; text-align: left; border-bottom: 1px solid #edf2f7; }
        .report-table th { background: #f1f5f9; color: #4a5568; font-weight: bold; }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold; text-transform: uppercase; }
        .badge-Pendiente { background: #feebc8; color: #c05621; }
        .badge-Aceptada { background: #c6f6d5; color: #22543d; }
        .badge-Rechazada { background: #fed7d7; color: #9b2c2c; }
        .modal { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2500; justify-content:center; align-items:center; padding: 15px; box-sizing: border-box; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 25px; border-radius: 10px; width: 100%; max-width: 750px; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 25px rgba(0,0,0,0.15); position: relative; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #edf2f7; padding-bottom: 10px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: bold; color: #4a5568; font-size: 0.9rem; }
        .form-control { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e0; border-radius: 6px; box-sizing: border-box; font-size: 0.95rem; }
        .product-row { display: grid; grid-template-columns: 3fr 1fr 1fr auto; gap: 10px; margin-bottom: 12px; align-items: center; }
        .preview-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .preview-table th, .preview-table td { padding: 10px; border: 1px solid #e2e8f0; text-align: left; }
        .preview-table th { background: #f8fafc; font-weight: bold; }

        @media (max-width: 992px) {
            .layout-container { grid-template-columns: 1fr; }
            .sidebar-wrapper { display: none; position: fixed; top: 60px; left: 0; width: 260px; bottom: 0; z-index: 1999; }
            .sidebar-wrapper.active { display: block; }
            .header-mobile { display: flex; }
            .main-content { padding: 85px 15px 30px 15px; }
            .product-row { grid-template-columns: 1fr; gap: 8px; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px; background: #f8fafc; }
        }
    </style>
</head>
<body>

<div class="header-mobile">
    <h2 style="margin:0; font-size: 1.2rem; font-weight: bold;">🧪 AHD Clean</h2>
    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
</div>

<div class="layout-container">
    <div class="sidebar-wrapper" id="sidebarMenu">
        <?php include 'sidebar.php'; ?>
    </div>

    <div class="main-content">
        <div class="header-actions">
            <div>
                <h1 style="margin:0; color: var(--dark); font-size: 1.8rem; font-weight: bold;">📑 Portal de Cotizaciones</h1>
                <p style="margin:5px 0 0 0; color:#718096; font-size: 0.95rem;">Módulo de Cotizaciones estable con vinculación directa al catálogo de productos.</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-outline" onclick="abrirModal('modalCliente')">👤 + Cliente Express</button>
                <button class="btn btn-primary" onclick="lanzarNuevaCotizacion()">✍️ Nueva Cotización</button>
            </div>
        </div>

        <?php if(!empty($mensaje_exito)): ?>
            <div style="background: #c6f6d5; color: #22543d; padding: 12px; margin-bottom: 20px; border-radius: 6px; font-weight: bold;"><?= $mensaje_exito ?></div>
        <?php endif; ?>
        <?php if(!empty($error)): ?>
            <div style="background: #fed7d7; color: #9b2c2c; padding: 12px; margin-bottom: 20px; border-radius: 6px; font-weight: bold;"><?= $error ?></div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Cliente</th>
                        <th>Emisión</th>
                        <th>Vencimiento</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($cotizaciones) > 0): ?>
                        <?php foreach($cotizaciones as $c): ?>
                            <tr>
                                <td><b>#<?= str_pad($c['id'], 5, "0", STR_PAD_LEFT) ?></b></td>
                                <td><?= htmlspecialchars($c['cliente_nombre']) ?></td>
                                <td><?= date('d/m/Y', strtotime($c['fecha_emision'])) ?></td>
                                <td><?= date('d/m/Y', strtotime($c['fecha_vencimiento'])) ?></td>
                                <td><b>$<?= number_format($c['total'], 2) ?></b></td>
                                <td><span class="badge badge-<?= $c['estado'] ?>"><?= $c['estado'] ?></span></td>
                                <td>
                                    <div style="display:flex; gap: 6px; align-items: center;">
                                        <button class="btn btn-view" onclick="verArticulos(<?= $c['id'] ?>)" style="padding: 6px 10px; font-size: 0.8rem;" title="Ver Productos">👁️ Ver</button>
                                        <a href="generar_pdf_cotizacion.php?id=<?= $c['id'] ?>" target="_blank" class="btn btn-pdf" style="padding: 6px 10px; font-size: 0.8rem;" title="Exportar PDF">📄 PDF</a>
                                        <button class="btn btn-primary" onclick="editarCotizacion(<?= $c['id'] ?>)" style="padding: 6px 10px; font-size: 0.8rem;">✏️ Editar</button>
                                        <form method="POST" onsubmit="return confirm('¿Eliminar esta cotización?');" style="margin:0;">
                                            <input type="hidden" name="action" value="eliminar">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="btn btn-danger" style="padding: 6px 10px; font-size: 0.8rem;">🗑️</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center; color:#a0aec0; padding: 40px;">No hay cotizaciones registradas.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="modalCliente" class="modal">
    <div class="modal-content" style="max-width: 420px;">
        <div class="modal-header">
            <h3>👤 Registrar Cliente Express</h3>
            <button onclick="cerrarModal('modalCliente')" style="border:none; background:none; font-size:1.6rem; cursor:pointer;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="rapido_cliente">
            <div class="form-group">
                <label>Nombre Completo</label>
                <input type="text" name="nombre_completo" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Teléfono</label>
                <input type="text" name="telefono" class="form-control">
            </div>
            <button type="submit" class="btn btn-success" style="width: 100%; justify-content:center;">Guardar Cliente</button>
        </form>
    </div>
</div>

<div id="modalVerArticulos" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 id="lblVerArticulosTitulo">📦 Artículos de la Cotización</h3>
            <button onclick="cerrarModal('modalVerArticulos')" style="border:none; background:none; font-size:1.6rem; cursor:pointer;">&times;</button>
        </div>
        <div style="margin-bottom: 15px;">
            <p style="margin: 4px 0;"><b>Notas / Términos:</b> <span id="lblVerNotas" style="color: #64748b; font-style: italic;">Ninguna.</span></p>
        </div>
        <table class="preview-table">
            <thead>
                <tr>
                    <th>Producto / Presentación</th>
                    <th>Cantidad</th>
                    <th>Precio Unitario (Neto)</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody id="tblArticulosCuerpo"></tbody>
        </table>
        <div style="text-align: right; margin-top: 15px; font-size: 1.1rem;">
            <b>Total General: <span id="lblVerTotal" style="color: var(--primary);">$0.00</span></b>
        </div>
        <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
            <button class="btn btn-outline" onclick="cerrarModal('modalVerArticulos')">Cerrar Ventana</button>
        </div>
    </div>
</div>

<div id="modalCotizacion" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="lblModalTitulo">✍️ Crear Cotización Formal</h3>
            <button onclick="cerrarModal('modalCotizacion')" style="border:none; background:none; font-size:1.6rem; cursor:pointer;">&times;</button>
        </div>
        <form method="POST" id="formCotizacion">
            <input type="hidden" name="action" id="txtFormAction" value="nueva_cotizacion">
            <input type="hidden" name="cotizacion_id" id="txtCotizacionId" value="">
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px;">
                <div class="form-group">
                    <label>Cliente Asignado</label>
                    <select name="cliente_id" id="selCliente" class="form-control" onchange="recalcularCotizacion()" required>
                        <option value="">-- Selecciona un cliente --</option>
                        <?php foreach($clientes as $cl): ?>
                            <option value="<?= $cl['id'] ?>" data-descuento="<?= $cl['descuento_porcentaje'] ?>"><?= htmlspecialchars($cl['nombre_completo']) ?> (Desc: <?= $cl['descuento_porcentaje'] ?>%)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fecha Vencimiento</label>
                    <input type="date" name="fecha_vencimiento" id="txtFechaVen" class="form-control" required>
                </div>
                <div class="form-group" id="containerEstado" style="display:none;">
                    <label>Estado Comercial</label>
                    <select name="estado" id="selEstado" class="form-control">
                        <option value="Pendiente">Pendiente</option>
                        <option value="Aceptada">Aceptada</option>
                        <option value="Rechazada">Rechazada</option>
                    </select>
                </div>
            </div>

            <h4 style="margin: 25px 0 10px 0; border-bottom: 2px solid #edf2f7; padding-bottom: 6px;">📦 Conceptos / Artículos</h4>
            <div id="productosContenedor"></div>

            <button type="button" class="btn btn-outline" style="margin-top: 8px; font-size:0.85rem;" onclick="agregarFilaProducto()">➕ Agregar Artículo</button>

            <div class="form-group" style="margin-top: 20px;">
                <label>Notas Internas o Condiciones</label>
                <textarea name="notas" id="txtNotas" class="form-control" rows="2"></textarea>
            </div>

            <div style="background: #f1f5f9; padding: 15px; border-radius: 8px; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-top: 15px; text-align: right;">
                <div>
                    <span style="font-size: 0.9rem; color: #64748b;">Subtotal Base:</span>
                    <h4 style="margin: 0; color: #475569;" id="lblSubtotalBase">$0.00</h4>
                </div>
                <div>
                    <span style="font-size: 0.9rem; color: var(--success);">Descuento Aplicado:</span>
                    <h4 style="margin: 0; color: var(--success);" id="lblDescuentoPresumir">-$0.00 (0%)</h4>
                </div>
                <div>
                    <span style="font-size: 1rem; font-weight: bold; color: var(--dark);">Total Propuesto:</span>
                    <h3 style="margin: 0; color: var(--primary);" id="txtTotalCotizacion">$0.00</h3>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 25px;">
                <button type="button" class="btn btn-outline" onclick="cerrarModal('modalCotizacion')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleSidebar() { document.getElementById('sidebarMenu').classList.toggle('active'); }
function abrirModal(id) { document.getElementById(id).classList.add('active'); }
function cerrarModal(id) { document.getElementById(id).classList.remove('active'); }

function lanzarNuevaCotizacion() {
    document.getElementById('lblModalTitulo').innerText = "✍️ Crear Cotización Formal";
    document.getElementById('txtFormAction').value = "nueva_cotizacion";
    document.getElementById('txtCotizacionId').value = "";
    document.getElementById('containerEstado').style.display = "none";
    document.getElementById('formCotizacion').reset();
    document.getElementById('productosContenedor').innerHTML = '';
    agregarFilaProducto();
    recalcularCotizacion();
    abrirModal('modalCotizacion');
}

function agregarFilaProducto(prodId = '', cantidad = '', precio = '') {
    const contenedor = document.getElementById('productosContenedor');
    const row = document.createElement('div');
    row.className = 'product-row';
    
    let optionsHtml = '<option value="">-- Elige un Producto --</option>';
    <?php foreach($productos_bd as $p): ?>
        optionsHtml += `<option value="<?= $p['id'] ?>" data-precio="<?= $p['precio'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>`;
    <?php endforeach; ?>

    row.innerHTML = `
        <select name="productos[]" class="form-control select-producto" onchange="autoAsignarPrecio(this)" required>
            ${optionsHtml}
        </select>
        <input type="number" name="cantidades[]" class="form-control input-cantidad" placeholder="Cant." min="0.01" step="any" value="${cantidad}" oninput="recalcularCotizacion()" required>
        <input type="number" name="precios[]" class="form-control input-precio" placeholder="Precio Un." min="0.00" step="any" value="${precio}" oninput="recalcularCotizacion()" required>
        <button type="button" class="btn btn-danger" onclick="eliminarFila(this)">🗑️</button>
    `;
    
    contenedor.appendChild(row);
    if(prodId) row.querySelector('.select-producto').value = prodId;
}

function autoAsignarPrecio(selectElement) {
    const fila = selectElement.closest('.product-row');
    const optionSeleccionada = selectElement.options[selectElement.selectedIndex];
    const precioBase = optionSeleccionada.getAttribute('data-precio') || 0;
    
    fila.querySelector('.input-precio').value = precioBase;
    if(!fila.querySelector('.input-cantidad').value) fila.querySelector('.input-cantidad').value = 1;
    recalcularCotizacion();
}

function eliminarFila(btn) {
    const contenedor = document.getElementById('productosContenedor');
    if (contenedor.querySelectorAll('.product-row').length > 1) {
        btn.closest('.product-row').remove();
        recalcularCotizacion();
    } else {
        alert("Debe contener al menos 1 producto.");
    }
}

function recalcularCotizacion() {
    let subtotalBase = 0;
    
    // Recorrer filas sumando precios de lista/originales
    document.querySelectorAll('.product-row').forEach(fila => {
        const cant = parseFloat(fila.querySelector('.input-cantidad').value) || 0;
        const prec = parseFloat(fila.querySelector('.input-precio').value) || 0;
        subtotalBase += (cant * prec);
    });
    
    // Obtener porcentaje de descuento del cliente seleccionado
    const selCliente = document.getElementById('selCliente');
    const pctDescuento = (selCliente.selectedIndex > 0) ? parseFloat(selCliente.options[selCliente.selectedIndex].getAttribute('data-descuento')) : 0;
    
    // Desglose matemático
    let montoDescuento = subtotalBase * (pctDescuento / 100);
    let totalNeto = subtotalBase - montoDescuento;
    
    // Actualización visual del desglose
    document.getElementById('lblSubtotalBase').innerText = '$' + subtotalBase.toFixed(2);
    document.getElementById('lblDescuentoPresumir').innerText = '-$' + montoDescuento.toFixed(2) + ' (' + pctDescuento + '%)';
    document.getElementById('txtTotalCotizacion').innerText = '$' + totalNeto.toFixed(2);
}

function editarCotizacion(id) {
    fetch(`cotizador.php?ajax_get_cotizacion=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('lblModalTitulo').innerText = `✏️ Editar Cotización Folio #${id.toString().padStart(5, '0')}`;
            document.getElementById('txtFormAction').value = "editar_cotizacion";
            document.getElementById('txtCotizacionId').value = id;
            document.getElementById('selCliente').value = data.encabezado.cliente_id;
            document.getElementById('txtFechaVen').value = data.encabezado.fecha_vencimiento;
            document.getElementById('selEstado').value = data.encabezado.estado;
            document.getElementById('txtNotas').value = data.encabezado.notas || '';
            document.getElementById('containerEstado').style.display = "block";
            
            const contenedor = document.getElementById('productosContenedor');
            contenedor.innerHTML = '';
            
            // Obtener descuento del cliente asignado para revertir el precio neto a precio de lista en el input
            const selCliente = document.getElementById('selCliente');
            const pct = (selCliente.selectedIndex > 0) ? parseFloat(selCliente.options[selCliente.selectedIndex].getAttribute('data-descuento')) : 0;
            
            data.detalles.forEach(d => {
                // Si la cotización se guardó con descuento, devolvemos el valor original para mantener limpia la edición
                let precioOriginal = parseFloat(d.precio_unitario) / (1 - (pct / 100));
                agregarFilaProducto(d.producto_id, d.cantidad, precioOriginal.toFixed(2));
            });
            
            recalcularCotizacion();
            abrirModal('modalCotizacion');
        })
        .catch(err => {
            alert("Error al parsear la información de la cotización.");
        });
}

function verArticulos(id) {
    fetch(`cotizador.php?ajax_get_cotizacion=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('lblVerArticulosTitulo').innerText = `📦 Artículos - Cotización Folio #${id.toString().padStart(5, '0')}`;
            document.getElementById('lblVerNotas').innerText = data.encabezado.notas ? data.encabezado.notas : 'Ninguna.';
            document.getElementById('lblVerTotal').innerText = '$' + parseFloat(data.encabezado.total).toFixed(2);
            
            const tbody = document.getElementById('tblArticulosCuerpo');
            tbody.innerHTML = '';
            
            data.detalles.forEach(d => {
                const fila = document.createElement('tr');
                const nombreProd = d.producto_nombre ? d.producto_nombre : `Producto ID: ${d.producto_id}`;
                const cant = parseFloat(d.cantidad);
                const precio = parseFloat(d.precio_unitario);
                const sub = parseFloat(d.subtotal);
                
                fila.innerHTML = `
                    <td>${nombreProd}</td>
                    <td>${cant}</td>
                    <td>$${precio.toFixed(2)}</td>
                    <td><b>$${sub.toFixed(2)}</b></td>
                `;
                tbody.appendChild(fila);
            });
            
            abrirModal('modalVerArticulos');
        })
        .catch(err => {
            alert("Error al obtener la lista de artículos.");
        });
}

window.onclick = function(e) { if (e.target.classList.contains('modal')) e.target.classList.remove('active'); }
</script>
</body>
</html>