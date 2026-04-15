<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// --- 1. LÓGICA DE PROCESAMIENTO (GUARDAR / EDITAR / ELIMINAR) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $id = $_POST['cliente_id'] ?? null;
    $nombre = $_POST['nombre_completo'] ?? '';
    $email = $_POST['email'] ?? '';
    $tel = $_POST['telefono'] ?? '';
    $direccion = $_POST['direccion'] ?? '';
    $tipo = $_POST['tipo_cliente_id'] ?? '';
    $dias_credito = $_POST['dias_credito'] ?? 0;
    $limite_credito = $_POST['limite_credito'] ?? 0;

    try {
        if ($_POST['action'] == 'crear') {
            $sql = "INSERT INTO clientes (nombre_completo, email, telefono, direccion, tipo_cliente_id, dias_credito, limite_credito) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$nombre, $email, $tel, $direccion, $tipo, $dias_credito, $limite_credito]);
            header("Location: cartera.php?msj=Cliente creado con éxito");
        } elseif ($_POST['action'] == 'editar') {
            $sql = "UPDATE clientes SET nombre_completo=?, email=?, telefono=?, direccion=?, tipo_cliente_id=?, dias_credito=?, limite_credito=? WHERE id=?";
            $pdo->prepare($sql)->execute([$nombre, $email, $tel, $direccion, $tipo, $dias_credito, $limite_credito, $id]);
            header("Location: cartera.php?msj=Cliente actualizado con éxito");
        } elseif ($_POST['action'] == 'eliminar' && $id) {
            $sql = "DELETE FROM clientes WHERE id = ?";
            $pdo->prepare($sql)->execute([$id]);
            header("Location: cartera.php?msj=Cliente eliminado");
        }
        exit;
    } catch (Exception $e) {
        $error = "Error en la base de datos: " . $e->getMessage();
    }
}

// --- 2. CONSULTAS PARA LA VISTA ---
$tipos = $pdo->query("SELECT * FROM tipos_cliente")->fetchAll();

// Consulta principal: incluye el cálculo de saldo pendiente (deuda)
$sql = "SELECT c.*, tc.nombre as tipo_nombre, 
        (SELECT SUM(total) FROM pedidos WHERE cliente_id = c.id) as total_comprado,
        (SELECT SUM(total - monto_pagado) FROM pedidos WHERE cliente_id = c.id AND status_pago != 'Pagado') as saldo_deuda
        FROM clientes c 
        INNER JOIN tipos_cliente tc ON c.tipo_cliente_id = tc.id
        ORDER BY c.nombre_completo ASC";
$clientes = $pdo->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Clientes | AHD Clean</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        :root { --dark: #1e293b; --accent: #3b82f6; }
        body { background: #f8fafc; font-family: sans-serif; margin: 0; }
        .main { padding: 20px; transition: 0.3s; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; background: #f1f5f9; color: var(--dark); }
        
        .table-container { background: white; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 15px; text-align: left; font-size: 0.8rem; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; }
        .badge-deuda { background: #fee2e2; color: #ef4444; }
        
        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 20px; width: 90%; max-width: 550px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-size: 0.75rem; font-weight: bold; color: #64748b; margin-bottom: 5px; }
        input, select { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; box-sizing: border-box; }
        
        .btn-primary { background: var(--dark); color: white; border: none; padding: 12px 20px; border-radius: 10px; font-weight: bold; cursor: pointer; }
        .btn-secondary { background: #e2e8f0; color: #475569; border: none; padding: 12px 20px; border-radius: 10px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <h1><i class="fas fa-users"></i> Cartera de Clientes</h1>
            <button class="btn-primary" onclick="abrirModalCrear()">
                <i class="fas fa-plus"></i> Nuevo Cliente
            </button>
        </div>

        <?php if(isset($_GET['msj'])): ?>
            <div style="background:#dcfce7; color:#15803d; padding:15px; border-radius:12px; margin-bottom:20px; border:1px solid #bbf7d0;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['msj']); ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
                <div>
                    <small style="color:#64748b">Clientes Registrados</small>
                    <div style="font-size:1.4rem; font-weight:900"><?php echo count($clientes); ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color:#ef4444"><i class="fas fa-hand-holding-usd"></i></div>
                <div>
                    <small style="color:#64748b">Saldo Total en Calle</small>
                    <div style="font-size:1.4rem; font-weight:900">$<?php echo number_format(array_sum(array_column($clientes, 'saldo_deuda')), 2); ?></div>
                </div>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Condiciones</th>
                        <th>Saldo Pendiente</th>
                        <th style="text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($clientes as $c): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($c['nombre_completo']); ?></strong><br>
                            <small style="color:#94a3b8"><?php echo $c['tipo_nombre']; ?> | <?php echo $c['telefono']; ?></small>
                        </td>
                        <td>
                            <span class="badge" style="background:#f1f5f9; color:#475569;"><?php echo $c['dias_credito']; ?> días</span>
                            <small style="display:block; color:#94a3b8">Límite: $<?php echo number_format($c['limite_credito'], 0); ?></small>
                        </td>
                        <td>
                            <?php if($c['saldo_deuda'] > 0): ?>
                                <span class="badge badge-deuda">$<?php echo number_format($c['saldo_deuda'], 2); ?></span>
                            <?php else: ?>
                                <span style="color:#10b981; font-weight:bold;">$0.00</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <button onclick='abrirModalEditar(<?php echo json_encode($c); ?>)' style="background:none; border:none; color:var(--accent); cursor:pointer; font-size:1.1rem;"><i class="fas fa-edit"></i></button>
                            <button onclick="eliminarCliente(<?php echo $c['id']; ?>, '<?php echo $c['nombre_completo']; ?>')" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:1.1rem; margin-left:10px;"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="modalCliente" class="modal-overlay">
        <div class="modal-content">
            <h2 id="modalTitle" style="margin-top:0;">Cliente</h2>
            <form id="formCliente" method="POST" action="cartera.php">
                <input type="hidden" name="action" id="formAction">
                <input type="hidden" name="cliente_id" id="modal_id">

                <div class="form-group">
                    <label>NOMBRE COMPLETO / EMPRESA</label>
                    <input type="text" name="nombre_completo" id="modal_nombre" required>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>TELÉFONO</label>
                        <input type="text" name="telefono" id="modal_telefono">
                    </div>
                    <div class="form-group">
                        <label>EMAIL</label>
                        <input type="email" name="email" id="modal_email">
                    </div>
                </div>

                <div class="form-group">
                    <label>DIRECCIÓN</label>
                    <input type="text" name="direccion" id="modal_direccion">
                </div>

                <div style="background:#f8fafc; padding:20px; border-radius:12px; border:1px solid #e2e8f0; margin-bottom:20px;">
                    <p style="margin:0 0 15px 0; font-weight:bold; font-size:0.8rem;">PARÁMETROS COMERCIALES</p>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                        <div>
                            <label>DÍAS CRÉDITO</label>
                            <input type="number" name="dias_credito" id="modal_dias" value="0">
                        </div>
                        <div>
                            <label>LÍMITE CRÉDITO ($)</label>
                            <input type="number" name="limite_credito" id="modal_limite" value="0" step="0.01">
                        </div>
                    </div>
                    <label>NIVEL DE PRECIO (TIPO)</label>
                    <select name="tipo_cliente_id" id="modal_tipo">
                        <?php foreach($tipos as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo $t['nombre']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:flex; justify-content: flex-end; gap:10px;">
                    <button type="button" class="btn-secondary" onclick="cerrarModal()">Cancelar</button>
                    <button type="submit" class="btn-primary">Guardar Cliente</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('modalCliente');

        function abrirModalCrear() {
            document.getElementById('formCliente').reset();
            document.getElementById('modalTitle').innerText = 'Nuevo Cliente';
            document.getElementById('formAction').value = 'crear';
            modal.style.display = 'flex';
        }

        function abrirModalEditar(c) {
            document.getElementById('modalTitle').innerText = 'Editar Cliente';
            document.getElementById('formAction').value = 'editar';
            document.getElementById('modal_id').value = c.id;
            document.getElementById('modal_nombre').value = c.nombre_completo;
            document.getElementById('modal_telefono').value = c.telefono;
            document.getElementById('modal_email').value = c.email;
            document.getElementById('modal_direccion').value = c.direccion;
            document.getElementById('modal_tipo').value = c.tipo_cliente_id;
            document.getElementById('modal_dias').value = c.dias_credito;
            document.getElementById('modal_limite').value = c.limite_credito;
            modal.style.display = 'flex';
        }

        function cerrarModal() { modal.style.display = 'none'; }

        function eliminarCliente(id, nombre) {
            if(confirm(`¿Estás seguro de eliminar a ${nombre}?`)) {
                const f = document.createElement('form');
                f.method = 'POST';
                f.innerHTML = `<input type="hidden" name="action" value="eliminar"><input type="hidden" name="cliente_id" value="${id}">`;
                document.body.appendChild(f);
                f.submit();
            }
        }

        // Cerrar al hacer clic fuera del modal
        window.onclick = function(event) { if (event.target == modal) cerrarModal(); }
    </script>
</body>
</html>