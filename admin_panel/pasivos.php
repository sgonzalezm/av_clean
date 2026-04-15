<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// --- 1. PROCESAMIENTO: REGISTRAR PASIVO O ABONO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'crear') {
        $desc = $_POST['descripcion'];
        $cat = $_POST['categoria'];
        $monto = floatval($_POST['monto']);
        $vence = $_POST['fecha_vencimiento'];
        
        $sql = "INSERT INTO pasivos (descripcion, categoria, monto_total, fecha_vencimiento, usuario_id) VALUES (?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$desc, $cat, $monto, $vence, $_SESSION['admin_id']]);
        header("Location: pasivos.php?msj=Obligación registrada");
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] == 'abonar') {
        $id = $_POST['pasivo_id'];
        $abono = floatval($_POST['monto_abono']);
        
        $sql = "UPDATE pasivos SET 
                monto_pagado = monto_pagado + ?, 
                estatus = IF(monto_pagado + ? >= monto_total, 'Pagado', 'Parcial') 
                WHERE id = ?";
        $pdo->prepare($sql)->execute([$abono, $abono, $id]);
        header("Location: pasivos.php?msj=Pago actualizado");
        exit;
    }
}

// --- 2. CONSULTA DE DATOS ---
$sql = "SELECT * FROM pasivos WHERE estatus != 'Pagado' ORDER BY fecha_vencimiento ASC";
$pasivos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$total_deuda_fija = $pdo->query("SELECT SUM(monto_total - monto_pagado) FROM pasivos WHERE estatus != 'Pagado'")->fetchColumn() ?? 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Pasivos | AHD Clean</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        :root { --danger: #ef4444; --dark: #1e293b; --accent: #3b82f6; }
        body { background: #f8fafc; font-family: sans-serif; margin: 0; }
        .main { padding: 25px; transition: 0.3s; }
        
        .header-mobile { display: none; position: fixed; top: 0; left: 0; right: 0; height: 60px; background: var(--dark); color: white; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 2000; }

        .stat-card { background: white; padding: 20px; border-radius: 15px; border-left: 6px solid var(--danger); box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        
        .pasivo-item { background: white; border-radius: 15px; margin-bottom: 15px; border: 1px solid #e2e8f0; overflow: hidden; }
        .pasivo-header { padding: 15px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; }
        .pasivo-body { padding: 15px 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        
        .vencido { color: var(--danger); font-weight: bold; }
        .badge-cat { font-size: 0.65rem; padding: 3px 8px; border-radius: 5px; font-weight: bold; background: #e2e8f0; color: #475569; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 3000; align-items: center; justify-content: center; }
        .modal-content { background: white; width: 90%; max-width: 450px; padding: 25px; border-radius: 20px; }
        .form-input { width: 100%; padding: 12px; border: 1px solid #cbd5e0; border-radius: 10px; margin-top: 5px; box-sizing: border-box; }

        @media (max-width: 992px) {
            .header-mobile { display: flex; }
            .main { margin-left: 0 !important; padding: 80px 15px 120px 15px !important; }
            .sidebar { position: fixed; left: -260px; z-index: 2500; }
            .sidebar.active { left: 0; }
            .hide-mobile { display: none; }
        }
    </style>
</head>
<body>

    <div class="header-mobile">
        <button onclick="toggleMenu()" style="background:none; border:none; color:white; font-size:1.5rem;"><i class="fas fa-bars"></i></button>
        <span style="font-weight: 900;">AHD PASIVOS</span>
        <i class="fas fa-file-invoice-dollar"></i>
    </div>

    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header hide-mobile" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
            <div>
                <h1><i class="fas fa-university"></i> Cuentas por Pagar</h1>
                <p style="color:#64748b;">Gestión de costos fijos y servicios.</p>
            </div>
            <button onclick="abrirCrear()" style="background:var(--dark); color:white; padding:12px 20px; border:none; border-radius:10px; font-weight:bold; cursor:pointer;">
                <i class="fas fa-plus"></i> NUEVA OBLIGACIÓN
            </button>
        </div>

        <div class="stat-card">
            <div>
                <small style="color:#64748b; font-weight:bold;">PASIVO CIRCULANTE TOTAL</small>
                <div style="font-size: 2rem; font-weight: 900; color: var(--danger);">$<?php echo number_format($total_deuda_fija, 2); ?></div>
            </div>
            <i class="fas fa-exclamation-triangle fa-2x" style="color:#fee2e2;"></i>
        </div>

        <div class="listado">
            <?php foreach($pasivos as $p): 
                $hoy = date('Y-m-d');
                $is_vencido = ($p['fecha_vencimiento'] < $hoy);
            ?>
            <div class="pasivo-item">
                <div class="pasivo-header">
                    <div>
                        <span class="badge-cat"><?php echo $p['categoria']; ?></span>
                        <strong style="display:block; margin-top:5px;"><?php echo htmlspecialchars($p['descripcion']); ?></strong>
                    </div>
                    <div style="text-align:right;">
                        <small style="display:block; color:#94a3b8;">VENCE</small>
                        <span class="<?php echo $is_vencido ? 'vencido' : ''; ?>">
                            <?php echo date('d/m/Y', strtotime($p['fecha_vencimiento'])); ?>
                        </span>
                    </div>
                </div>
                <div class="pasivo-body">
                    <div>
                        <small style="display:block; color:#94a3b8;">SALDO</small>
                        <strong style="font-size:1.1rem; color:var(--danger);">$<?php echo number_format($p['monto_total'] - $p['monto_pagado'], 2); ?></strong>
                    </div>
                    <div style="text-align:right;">
                        <button onclick="abrirAbono(<?php echo $p['id']; ?>, '<?php echo $p['descripcion']; ?>', <?php echo $p['monto_total'] - $p['monto_pagado']; ?>)" 
                                style="background:var(--accent); color:white; border:none; padding:8px 15px; border-radius:8px; cursor:pointer;">
                            ABONAR
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="modalCrear" class="modal">
        <div class="modal-content">
            <h2 style="margin-top:0;">Nueva Obligación</h2>
            <form method="POST">
                <input type="hidden" name="action" value="crear">
                <label>Descripción</label>
                <input type="text" name="descripcion" class="form-input" required placeholder="Ej. Renta Bodega Abril">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:15px;">
                    <div>
                        <label>Categoría</label>
                        <select name="categoria" class="form-input">
                            <option>Renta</option><option>Luz</option><option>Agua</option>
                            <option>Sueldos</option><option>Impuestos</option><option>Otros</option>
                        </select>
                    </div>
                    <div>
                        <label>Monto</label>
                        <input type="number" step="0.01" name="monto" class="form-input" required>
                    </div>
                </div>
                <div style="margin-top:15px;">
                    <label>Fecha Vencimiento</label>
                    <input type="date" name="fecha_vencimiento" class="form-input" required>
                </div>
                <button type="submit" style="width:100%; background:var(--dark); color:white; padding:15px; border-radius:10px; border:none; margin-top:20px; font-weight:bold;">GUARDAR</button>
                <button type="button" onclick="cerrarModales()" style="width:100%; background:none; border:none; color:#94a3b8; margin-top:10px;">Cancelar</button>
            </form>
        </div>
    </div>

    <div id="modalAbono" class="modal">
        <div class="modal-content">
            <h2 style="margin-top:0;">Registrar Pago</h2>
            <p id="abono_desc" style="font-weight:bold; color:var(--accent);"></p>
            <form method="POST">
                <input type="hidden" name="action" value="abonar">
                <input type="hidden" name="pasivo_id" id="abono_id">
                <label>Monto a pagar</label>
                <input type="number" step="0.01" name="monto_abono" id="abono_input" class="form-input" required>
                <button type="submit" style="width:100%; background:var(--accent); color:white; padding:15px; border-radius:10px; border:none; margin-top:20px; font-weight:bold;">CONFIRMAR PAGO</button>
                <button type="button" onclick="cerrarModales()" style="width:100%; background:none; border:none; color:#94a3b8; margin-top:10px;">Cancelar</button>
            </form>
        </div>
    </div>

    <script>
        function toggleMenu() {
            document.querySelector('.sidebar').classList.toggle('active');
        }
        function abrirCrear() { document.getElementById('modalCrear').style.display = 'flex'; }
        function abrirAbono(id, desc, saldo) {
            document.getElementById('abono_id').value = id;
            document.getElementById('abono_desc').innerText = desc;
            document.getElementById('abono_input').value = saldo;
            document.getElementById('modalAbono').style.display = 'flex';
        }
        function cerrarModales() {
            document.querySelectorAll('.modal').forEach(m => m.style.display = 'none');
        }
    </script>
</body>
</html>