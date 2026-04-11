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
            $cantidad_real = floatval($cantidad_real);
            if($cantidad_real > 0) {
                $sqlStock = "UPDATE insumos SET stock_actual = COALESCE(stock_actual, 0) + ? WHERE id = ?";
                $pdo->prepare($sqlStock)->execute([$cantidad_real, $id_insumo]);
            }
        }

        // Actualizamos a 'SURTIDO' (o 'RECIBIDO' según tu flujo)
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
    $ordenes_pendientes = $pdo->query("SELECT * FROM ordenes_produccion WHERE estado = 'PENDIENTE' ORDER BY id DESC")->fetchAll();
    $ordenes_surtidas = $pdo->query("SELECT * FROM ordenes_produccion WHERE estado = 'SURTIDO' ORDER BY id DESC LIMIT 8")->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Recepción Stock | AHD Clean</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        :root { --accent: #ed8936; --dark: #1e293b; --success: #059669; }
        body { background: #f8fafc; margin: 0; font-family: sans-serif; }

        .header-mobile { display: none; position: fixed; top: 0; left: 0; right: 0; height: 60px; background: var(--dark); color: white; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 2000; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
        .main { padding: 25px; transition: 0.3s; }

        @media (max-width: 992px) {
            .header-mobile { display: flex; }
            .main { margin-left: 0 !important; padding: 80px 15px 120px 15px !important; }
            .sidebar { position: fixed; left: -260px; z-index: 3000; }
            .sidebar.active { left: 0; }
            .hide-mobile { display: none !important; }
        }

        /* Cards de Órdenes */
        .hub-card { background: white; border-radius: 15px; padding: 20px; border: 1px solid #e2e8f0; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
        .folio-info strong { font-size: 1.2rem; display: block; color: var(--dark); }
        .folio-info small { color: #64748b; font-weight: bold; }

        /* Formulario de Recepción */
        .insumo-item { background: white; padding: 20px; border-radius: 15px; border: 1px solid #e2e8f0; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; }
        .qty-input { width: 90px; height: 50px; text-align: center; font-size: 1.2rem; border: 2px solid #cbd5e1; border-radius: 10px; font-weight: 800; }
        .qty-input:focus { border-color: var(--accent); outline: none; background: #fffaf0; }

        /* Evidencia Zone */
        .evidence-zone { border: 2px dashed var(--accent); padding: 30px; border-radius: 15px; text-align: center; background: #fffaf0; cursor: pointer; display: block; transition: 0.3s; margin-top: 20px; }
        .evidence-zone i { font-size: 2.5rem; color: var(--accent); margin-bottom: 10px; }
        
        .btn-recibir-big { background: var(--success); color: white; width: 100%; padding: 20px; border: none; border-radius: 15px; font-weight: 900; font-size: 1.1rem; cursor: pointer; margin-top: 20px; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3); }
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2500; }
        .overlay.active { display: block; }
    </style>
</head>
<body>
    <div class="overlay" id="overlay" onclick="toggleMenu()"></div>

    <div class="header-mobile">
        <button onclick="toggleMenu()" style="background:none; border:none; color:white; font-size:1.5rem;"><i class="fas fa-bars"></i></button>
        <span style="font-weight: 900; letter-spacing: 1px;">ALMACÉN AHD</span>
        <i class="fas fa-boxes"></i>
    </div>

    <?php include 'sidebar.php'; ?>

    <div class="main">
        <?php if(!$id_orden): ?>
            <div class="hide-mobile" style="margin-bottom:20px;">
                <h1><i class="fas fa-truck-loading"></i> Recepción de Mercancía</h1>
            </div>

            <?php if(isset($_GET['msj'])) echo "<div style='background:#dcfce7; color:#15803d; padding:15px; border-radius:12px; margin-bottom:20px; font-weight:bold;'><i class='fas fa-check-circle'></i> ¡Stock actualizado y evidencia guardada!</div>"; ?>

            <h3 style="color:#64748b; font-size:0.9rem; text-transform:uppercase; margin-bottom:15px;">Órdenes Pendientes de Ingreso</h3>
            <?php foreach($ordenes_pendientes as $op): ?>
                <div class="hub-card">
                    <div class="folio-info">
                        <strong>ORDEN #<?php echo $op['id']; ?></strong>
                        <small><?php echo date('d/M/y', strtotime($op['fecha_registro'])); ?> - $<?php echo number_format($op['costo_total_insumos'], 2); ?></small>
                    </div>
                    <a href="?id=<?php echo $op['id']; ?>" style="background:var(--accent); color:white; padding:12px 20px; border-radius:10px; text-decoration:none; font-weight:bold; font-size:0.85rem;">
                        RECIBIR <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            <?php endforeach; if(empty($ordenes_pendientes)) echo "<p style='text-align:center; padding:40px; color:#94a3b8;'>No hay entregas pendientes hoy.</p>"; ?>

            <h3 style="color:#64748b; font-size:0.9rem; text-transform:uppercase; margin-top:30px; margin-bottom:15px;">Recibidos Recientemente</h3>
            <div style="opacity: 0.7;">
                <?php foreach($ordenes_surtidas as $os): ?>
                    <div class="hub-card" style="padding: 12px 20px;">
                        <span style="font-weight:bold;">#<?php echo $os['id']; ?></span>
                        <span style="font-size:0.8rem;"><?php echo date('d/m/Y', strtotime($os['fecha_registro'])); ?></span>
                        <?php if($os['evidencia_url']): ?>
                            <a href="../uploads/recepciones/<?php echo $os['evidencia_url']; ?>" target="_blank" style="color:var(--accent);"><i class="fas fa-image"></i> Ver Foto</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h1 style="margin:0;">Recibiendo #<?php echo $id_orden; ?></h1>
                <a href="recepcion_stock.php" style="color:#64748b; font-weight:bold;"><i class="fas fa-times"></i> Salir</a>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id_orden_hidden" value="<?php echo $id_orden; ?>">
                <input type="hidden" name="finalizar_recepcion" value="1">

                <div class="insumos-lista">
                    <?php foreach($insumos as $i): ?>
                        <div class="insumo-item">
                            <div style="flex:1;">
                                <strong style="display:block; font-size:1.1rem; color:var(--dark);"><?php echo htmlspecialchars($i['nombre']); ?></strong>
                                <small style="color:#64748b; font-weight:bold;"><?php echo htmlspecialchars($i['nombre_empresa'] ?: 'Proveedor Genérico'); ?></small><br>
                                <span style="font-size:0.75rem; color:#94a3b8;">Pedido: <?php echo number_format($i['cantidad_usada'], 2); ?> <?php echo $i['unidad_medida']; ?></span>
                            </div>
                            <div style="text-align:right;">
                                <input type="number" step="0.001" 
                                       name="recibido[<?php echo $i['id_insumo']; ?>]" 
                                       value="" 
                                       placeholder="<?php echo (float)$i['cantidad_usada']; ?>"
                                       class="qty-input"
                                       inputmode="decimal"
                                       onfocus="if(this.value=='') this.select();">
                                <div style="font-size:0.7rem; font-weight:bold; color:var(--accent); margin-top:5px;"><?php echo $i['unidad_medida']; ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="evidencia-container">
                    <label style="font-weight:bold; color:var(--dark); display:block; margin-top:20px;">FOTO DE REMISIÓN O MATERIAL:</label>
                    <label for="evidencia" class="evidence-zone" id="drop-area">
                        <i class="fas fa-camera"></i><br>
                        <strong id="file-name">TAP PARA CAPTURAR FOTO</strong>
                        <input type="file" name="evidencia" id="evidencia" accept="image/*" capture="environment" style="display:none;" required onchange="validarFoto()">
                    </label>
                </div>

                <button type="submit" class="btn-recibir-big">
                    <i class="fas fa-check-double"></i> FINALIZAR Y CARGAR STOCK
                </button>
            </form>
        <?php endif; ?>
    </div>

    <script>
        function validarFoto() {
            const input = document.getElementById('evidencia');
            const label = document.getElementById('file-name');
            const zone = document.getElementById('drop-area');
            if(input.files[0]) {
                label.innerText = "✓ " + input.files[0].name;
                zone.style.background = "#f0fff4";
                zone.style.borderColor = "#059669";
                zone.style.color = "#059669";
            }
        }

        function toggleMenu() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('active');
        }
    </script>
</body>
</html>