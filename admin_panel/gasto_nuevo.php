<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// 1. PROCESAMIENTO DEL FORMULARIO (Si se envía el modal)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['btn_guardar'])) {
    try {
        $desc = $_POST['descripcion'];
        $cat = $_POST['categoria'];
        $monto = $_POST['monto'];
        $u_id = $_SESSION['admin_id'];

        $ins = $pdo->prepare("INSERT INTO gastos (descripcion, categoria, monto, usuario_id) VALUES (?, ?, ?, ?)");
        $ins->execute([$desc, $cat, $monto, $u_id]);
        header("Location: gastos.php?msj=Gasto registrado");
        exit;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// 2. LÓGICA DE ELIMINACIÓN
if (isset($_GET['eliminar'])) {
    $pdo->prepare("DELETE FROM gastos WHERE id = ?")->execute([$_GET['eliminar']]);
    header("Location: gastos.php?msj=Registro eliminado");
    exit;
}

// 3. CONSULTA DE DATOS
$gastos = $pdo->query("SELECT g.*, u.nombre as admin_nombre FROM gastos g 
                       LEFT JOIN usuarios_admin u ON g.usuario_id = u.id 
                       ORDER BY g.fecha_gasto DESC")->fetchAll();
$total_egresos = array_sum(array_column($gastos, 'monto'));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Administración de Gastos | AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .card-total { background: #fff; padding: 15px 25px; border-radius: 10px; border-left: 5px solid #ef4444; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        
        /* Estilos de la Tabla */
        .table-main { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; }
        .table-main th { background: #f8fafc; padding: 12px; text-align: left; color: #64748b; font-size: 0.8rem; }
        .table-main td { padding: 12px; border-top: 1px solid #f1f5f9; }

        /* Estilos del Modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; width: 400px; margin: 10% auto; padding: 25px; border-radius: 12px; position: relative; }
        .close-btn { position: absolute; right: 20px; top: 15px; cursor: pointer; font-size: 1.5rem; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-input { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
        
        .btn-add { background: #1e293b; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; }
        .btn-submit { background: #ef4444; color: white; width: 100%; border: none; padding: 12px; border-radius: 8px; cursor: pointer; font-weight: bold; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; }
        .bg-insumo { background: #dcfce7; color: #166534; }
        .bg-herramienta { background: #fef9c3; color: #854d0e; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header-flex">
            <div>
                <h1>Gastos y Egresos</h1>
                <p style="color: #64748b;">Control operativo de AHD Clean</p>
            </div>
            <div class="card-total">
                <small>EGRESOS TOTALES</small>
                <div style="font-size: 1.5rem; font-weight: 900; color: #ef4444;">$<?php echo number_format($total_egresos, 2); ?></div>
            </div>
        </div>

        <div style="margin-bottom: 20px;">
            <button class="btn-add" onclick="toggleModal(true)">
                <i class="fas fa-plus"></i> Registrar Nuevo Gasto
            </button>
        </div>

        <table class="table-main">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Descripción</th>
                    <th>Categoría</th>
                    <th>Monto</th>
                    <th>Registró</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($gastos as $g): ?>
                <tr>
                    <td><?php echo date("d/m/y H:i", strtotime($g['fecha_gasto'])); ?></td>
                    <td><strong><?php echo htmlspecialchars($g['descripcion']); ?></strong></td>
                    <td>
                        <span class="badge <?php echo ($g['categoria'] == 'Herramienta') ? 'bg-herramienta' : 'bg-insumo'; ?>">
                            <?php echo $g['categoria']; ?>
                        </span>
                    </td>
                    <td style="color: #ef4444; font-weight: bold;">-$<?php echo number_format($g['monto'], 2); ?></td>
                    <td><small><?php echo $g['admin_nombre']; ?></small></td>
                    <td>
                        <a href="?eliminar=<?php echo $g['id']; ?>" onclick="return confirm('¿Eliminar gasto?')" style="color:#cbd5e1;"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="modalGasto" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="toggleModal(false)">&times;</span>
            <h2 style="margin-top:0;">Nuevo Gasto</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Descripción</label>
                    <input type="text" name="descripcion" class="form-input" required placeholder="¿En qué se gastó?">
                </div>
                <div class="form-group">
                    <label>Categoría</label>
                    <select name="categoria" class="form-input">
                        <option>Insumo Operativo</option>
                        <option>Herramienta</option>
                        <option>Consumible</option>
                        <option>Materia Prima</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Monto ($)</label>
                    <input type="number" step="0.01" name="monto" class="form-input" required>
                </div>
                <button type="submit" name="btn_guardar" class="btn-submit">GUARDAR GASTO</button>
            </form>
        </div>
    </div>

    <script>
        function toggleModal(show) {
            document.getElementById('modalGasto').style.display = show ? 'block' : 'none';
        }

        // Cerrar si hacen clic fuera del cuadro blanco
        window.onclick = function(event) {
            let modal = document.getElementById('modalGasto');
            if (event.target == modal) toggleModal(false);
        }
    </script>
</body>
</html>