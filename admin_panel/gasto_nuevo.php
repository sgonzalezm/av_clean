<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// --- 1. PROCESAMIENTO DEL FORMULARIO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['btn_guardar'])) {
    try {
        $desc = $_POST['descripcion'];
        $cat = $_POST['categoria'];
        $monto = floatval($_POST['monto']);
        $u_id = $_SESSION['admin_id'];

        if($monto > 0){
            $ins = $pdo->prepare("INSERT INTO gastos (descripcion, categoria, monto, usuario_id, fecha_gasto) VALUES (?, ?, ?, ?, NOW())");
            $ins->execute([$desc, $cat, $monto, $u_id]);
            header("Location: gastos.php?msj=Gasto registrado");
            exit;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// --- 2. LÓGICA DE ELIMINACIÓN ---
if (isset($_GET['eliminar'])) {
    $pdo->prepare("DELETE FROM gastos WHERE id = ?")->execute([$_GET['eliminar']]);
    header("Location: gastos.php?msj=Registro eliminado");
    exit;
}

// --- 3. CONSULTA DE DATOS ---
$gastos = $pdo->query("SELECT g.*, u.nombre as admin_nombre FROM gastos g 
                       LEFT JOIN usuarios_admin u ON g.usuario_id = u.id 
                       ORDER BY g.fecha_gasto DESC")->fetchAll();
$total_egresos = array_sum(array_column($gastos, 'monto'));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Gastos | AHD Clean</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        :root { --danger: #ef4444; --dark: #1e293b; --accent: #10b981; }
        body { background: #f8fafc; margin: 0; font-family: sans-serif; }

        /* Mobile Header */
        .header-mobile { display: none; position: fixed; top: 0; left: 0; right: 0; height: 60px; background: var(--dark); color: white; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 2000; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
        
        .main { padding: 25px; transition: 0.3s; }

        /* Card de Total */
        .card-total { background: white; padding: 20px; border-radius: 15px; border-left: 6px solid var(--danger); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        
        /* Botón Flotante Mobile */
        .btn-add-float { display: none; position: fixed; bottom: 30px; right: 20px; width: 60px; height: 60px; background: var(--dark); color: white; border-radius: 50%; border: none; font-size: 1.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.3); z-index: 1000; align-items: center; justify-content: center; }

        /* Tablas y Cards */
        .desktop-table { background: white; border-radius: 15px; overflow: hidden; border: 1px solid #e2e8f0; }
        .desktop-table table { width: 100%; border-collapse: collapse; }
        .desktop-table th { background: #f8fafc; padding: 15px; text-align: left; color: #64748b; font-size: 0.8rem; text-transform: uppercase; }
        .desktop-table td { padding: 15px; border-top: 1px solid #f1f5f9; }

        .mobile-cards { display: none; flex-direction: column; gap: 12px; }
        .expense-card { background: white; padding: 20px; border-radius: 15px; border: 1px solid #e2e8f0; position: relative; }

        /* Badges */
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: bold; }
        .bg-insumo { background: #dcfce7; color: #166534; }
        .bg-herramienta { background: #fef9c3; color: #854d0e; }
        .bg-otros { background: #f1f5f9; color: #475569; }

        /* Modal UX */
        .modal { display: none; position: fixed; z-index: 3000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); }
        .modal-content { background: white; width: 90%; max-width: 450px; margin: 15% auto; padding: 30px; border-radius: 20px; position: relative; }
        .form-input { width: 100%; padding: 12px; border: 1px solid #cbd5e0; border-radius: 10px; box-sizing: border-box; font-size: 1rem; margin-top: 5px; }

        @media (max-width: 992px) {
            .header-mobile { display: flex; }
            .main { margin-left: 0 !important; padding: 80px 15px 120px 15px !important; }
            .desktop-table { display: none; }
            .mobile-cards { display: flex; }
            .btn-add-float { display: flex; }
            .sidebar { position: fixed; left: -260px; z-index: 2500; }
            .sidebar.active { left: 0; }
            .hide-mobile { display: none !important; }
            .modal-content { margin: 10% auto; }
        }

        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2200; }
        .overlay.active { display: block; }
    </style>
</head>
<body>
    <div class="overlay" id="overlay" onclick="toggleMenu()"></div>

    <div class="header-mobile">
        <button onclick="toggleMenu()" style="background:none; border:none; color:white; font-size:1.5rem;"><i class="fas fa-bars"></i></button>
        <span style="font-weight: 900; letter-spacing: 1px;">AHD GASTOS</span>
        <i class="fas fa-wallet"></i>
    </div>

    <?php include 'sidebar.php'; ?>

    <button class="btn-add-float" onclick="toggleModal(true)"><i class="fas fa-plus"></i></button>

    <div class="main">
        <div class="header hide-mobile" style="margin-bottom:25px;">
            <h1><i class="fas fa-money-bill-wave"></i> Control de Gastos</h1>
            <p style="color: #64748b;">Egresos operativos de AHD Clean</p>
        </div>

        <div class="card-total">
            <div>
                <small style="font-weight:bold; color:#64748b; text-transform:uppercase; letter-spacing:1px;">Salida de Capital</small>
                <div style="font-size: 2rem; font-weight: 900; color: var(--danger);">$<?php echo number_format($total_egresos, 2); ?></div>
            </div>
            <button class="btn hide-mobile" onclick="toggleModal(true)" style="background:var(--dark); color:white; padding:15px 25px; border-radius:12px; border:none; font-weight:bold; cursor:pointer;">
                <i class="fas fa-plus"></i> NUEVO REGISTRO
            </button>
        </div>

        <div class="desktop-table">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Descripción</th>
                        <th>Categoría</th>
                        <th>Monto</th>
                        <th>Responsable</th>
                        <th style="text-align:right;">Eliminar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($gastos as $g): ?>
                    <tr class="expense-row">
                        <td><small><?php echo date("d/m/y H:i", strtotime($g['fecha_gasto'])); ?></small></td>
                        <td><strong><?php echo htmlspecialchars($g['descripcion']); ?></strong></td>
                        <td>
                            <span class="badge <?php echo ($g['categoria'] == 'Herramienta') ? 'bg-herramienta' : ($g['categoria'] == 'Insumo Operativo' ? 'bg-insumo' : 'bg-otros'); ?>">
                                <?php echo $g['categoria']; ?>
                            </span>
                        </td>
                        <td style="color: var(--danger); font-weight: bold;">-$<?php echo number_format($g['monto'], 2); ?></td>
                        <td><small><?php echo $g['admin_nombre']; ?></small></td>
                        <td style="text-align:right;">
                            <a href="?eliminar=<?php echo $g['id']; ?>" onclick="return confirm('¿Eliminar gasto?')" style="color:#cbd5e1;"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mobile-cards">
            <?php foreach($gastos as $g): ?>
            <div class="expense-card expense-row">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <strong style="display:block; font-size:1.1rem; color:var(--dark);"><?php echo htmlspecialchars($g['descripcion']); ?></strong>
                        <small style="color:#64748b; font-weight:bold;"><?php echo date("d M, Y", strtotime($g['fecha_gasto'])); ?></small>
                    </div>
                    <div style="text-align:right;">
                        <div style="color:var(--danger); font-weight:900; font-size:1.2rem;">-$<?php echo number_format($g['monto'], 2); ?></div>
                        <span class="badge <?php echo ($g['categoria'] == 'Herramienta') ? 'bg-herramienta' : 'bg-insumo'; ?>"><?php echo $g['categoria']; ?></span>
                    </div>
                </div>
                <div style="margin-top:10px; display:flex; justify-content:space-between; align-items:center;">
                    <small style="color:#94a3b8;"><i class="fas fa-user-edit"></i> <?php echo $g['admin_nombre']; ?></small>
                    <a href="?eliminar=<?php echo $g['id']; ?>" onclick="return confirm('¿Eliminar gasto?')" style="color:#cbd5e1; padding:5px;"><i class="fas fa-trash"></i></a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="modalGasto" class="modal">
        <div class="modal-content">
            <h2 style="margin-top:0; color:var(--dark); font-weight:900;"><i class="fas fa-file-invoice-dollar"></i> Nuevo Gasto</h2>
            <form method="POST">
                <div style="margin-bottom:15px;">
                    <label style="font-size:0.75rem; font-weight:bold; color:#64748b;">CONCEPTO DEL GASTO</label>
                    <input type="text" name="descripcion" class="form-input" required placeholder="Ej. Reparación de bomba">
                </div>
                <div style="margin-bottom:15px;">
                    <label style="font-size:0.75rem; font-weight:bold; color:#64748b;">CATEGORÍA</label>
                    <select name="categoria" class="form-input">
                        <option>Insumo Operativo</option>
                        <option>Herramienta</option>
                        <option>Consumible</option>
                        <option>Materia Prima</option>
                        <option>Mantenimiento</option>
                    </select>
                </div>
                <div style="margin-bottom:25px;">
                    <label style="font-size:0.75rem; font-weight:bold; color:#64748b;">MONTO TOTAL ($)</label>
                    <input type="number" step="0.01" 
                           name="monto" 
                           class="form-input" 
                           required 
                           placeholder="0.00" 
                           inputmode="decimal"
                           onfocus="if(this.value=='') this.select();">
                </div>
                <button type="submit" name="btn_guardar" style="width:100%; background:var(--danger); color:white; border:none; padding:18px; border-radius:12px; font-weight:900; font-size:1.1rem; cursor:pointer; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);">
                    GUARDAR EGRESO
                </button>
                <button type="button" onclick="toggleModal(false)" style="width:100%; margin-top:10px; background:none; border:none; color:#94a3b8; cursor:pointer; font-weight:bold;">
                    Cancelar
                </button>
            </form>
        </div>
    </div>

    <script>
        function toggleModal(show) {
            document.getElementById('modalGasto').style.display = show ? 'block' : 'none';
        }

        function toggleMenu() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('active');
        }

        window.onclick = function(event) {
            let modal = document.getElementById('modalGasto');
            if (event.target == modal) toggleModal(false);
        }
    </script>
</body>
</html>