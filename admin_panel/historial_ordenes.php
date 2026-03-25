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

    // Obtener Insumos con Proveedor
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
        $mensaje = "<div class='alert exito'>¡Insumos de la Orden #$id_orden cargados al stock correctamente!</div>";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $mensaje = "<div class='alert error'>Error: " . $e->getMessage() . "</div>";
    }
}

// --- 3. CONSULTA DE ÓRDENES Y DETALLES ---
$ordenes = $pdo->query("SELECT * FROM ordenes_produccion ORDER BY fecha_registro DESC")->fetchAll();
$detalle_id = $_GET['ver'] ?? null;
$insumos_detalle = [];

if ($detalle_id) {
    $stmt_i = $pdo->prepare("
        SELECT odi.*, i.nombre, i.unidad_medida, prov.nombre_empresa as proveedor 
        FROM orden_detalle_insumos odi 
        JOIN insumos i ON odi.id_insumo = i.id 
        LEFT JOIN proveedores prov ON i.id_proveedor = prov.id_proveedor
        WHERE odi.id_orden = ?
        ORDER BY prov.nombre_empresa ASC
    ");
    $stmt_i->execute([$detalle_id]);
    $insumos_detalle = $stmt_i->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial | AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .main { padding: 20px; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; }
        .exito { background: #c6f6d5; color: #22543d; border: 1px solid #38a169; }
        .error { background: #fed7d7; color: #822727; border: 1px solid #e53e3e; }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: bold; text-transform: uppercase; }
        .badge-pendiente { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; }
        .badge-surtido { background: #f0fff4; color: #2f855a; border: 1px solid #c6f6d5; }
        .card-detalle { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; background: #f8fafc; padding: 12px; font-size: 0.85rem; color: #64748b; border-bottom: 2px solid #edf2f7; }
        td { padding: 12px; border-bottom: 1px solid #edf2f7; font-size: 0.9rem; }
        .row-proveedor { background: #edf2f7; font-weight: bold; color: #4a5568; font-size: 0.8rem; }
        .btn-pdf-prov { background: #e53e3e; color: white; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 0.75rem; font-weight: bold; display: inline-flex; align-items: center; gap: 5px; transition: 0.3s; }
        .btn-pdf-prov:hover { background: #c53030; transform: translateY(-2px); }
        .btn-recibir { background: #28a745; color: white; border: none; padding: 6px 12px; border-radius: 5px; cursor: pointer; font-size: 0.8rem; font-weight: bold; }
        .search-box { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px; box-sizing: border-box; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <h1><i class="fas fa-history"></i> Historial de Producción</h1>
        <?php echo $mensaje; ?>

        <?php if ($detalle_id): ?>
        <div class="card-detalle">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3><i class="fas fa-file-invoice"></i> Requisiciones de Orden #<?php echo $detalle_id; ?></h3>
                <div style="display:flex; gap:10px;">
                    <a href="?exportar=excel&id=<?php echo $detalle_id; ?>" style="background:#1d6f42; color:white; padding:8px 12px; border-radius:6px; text-decoration:none; font-size:0.75rem; font-weight:bold;"><i class="fas fa-file-excel"></i> Excel Completo</a>
                    <a href="historial_ordenes.php" style="color:#a0aec0; margin-left:10px;"><i class="fas fa-times-circle fa-lg"></i></a>
                </div>
            </div>

            <p style="font-size: 0.85rem; color:#718096; margin-bottom:10px;">Descargar PDF individual para enviar a cada proveedor:</p>
            <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:30px; padding:15px; background:#f8fafc; border-radius:8px;">
                <?php 
                $prov_listados = [];
                foreach($insumos_detalle as $ins) {
                    $p_nombre = $ins['proveedor'] ?? 'Sin Proveedor';
                    if(!in_array($p_nombre, $prov_listados)) {
                        $prov_listados[] = $p_nombre;
                        echo '<a href="generar_pdf_lote.php?id='.$detalle_id.'&prov='.urlencode($p_nombre).'" target="_blank" class="btn-pdf-prov">
                                <i class="fas fa-file-pdf"></i> '.$p_nombre.'
                              </a>';
                    }
                }
                ?>
            </div>

            <h4 style="color:#4a5568;"><i class="fas fa-flask"></i> Detalle de Insumos</h4>
            <table>
                <thead><tr><th>Insumo</th><th style="text-align:right;">Cantidad</th></tr></thead>
                <tbody>
                    <?php 
                    $last_p = "";
                    foreach($insumos_detalle as $id): 
                        if($id['proveedor'] !== $last_p):
                            $last_p = $id['proveedor'];
                    ?>
                        <tr class="row-proveedor"><td colspan="2"><i class="fas fa-truck"></i> <?php echo htmlspecialchars($last_p ?? 'Sin Proveedor'); ?></td></tr>
                    <?php endif; ?>
                    <tr>
                        <td style="padding-left:25px;"><?php echo htmlspecialchars($id['nombre']); ?></td>
                        <td style="text-align:right;"><strong><?php echo number_format($id['cantidad_usada'], 3); ?></strong> <?php echo $id['unidad_medida']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <input type="text" id="historialSearch" onkeyup="filterHistory()" placeholder="Buscar por Folio o Estado..." class="search-box">

        <div class="card" style="border:1px solid #e2e8f0; border-radius:10px; overflow:hidden;">
            <table id="tableHistory">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Inversión</th>
                        <th style="text-align:right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ordenes as $o): ?>
                    <tr class="history-row">
                        <td class="folio"><strong>#<?php echo $o['id']; ?></strong></td>
                        <td><?php echo date('d/m/y H:i', strtotime($o['fecha_registro'])); ?></td>
                        <td class="status">
                            <span class="badge <?php echo (($o['estado'] ?? 'PENDIENTE') == 'PENDIENTE') ? 'badge-pendiente' : 'badge-surtido'; ?>">
                                <?php echo (($o['estado'] ?? 'PENDIENTE') == 'PENDIENTE') ? 'Pendiente' : 'Surtido'; ?>
                            </span>
                        </td>
                        <td>$<?php echo number_format($o['costo_total_insumos'], 2); ?></td>
                        <td style="text-align:right; display:flex; justify-content:flex-end; gap:10px;">
                            <?php if(($o['estado'] ?? 'PENDIENTE') == 'PENDIENTE'): ?>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="id_orden" value="<?php echo $o['id']; ?>">
                                    <button type="submit" name="recibir_insumos_orden" class="btn-recibir" onclick="return confirm('¿Confirmas la recepción física del material?')">
                                        <i class="fas fa-truck-loading"></i> Recibir
                                    </button>
                                </form>
                            <?php endif; ?>
                            <a href="?ver=<?php echo $o['id']; ?>" style="color:#4c51bf; font-weight:bold; text-decoration:none; margin-top:6px; font-size:0.85rem;">
                                <i class="fas fa-eye"></i> Detalle
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
    </script>
</body>
</html>