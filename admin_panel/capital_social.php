<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$mensaje = "";

// --- LÓGICA DE PROCESAMIENTO ---

// 1. Registro de Aportación
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_aportacion'])) {
    $socio_id = filter_var($_POST['socio_id'], FILTER_SANITIZE_NUMBER_INT);
    $monto = filter_var($_POST['monto'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $concepto = htmlspecialchars($_POST['concepto']);
    
    if ($monto > 0 && !empty($socio_id)) {
        $stmt = $pdo->prepare("INSERT INTO aportaciones (socio_id, monto, concepto, fecha) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$socio_id, $monto, $concepto]);
        $mensaje = "Aportación registrada correctamente.";
    }
}

// 2. Registro de Socio
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_socio'])) {
    $nombre = htmlspecialchars($_POST['nombre_socio']);
    $porcentaje = filter_var($_POST['porcentaje'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    
    $stmt = $pdo->prepare("INSERT INTO socios (nombre, porcentaje_comprometido) VALUES (?, ?)");
    $stmt->execute([$nombre, $porcentaje]);
    $mensaje = "Socio agregado exitosamente.";
}

// --- CONSULTAS ---
$total_capital = $pdo->query("SELECT SUM(monto) FROM aportaciones")->fetchColumn() ?: 0;
$historial = $pdo->query("SELECT a.fecha, a.monto, a.concepto, s.nombre 
                          FROM aportaciones a 
                          JOIN socios s ON a.socio_id = s.id 
                          ORDER BY a.fecha DESC")->fetchAll();
$socios = $pdo->query("SELECT id, nombre FROM socios")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Capital Social | AHD Clean</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 25px; border-radius: 12px; width: 90%; max-width: 450px; position: relative; }
        .close { position: absolute; top: 15px; right: 20px; cursor: pointer; font-size: 1.5rem; }
        .btn-secundario { background: #718096; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold; cursor: pointer; border: none; }
        .btn-secundario:hover { background: #4a5568; }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <div class="header">
            <div>
                <h1><i class="fas fa-hand-holding-usd"></i> Capital Social</h1>
                <p>Gestión de socios y movimientos financieros.</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <button class="btn-secundario" onclick="toggleModal('modalSocio')"><i class="fas fa-user-plus"></i> Socio</button>
                <button class="btn-principal" onclick="toggleModal('modalAportacion')"><i class="fas fa-plus"></i> Aportación</button>
            </div>
        </div>

        <?php if($mensaje) echo "<div style='background:#c6f6d5; padding:15px; border-radius:8px; margin-bottom:20px;'>$mensaje</div>"; ?>

        <div class="metricas-grid">
            <div class="card" style="border-left: 5px solid #48bb78;">
                <i class="fas fa-wallet fa-2x" style="color: #48bb78; margin-bottom: 10px;"></i>
                <h3 style="margin: 0;">Capital Total</h3>
                <p style="font-size: 1.8rem; font-weight: bold; margin: 5px 0;">$<?php echo number_format($total_capital, 2); ?></p>
            </div>
        </div>

        <div style="background: white; padding: 25px; border-radius: 12px; margin-top: 25px; border: 1px solid #e2e8f0;">
            <h2>Historial de Aportaciones</h2>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #edf2f7; text-align: left;">
                            <th style="padding: 12px; color: #718096;">Fecha</th>
                            <th style="padding: 12px; color: #718096;">Socio</th>
                            <th style="padding: 12px; color: #718096;">Concepto</th>
                            <th style="padding: 12px; color: #718096; text-align: right;">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historial as $fila): ?>
                        <tr style="border-bottom: 1px solid #edf2f7;">
                            <td style="padding: 12px;"><?php echo date('d/m/Y', strtotime($fila['fecha'])); ?></td>
                            <td style="padding: 12px; font-weight: 600;"><?php echo htmlspecialchars($fila['nombre']); ?></td>
                            <td style="padding: 12px;"><?php echo htmlspecialchars($fila['concepto']); ?></td>
                            <td style="padding: 12px; text-align: right; font-weight: bold; color: #3182ce;">$<?php echo number_format($fila['monto'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="modalAportacion" class="modal">
        <div class="modal-content">
            <span class="close" onclick="toggleModal('modalAportacion')">&times;</span>
            <h2>Nueva Aportación</h2>
            <form method="POST">
                <select name="socio_id" style="width:100%; padding:10px; margin-bottom:10px;" required>
                    <?php foreach($socios as $s): ?><option value="<?=$s['id']?>"><?=$s['nombre']?></option><?php endforeach; ?>
                </select>
                <input type="number" name="monto" placeholder="Monto ($)" style="width:100%; padding:10px; margin-bottom:10px;" required>
                <input type="text" name="concepto" placeholder="Concepto" style="width:100%; padding:10px; margin-bottom:10px;" required>
                <button type="submit" name="registrar_aportacion" class="btn-principal" style="width:100%;">Guardar</button>
            </form>
        </div>
    </div>

    <div id="modalSocio" class="modal">
        <div class="modal-content">
            <span class="close" onclick="toggleModal('modalSocio')">&times;</span>
            <h2>Registrar Socio</h2>
            <form method="POST">
                <input type="text" name="nombre_socio" placeholder="Nombre completo" style="width:100%; padding:10px; margin-bottom:10px;" required>
                <input type="number" name="porcentaje" placeholder="% Participación" style="width:100%; padding:10px; margin-bottom:10px;" required>
                <button type="submit" name="registrar_socio" class="btn-secundario" style="width:100%;">Guardar Socio</button>
            </form>
        </div>
    </div>

    <script src="../js/admin.js"></script>
    <script>
        function toggleSidebar() { document.querySelector('.sidebar').classList.toggle('active'); }
        function toggleModal(id) { 
            const m = document.getElementById(id);
            m.style.display = (m.style.display === 'flex') ? 'none' : 'flex';
        }
    </script>
</body>
</html>