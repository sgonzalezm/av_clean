<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$id_orden = $_GET['id'] ?? null;
$mensaje = "";

// --- 1. PROCESAR LA RECEPCIÓN (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['finalizar_recepcion'])) {
    $id_orden_post = $_POST['id_orden_hidden'];
    try {
        $pdo->beginTransaction();
        
        $foto_db = null;
        if (!empty($_FILES['evidencia']['name'])) {
            $ruta_carpeta = "../uploads/recepciones/";
            if (!file_exists($ruta_carpeta)) mkdir($ruta_carpeta, 0777, true);
            
            $extension = strtolower(pathinfo($_FILES['evidencia']['name'], PATHINFO_EXTENSION));
            $nombre_archivo = "ORDEN_" . $id_orden_post . "_" . date("Ymd_His") . "." . $extension;
            if (move_uploaded_file($_FILES['evidencia']['tmp_name'], $ruta_carpeta . $nombre_archivo)) {
                $foto_db = $nombre_archivo;
            }
        }

        foreach ($_POST['recibido'] as $id_insumo => $cantidad_real) {
            $sqlStock = "UPDATE insumos SET stock_actual = COALESCE(stock_actual, 0) + ? WHERE id = ?";
            $pdo->prepare($sqlStock)->execute([$cantidad_real, $id_insumo]);
        }

        $sqlOrden = "UPDATE ordenes_produccion SET estado = 'SURTIDO', evidencia_url = ? WHERE id = ?";
        $pdo->prepare($sqlOrden)->execute([$foto_db, $id_orden_post]);

        $pdo->commit();
        header("Location: recepcion_stock.php?msj=Ok");
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $mensaje = "<div class='alert error'>Error: " . $e->getMessage() . "</div>";
    }
}

// --- 2. LÓGICA DE VISTA ---
if ($id_orden) {
    // VISTA: Formulario de conteo para una orden específica
    $stmt = $pdo->prepare("
        SELECT odi.*, i.nombre, i.unidad_medida, prov.nombre_empresa 
        FROM orden_detalle_insumos odi 
        JOIN insumos i ON odi.id_insumo = i.id 
        LEFT JOIN proveedores prov ON i.id_proveedor = prov.id_proveedor
        WHERE odi.id_orden = ?
    ");
    $stmt->execute([$id_orden]);
    $insumos = $stmt->fetchAll();
} else {
    // VISTA: Listado general (Hub) - Órdenes pendientes y completadas
    $ordenes_pendientes = $pdo->query("SELECT * FROM ordenes_produccion WHERE estado = 'PENDIENTE' ORDER BY id DESC")->fetchAll();
    $ordenes_surtidas = $pdo->query("SELECT * FROM ordenes_produccion WHERE estado = 'SURTIDO' ORDER BY id DESC LIMIT 10")->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Entrada Almacén | AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .recepcion-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .qty-input { width: 90px; padding: 8px; border: 2px solid #e2e8f0; border-radius: 6px; text-align: center; font-weight: bold; }
        .evidence-zone { border: 2px dashed #3b82f6; padding: 30px; border-radius: 10px; text-align: center; background: #f0f7ff; cursor: pointer; display: block; }
        .btn-accion { padding: 8px 15px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 5px; }
        .btn-recibir { background: #ed8936; color: white; }
        .btn-ver { background: #edf2f7; color: #4a5568; }
        .badge { padding: 4px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: bold; }
        .badge-pendiente { background: #fff5f5; color: #c53030; }
        .badge-surtido { background: #f0fff4; color: #2f855a; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; }
        .exito { background: #c6f6d5; color: #22543d; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header">
            <h1><i class="fas fa-boxes"></i> Entrada de Almacén</h1>
            <?php if($id_orden): ?>
                <a href="recepcion_stock.php" class="btn" style="background:#64748b; color:white; text-decoration:none;">Volver al Listado</a>
            <?php endif; ?>
        </div>

        <?php if(isset($_GET['msj'])) echo "<div class='alert exito'>¡Stock actualizado y evidencia guardada correctamente!</div>"; ?>
        <?php echo $mensaje; ?>

        <?php if(!$id_orden): ?>
            <div class="recepcion-card">
                <h3><i class="fas fa-clock"></i> Esperando Mercancía (Pendientes)</h3>
                <table style="width:100%; border-collapse:collapse; margin-top:15px;">
                    <thead>
                        <tr style="text-align:left; color:#64748b; font-size:0.85rem;">
                            <th>Folio</th>
                            <th>Fecha Orden</th>
                            <th>Inversión</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($ordenes_pendientes as $op): ?>
                        <tr style="border-top:1px solid #f1f5f9;">
                            <td style="padding:12px;"><strong>#<?php echo $op['id']; ?></strong></td>
                            <td><?php echo date('d/m/Y', strtotime($op['fecha_registro'])); ?></td>
                            <td>$<?php echo number_format($op['costo_total_insumos'], 2); ?></td>
                            <td>
                                <a href="?id=<?php echo $op['id']; ?>" class="btn-accion btn-recibir">
                                    <i class="fas fa-truck-loading"></i> Iniciar Recepción
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; if(empty($ordenes_pendientes)) echo "<tr><td colspan='4' style='padding:20px; text-align:center; color:#94a3b8;'>No hay recepciones pendientes.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>

            <div class="recepcion-card">
                <h3><i class="fas fa-check-circle"></i> Últimas Recepciones (Surtidas)</h3>
                <table style="width:100%; border-collapse:collapse; margin-top:15px;">
                    <tbody>
                        <?php foreach($ordenes_surtidas as $os): ?>
                        <tr style="border-top:1px solid #f1f5f9;">
                            <td style="padding:12px;"><strong>#<?php echo $os['id']; ?></strong></td>
                            <td style="color:#64748b;"><?php echo date('d/m/Y', strtotime($os['fecha_registro'])); ?></td>
                            <td><span class="badge badge-surtido">RECIBIDO</span></td>
                            <td style="text-align:right;">
                                <?php if($os['evidencia_url']): ?>
                                    <a href="../uploads/recepciones/<?php echo $os['evidencia_url']; ?>" target="_blank" class="btn-accion btn-ver" title="Ver Evidencia">
                                        <i class="fas fa-eye"></i> Ver Foto
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <form method="POST" enctype="multipart/form-data" class="recepcion-card">
                <input type="hidden" name="id_orden_hidden" value="<?php echo $id_orden; ?>">
                <input type="hidden" name="finalizar_recepcion" value="1">

                <h3>Confirmar Insumos - Orden #<?php echo $id_orden; ?></h3>
                <table style="width:100%; border-collapse:collapse; margin:20px 0;">
                    <thead>
                        <tr style="background:#f8fafc; text-align:left;">
                            <th style="padding:10px;">Insumo</th>
                            <th style="padding:10px; text-align:center;">Cant. Pedida</th>
                            <th style="padding:10px; text-align:center;">Cant. Real Recibida</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($insumos as $i): ?>
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:10px;">
                                <strong><?php echo $i['nombre']; ?></strong><br>
                                <small style="color:#94a3b8;"><?php echo $i['nombre_empresa']; ?></small>
                            </td>
                            <td style="text-align:center; color:#94a3b8;"><?php echo number_format($i['cantidad_usada'], 2); ?></td>
                            <td style="text-align:center;">
                                <input type="number" step="0.001" name="recibido[<?php echo $i['id_insumo']; ?>]" 
                                       value="<?php echo $i['cantidad_usada']; ?>" class="qty-input">
                                <small><?php echo $i['unidad_medida']; ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; align-items:end;">
                    <div>
                        <label><strong>Subir Evidencia (Foto de Remisión/Material):</strong></label>
                        <label for="evidencia" class="evidence-zone" id="drop-area">
                            <i class="fas fa-camera fa-2x"></i><br>
                            <span id="file-name">Clic para capturar o subir foto</span>
                            <input type="file" name="evidencia" id="evidencia" accept="image/*" style="display:none;" required onchange="validarFoto()">
                        </label>
                    </div>
                    <button type="submit" class="btn-guardar" style="background:#059669; padding:20px; font-size:1.1rem; width:100%;">
                        <i class="fas fa-check-double"></i> FINALIZAR Y CARGAR STOCK
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        function validarFoto() {
            const input = document.getElementById('evidencia');
            const label = document.getElementById('file-name');
            if(input.files[0]) {
                label.innerText = "✓ Archivo: " + input.files[0].name;
                document.getElementById('drop-area').style.background = "#f0fff4";
                document.getElementById('drop-area').style.borderColor = "#2f855a";
            }
        }
    </script>
</body>
</html>