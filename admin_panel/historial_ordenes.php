<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// --- 1. LÓGICA DE EXPORTACIÓN A EXCEL (DETALLE DE ORDEN) ---
if (isset($_GET['exportar']) && $_GET['exportar'] == 'excel' && isset($_GET['id'])) {
    $id_orden = $_GET['id'];
    
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Detalle_Orden_AHD_#$id_orden.xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    $stmt_i = $pdo->prepare("
        SELECT odi.*, i.nombre, i.unidad_medida, prov.nombre_empresa as proveedor 
        FROM orden_detalle_insumos odi 
        JOIN insumos i ON odi.id_insumo = i.id 
        LEFT JOIN proveedores prov ON i.id_proveedor = prov.id_proveedor
        WHERE odi.id_orden = ?
        ORDER BY prov.nombre_empresa ASC
    ");
    $stmt_i->execute([$id_orden]);
    $insumos_excel = $stmt_i->fetchAll();

    echo '<table border="1">';
    echo '<tr><th colspan="3" style="background:#4c51bf; color:white;">AHD CLEAN - REQUISICION DE ORDEN #' . $id_orden . '</th></tr>';
    echo '<tr><th>Proveedor</th><th>Insumo</th><th>Cantidad Requerida</th></tr>';
    foreach ($insumos_excel as $i) {
        $p_nombre = $i['proveedor'] ?? 'Sin Proveedor';
        echo "<tr><td>$p_nombre</td><td>{$i['nombre']}</td><td>" . number_format($i['cantidad_usada'], 3) . " {$i['unidad_medida']}</td></tr>";
    }
    echo '</table>';
    exit();
}

$mensaje = "";

// --- 2. LÓGICA PARA RECIBIR INSUMOS (CARGAR AL STOCK) ---
if (isset($_POST['recibir_insumos_orden'])) {
    $id_orden = $_POST['id_orden'];
    try {
        $pdo->beginTransaction();
        $stmt_ins = $pdo->prepare("SELECT id_insumo, cantidad_usada FROM orden_detalle_insumos WHERE id_orden = ?");
        $stmt_ins->execute([$id_orden]);
        $insumos = $stmt_ins->fetchAll();

        $stmt_update = $pdo->prepare("UPDATE insumos SET stock_actual = COALESCE(stock_actual, 0) + ? WHERE id = ?");
        foreach ($insumos as $ins) {
            $stmt_update->execute([$ins['cantidad_usada'], $ins['id_insumo']]);
        }

        $stmt_status = $pdo->prepare("UPDATE ordenes_produccion SET estado = 'SURTIDO' WHERE id = ?");
        $stmt_status->execute([$id_orden]);

        $pdo->commit();
        $mensaje = "<div class='alert exito'>¡Orden #$id_orden recibida y stock actualizado!</div>";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $mensaje = "<div class='alert error'>Error: " . $e->getMessage() . "</div>";
    }
}

// --- 3. CONSULTA DE ÓRDENES Y DETALLES ---
$ordenes = $pdo->query("SELECT * FROM ordenes_produccion ORDER BY fecha_registro DESC")->fetchAll();
$detalle_id = $_GET['ver'] ?? null;
$insumos_detalle = [];
$productos_detalle = [];

if ($detalle_id) {
    $stmt_i = $pdo->prepare("SELECT odi.*, i.nombre, i.unidad_medida, prov.nombre_empresa as proveedor FROM orden_detalle_insumos odi JOIN insumos i ON odi.id_insumo = i.id LEFT JOIN proveedores prov ON i.id_proveedor = prov.id_proveedor WHERE odi.id_orden = ? ORDER BY prov.nombre_empresa ASC");
    $stmt_i->execute([$detalle_id]);
    $insumos_detalle = $stmt_i->fetchAll();

    $stmt_p = $pdo->prepare("SELECT odp.*, p.nombre FROM orden_detalle_productos odp JOIN productos p ON odp.id_producto = p.id WHERE odp.id_orden = ?");
    $stmt_p->execute([$detalle_id]);
    $productos_detalle = $stmt_p->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Historial Órdenes | AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        :root { --accent: #4c51bf; --dark: #1e293b; --success: #38a169; }
        body { background: #f8fafc; margin: 0; font-family: sans-serif; }

        .header-mobile { display: none; position: fixed; top: 0; left: 0; right: 0; height: 60px; background: var(--dark); color: white; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 2000; }
        .main { padding: 25px; transition: 0.3s; }

        @media (max-width: 992px) {
            .header-mobile { display: flex; }
            .main { margin-left: 0 !important; padding: 80px 15px 100px 15px !important; }
            .sidebar { position: fixed; left: -260px; z-index: 3000; }
            .sidebar.active { left: 0; }
            .hide-mobile { display: none !important; }
            .grid-detalle { grid-template-columns: 1fr !important; }
            .desktop-table { display: none; }
            .mobile-history { display: flex !important; flex-direction: column; gap: 15px; }
        }

        /* Alertas */
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: bold; border: 1px solid; }
        .exito { background: #f0fff4; color: #22543d; border-color: #c6f6d5; }
        .error { background: #fff5f5; color: #c53030; border-color: #feb2b2; }

        /* Badges de Estado */
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
        .badge-pendiente { background: #fef3c7; color: #92400e; }
        .badge-surtido { background: #e0f2fe; color: #0369a1; }
        .badge-terminado { background: #dcfce7; color: #15803d; }

        /* Vista de Historial (Mobile) */
        .mobile-history { display: none; }
        .order-card { background: white; border-radius: 15px; padding: 20px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .card-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }

        /* Detalle */
        .card-detalle { background: #fff; border-radius: 15px; padding: 20px; border: 1px solid #e2e8f0; margin-bottom: 25px; }
        .grid-detalle { display: grid; grid-template-columns: 1fr 1.5fr; gap: 20px; }
        .table-mini { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .table-mini th { text-align: left; padding: 10px; background: #f8fafc; color: #64748b; }
        .table-mini td { padding: 10px; border-top: 1px solid #f1f5f9; }

        .search-box { width: 100%; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px; box-sizing: border-box; font-size: 1rem; }
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2500; }
        .overlay.active { display: block; }

        .btn-action { padding: 10px 15px; border-radius: 10px; font-weight: bold; text-decoration: none; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 5px; }
    </style>
</head>
<body>
    <div class="overlay" id="overlay" onclick="toggleMenu()"></div>

    <div class="header-mobile">
        <button onclick="toggleMenu()" style="background:none; border:none; color:white; font-size:1.5rem;"><i class="fas fa-bars"></i></button>
        <span style="font-weight: 900; letter-spacing: 1px;">HISTORIAL AHD</span>
        <i class="fas fa-history"></i>
    </div>

    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="hide-mobile" style="margin-bottom:20px;">
            <h1><i class="fas fa-history"></i> Historial de Producción</h1>
        </div>

        <?php if($mensaje) echo $mensaje; ?>

        <?php if ($detalle_id): ?>
        <div class="card-detalle">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
                <h3 style="margin:0;"><i class="fas fa-file-invoice"></i> Orden #<?php echo $detalle_id; ?></h3>
                <div style="display:flex; gap:8px;">
                    <a href="?exportar=excel&id=<?php echo $detalle_id; ?>" style="background:#1d6f42; color:white; padding:8px 12px; border-radius:8px; text-decoration:none; font-size:0.7rem; font-weight:bold;"><i class="fas fa-file-excel"></i></a>
                    <a href="generar_pdf_produccion.php?id=<?php echo $detalle_id; ?>" target="_blank" style="background:#2b6cb0; color:white; padding:8px 12px; border-radius:8px; text-decoration:none; font-size:0.7rem; font-weight:bold;"><i class="fas fa-industry"></i></a>
                    <a href="historial_ordenes.php" style="background:#e2e8f0; color:#475569; padding:8px 12px; border-radius:8px; text-decoration:none;"><i class="fas fa-times"></i></a>
                </div>
            </div>

            <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:20px; padding:12px; background:#f8fafc; border-radius:10px; border:1px solid #edf2f7;">
                <small style="width:100%; color:#64748b; font-weight:bold; margin-bottom:5px;">LISTAS DE COMPRA POR PROVEEDOR:</small>
                <?php 
                $prov_listados = [];
                foreach($insumos_detalle as $ins) {
                    $p_nombre = $ins['proveedor'] ?? 'Sin Proveedor';
                    if(!in_array($p_nombre, $prov_listados)) {
                        $prov_listados[] = $p_nombre;
                        echo '<a href="generar_pdf_lote.php?id='.$detalle_id.'&prov='.urlencode($p_nombre).'" target="_blank" class="badge" style="background:#fee2e2; color:#b91c1c; text-decoration:none; border:1px solid #fecaca;">
                                <i class="fas fa-file-pdf"></i> '.$p_nombre.'
                              </a>';
                    }
                }
                ?>
            </div>

            <div class="grid-detalle">
                <div>
                    <h4 style="color:#2b6cb0; margin-top:0;"><i class="fas fa-boxes"></i> Productos</h4>
                    <table class="table-mini">
                        <thead><tr><th>Producto</th><th style="text-align:right;">Lts</th></tr></thead>
                        <tbody>
                            <?php foreach($productos_detalle as $pd): ?>
                            <tr><td><?php echo htmlspecialchars($pd['nombre']); ?></td><td style="text-align:right;"><strong><?php echo number_format($pd['cantidad_litros'], 1); ?></strong></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div>
                    <h4 style="color:#4a5568; margin-top:0;"><i class="fas fa-flask"></i> Insumos</h4>
                    <table class="table-mini">
                        <thead><tr><th>Insumo / Proveedor</th><th style="text-align:right;">Cant.</th></tr></thead>
                        <tbody>
                            <?php 
                            $last_p = "";
                            foreach($insumos_detalle as $id): 
                                if($id['proveedor'] !== $last_p):
                                    $last_p = $id['proveedor'];
                            ?>
                                <tr style="background:#f1f5f9;"><td colspan="2" style="font-weight:bold; font-size:0.7rem; color:#475569;"><?php echo htmlspecialchars($last_p ?? 'Sin Proveedor'); ?></td></tr>
                            <?php endif; ?>
                            <tr>
                                <td style="padding-left:15px;"><?php echo htmlspecialchars($id['nombre']); ?></td>
                                <td style="text-align:right;"><strong><?php echo number_format($id['cantidad_usada'], 2); ?></strong> <small><?php echo $id['unidad_medida']; ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div style="position:relative;">
            <i class="fas fa-search" style="position:absolute; left:15px; top:18px; color:#a0aec0;"></i>
            <input type="text" id="historialSearch" onkeyup="filterHistory()" placeholder="Buscar folio o estado..." class="search-box" style="padding-left:45px;">
        </div>

        <div class="desktop-table card" style="padding:0; border-radius:15px; overflow:hidden; border: 1px solid #e2e8f0; background:white;">
            <table id="tableHistory" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:2px solid #edf2f7;">
                        <th style="padding:15px; text-align:left;">Folio</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Inversión</th>
                        <th style="text-align:right; padding-right:15px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ordenes as $o): ?>
                    <tr class="history-row" style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:15px;" class="folio"><strong>#<?php echo $o['id']; ?></strong></td>
                        <td><small><?php echo date('d/m/y H:i', strtotime($o['fecha_registro'])); ?></small></td>
                        <td class="status">
                            <?php 
                                $est = $o['estado'] ?? 'PENDIENTE';
                                $clase = 'badge-pendiente';
                                if($est == 'SURTIDO') $clase = 'badge-surtido';
                                if($est == 'TERMINADO') $clase = 'badge-terminado';
                            ?>
                            <span class="badge <?php echo $clase; ?>"><?php echo $est; ?></span>
                        </td>
                        <td><strong>$<?php echo number_format($o['costo_total_insumos'], 2); ?></strong></td>
                        <td style="text-align:right; padding-right:15px;">
                            <div style="display:flex; justify-content:flex-end; gap:8px;">
                                <?php if($est == 'PENDIENTE'): ?>
                                    <form method="POST" style="margin:0;"><input type="hidden" name="id_orden" value="<?php echo $o['id']; ?>"><button type="submit" name="recibir_insumos_orden" class="btn-action" style="background:var(--success); color:white; border:none; cursor:pointer;" onclick="return confirm('¿Cargar al stock?')"><i class="fas fa-truck-loading"></i></button></form>
                                <?php elseif($est == 'SURTIDO'): ?>
                                    <a href="finalizar_produccion.php?id=<?php echo $o['id']; ?>" class="btn-action" style="background:#3182ce; color:white;"><i class="fas fa-check-double"></i></a>
                                <?php endif; ?>
                                <a href="?ver=<?php echo $o['id']; ?>" class="btn-action" style="background:#f1f5f9; color:var(--dark);"><i class="fas fa-eye"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mobile-history">
            <?php foreach ($ordenes as $o): 
                $est = $o['estado'] ?? 'PENDIENTE';
                $clase = 'badge-pendiente';
                if($est == 'SURTIDO') $clase = 'badge-surtido';
                if($est == 'TERMINADO') $clase = 'badge-terminado';
            ?>
            <div class="order-card history-row">
                <div class="card-row">
                    <span class="folio" style="font-weight:900; font-size:1.1rem; color:var(--dark);">#<?php echo $o['id']; ?></span>
                    <span class="status badge <?php echo $clase; ?>"><?php echo $est; ?></span>
                </div>
                <div class="card-row">
                    <small style="color:#64748b;"><i class="far fa-calendar-alt"></i> <?php echo date('d/M/y H:i', strtotime($o['fecha_registro'])); ?></small>
                    <strong style="color:var(--dark);">$<?php echo number_format($o['costo_total_insumos'], 2); ?></strong>
                </div>
                <hr style="border:0; border-top:1px solid #f1f5f9; margin:15px 0;">
                <div style="display:flex; gap:10px;">
                    <a href="?ver=<?php echo $o['id']; ?>" class="btn-action" style="flex:1; justify-content:center; background:#f1f5f9; color:var(--dark);"><i class="fas fa-eye"></i> DETALLE</a>
                    <?php if($est == 'PENDIENTE'): ?>
                        <form method="POST" style="flex:1;"><input type="hidden" name="id_orden" value="<?php echo $o['id']; ?>"><button type="submit" name="recibir_insumos_orden" class="btn-action" style="width:100%; justify-content:center; background:var(--success); color:white; border:none;" onclick="return confirm('¿Recibir insumos?')"><i class="fas fa-truck-loading"></i> RECIBIR</button></form>
                    <?php elseif($est == 'SURTIDO'): ?>
                        <a href="finalizar_produccion.php?id=<?php echo $o['id']; ?>" class="btn-action" style="flex:1; justify-content:center; background:#3182ce; color:white;"><i class="fas fa-check-double"></i> FINALIZAR</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        function filterHistory() {
            let input = document.getElementById("historialSearch").value.toLowerCase();
            let rows = document.querySelectorAll(".history-row");
            rows.forEach(row => {
                let folio = row.querySelector(".folio").innerText.toLowerCase();
                let status = row.querySelector(".status").innerText.toLowerCase();
                row.style.display = (folio.includes(input) || status.includes(input)) ? "" : "none";
            });
        }

        function toggleMenu() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('active');
        }
    </script>
</body>
</html>