<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// Procesar Formulario de Guardar/Editar/Eliminar
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $id = $_POST['cliente_id'] ?? null;
    
    // Variables para crear/editar (solo se usan si no es eliminar)
    $nombre = $_POST['nombre_completo'] ?? '';
    $email = $_POST['email'] ?? '';
    $tel = $_POST['telefono'] ?? '';
    $direccion = $_POST['direccion'] ?? '';
    $tipo = $_POST['tipo_cliente_id'] ?? '';

    try {
        if ($_POST['action'] == 'crear') {
            $sql = "INSERT INTO clientes (nombre_completo, email, telefono, direccion, tipo_cliente_id) VALUES (?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$nombre, $email, $tel, $direccion, $tipo]);
            header("Location: cartera.php?msj=Cliente creado");
        } elseif ($_POST['action'] == 'editar') {
            $sql = "UPDATE clientes SET nombre_completo=?, email=?, telefono=?, direccion=?, tipo_cliente_id=? WHERE id=?";
            $pdo->prepare($sql)->execute([$nombre, $email, $tel, $direccion, $tipo, $id]);
            header("Location: cartera.php?msj=Cliente actualizado");
        } elseif ($_POST['action'] == 'eliminar' && $id) {
            $sql = "DELETE FROM clientes WHERE id = ?";
            $pdo->prepare($sql)->execute([$id]);
            header("Location: cartera.php?msj=Cliente eliminado");
        }
        exit;
    } catch (Exception $e) {
        $error = "Error al procesar: " . $e->getMessage();
    }
}

// Filtros
$filtro_saldo = isset($_GET['con_saldo']) ? true : false;

// 1. Obtener Tipos de Cliente
$tipos = $pdo->query("SELECT * FROM tipos_cliente")->fetchAll();

// 2. Consulta de Clientes
$sql = "SELECT c.*, tc.nombre as tipo_nombre, 
        (SELECT SUM(total) FROM pedidos WHERE cliente_id = c.id) as total_comprado,
        (SELECT COUNT(*) FROM pedidos WHERE cliente_id = c.id) as cant_pedidos
        FROM clientes c 
        INNER JOIN tipos_cliente tc ON c.tipo_cliente_id = tc.id";

if ($filtro_saldo) {
    $sql .= " WHERE (SELECT SUM(total) FROM pedidos WHERE cliente_id = c.id) > 0";
}

$sql .= " ORDER BY c.nombre_completo ASC";
$clientes = $pdo->query($sql)->fetchAll();
$total_clientes = count($clientes);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Clientes | Panel Admin</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; }
        .stat-icon { width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .bg-blue { background: #eebf3122; color: #eebf31; }
        .bg-green { background: #10b98122; color: #10b981; }
        .actions-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .badge { padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: bold; }
        .badge-mayorista { background: #dcfce7; color: #166534; }
        .badge-distribuidor { background: #dbeafe; color: #1e40af; }
        .btn-active { background: #3b82f6 !important; color: white !important; border-color: #3b82f6 !important; }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <?php include 'sidebar.php'; ?> 

    <div class="main">
        <div class="header">
            <div>
                <h1><i class="fas fa-address-book"></i> Cartera de Clientes</h1>
                <p>Gestiona niveles de precios, saldos y contacto.</p>
            </div>
            <button class="btn-guardar" onclick="abrirModalCliente()">
                <i class="fas fa-plus"></i> Nuevo Cliente
            </button>
        </div>

        <?php if(isset($error)): ?>
            <div style="background:#fee2e2; color:#b91c1c; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #f87171;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-blue"><i class="fas fa-users"></i></div>
                <div>
                    <small style="color: #64748b;">Total Clientes</small>
                    <div style="font-size: 1.5rem; font-weight: bold;"><?php echo $total_clientes; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-green"><i class="fas fa-shopping-cart"></i></div>
                <div>
                    <small style="color: #64748b;">Ventas Totales</small>
                    <div style="font-size: 1.5rem; font-weight: bold;">$<?php echo number_format(array_sum(array_column($clientes, 'total_comprado')), 2); ?></div>
                </div>
            </div>
        </div>

        <div class="actions-bar">
            <div class="filter-group">
                <a href="cartera.php" class="btn <?php echo !$filtro_saldo ? 'btn-active' : ''; ?>" style="text-decoration:none; padding: 10px 15px; border-radius:8px; border:1px solid #ddd; background:white; color:#333;">Todos</a>
                <a href="cartera.php?con_saldo=1" class="btn <?php echo $filtro_saldo ? 'btn-active' : ''; ?>" style="text-decoration:none; padding: 10px 15px; border-radius:8px; border:1px solid #ddd; background:white; color:#333;">Con Historial</a>
            </div>
            <input type="text" id="busquedaCliente" class="form-control" placeholder="Buscar por nombre..." style="max-width: 300px; padding:10px; border-radius:8px; border:1px solid #ddd;">
        </div>

        <div class="form-container" style="padding: 0; overflow: hidden; background:white; border-radius:12px; box-shadow:0 4px 6px rgba(0,0,0,0.05);">
            <table style="width: 100%; border-collapse: collapse;" id="tablaClientes">
                <thead style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                    <tr>
                        <th style="padding: 15px; text-align: left;">Cliente / Contacto</th>
                        <th style="padding: 15px; text-align: left;">Tipo</th>
                        <th style="padding: 15px; text-align: right;">Pedidos</th>
                        <th style="padding: 15px; text-align: right;">Total Compras</th>
                        <th style="padding: 15px; text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($clientes as $c): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 15px;">
                            <div style="font-weight: bold;"><?php echo htmlspecialchars($c['nombre_completo']); ?></div>
                            <div style="font-size: 0.85rem; color: #64748b;">
                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($c['email']); ?><br>
                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($c['telefono']); ?>
                            </div>
                        </td>
                        <td style="padding: 15px;">
                            <span class="badge <?php echo 'badge-'.strtolower(str_replace(' ', '', $c['tipo_nombre'])); ?>">
                                <?php echo $c['tipo_nombre']; ?>
                            </span>
                        </td>
                        <td style="padding: 15px; text-align: right;"><?php echo $c['cant_pedidos'] ?? 0; ?></td>
                        <td style="padding: 15px; text-align: right; font-weight: bold;">
                            $<?php echo number_format($c['total_comprado'] ?? 0, 2); ?>
                        </td>
                        <td style="padding: 15px; text-align: center;">
                            <button title="Editar" onclick='editarCliente(<?php echo json_encode($c); ?>)' style="background:none; border:none; color:#3b82f6; cursor:pointer; font-size:1.1rem;">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button title="Eliminar" onclick="confirmarEliminar(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars($c['nombre_completo']); ?>')" style="background:none; border:none; color:#ef4444; cursor:pointer; margin-left:10px; font-size:1.1rem;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="modalCliente" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div class="modal-content" style="background:white; padding:30px; border-radius:15px; width:90%; max-width:500px; position:relative; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
            <h2 id="modalTitle" style="margin-bottom:20px;"></h2>
            
            <form id="formCliente" method="POST">
                <input type="hidden" name="action" id="formAction">
                <input type="hidden" name="cliente_id" id="cliente_id_hidden">

                <div class="form-group" style="margin-bottom:15px;">
                    <label style="display:block; font-weight:600; color:#475569;">Nombre Completo</label>
                    <input type="text" name="nombre_completo" id="modal_nombre" class="form-control" required style="width:100%; margin-top:5px; padding:10px; border:1px solid #ddd; border-radius:6px;">
                </div>

                <div class="form-group" style="margin-bottom:15px;">
                    <label style="display:block; font-weight:600; color:#475569;">Correo Electrónico</label>
                    <input type="email" name="email" id="modal_email" class="form-control" required style="width:100%; margin-top:5px; padding:10px; border:1px solid #ddd; border-radius:6px;">
                </div>

                <div class="form-group" style="margin-bottom:15px;">
                    <label style="display:block; font-weight:600; color:#475569;">Dirección</label>
                    <input type="text" name="direccion" id="modal_direccion" class="form-control" required style="width:100%; margin-top:5px; padding:10px; border:1px solid #ddd; border-radius:6px;">
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:20px;">
                    <div>
                        <label style="display:block; font-weight:600; color:#475569;">Teléfono</label>
                        <input type="text" name="telefono" id="modal_telefono" class="form-control" style="width:100%; margin-top:5px; padding:10px; border:1px solid #ddd; border-radius:6px;">
                    </div>
                    <div>
                        <label style="display:block; font-weight:600; color:#475569;">Tipo de Cliente</label>
                        <select name="tipo_cliente_id" id="modal_tipo" class="form-control" style="width:100%; margin-top:5px; padding:10px; border:1px solid #ddd; border-radius:6px;">
                            <?php foreach($tipos as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo $t['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display:flex; justify-content: flex-end; gap:10px;">
                    <button type="button" onclick="cerrarModal()" style="padding:10px 20px; background:#e2e8f0; border:none; border-radius:8px; cursor:pointer; font-weight:600;">Cancelar</button>
                    <button type="submit" class="btn-guardar" style="padding:10px 20px; background:#3b82f6; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600;">Guardar Cliente</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('modalCliente');
        const form = document.getElementById('formCliente');
        const modalTitle = document.getElementById('modalTitle');
        const formAction = document.getElementById('formAction');

        // Buscador
        document.getElementById('busquedaCliente').addEventListener('input', function() {
            let term = this.value.toLowerCase();
            let filas = document.querySelectorAll('#tablaClientes tbody tr');
            filas.forEach(f => {
                f.style.display = f.innerText.toLowerCase().includes(term) ? '' : 'none';
            });
        });

        function abrirModalCliente() {
            form.reset();
            modalTitle.innerHTML = '<i class="fas fa-user-plus"></i> Nuevo Cliente';
            formAction.value = 'crear';
            document.getElementById('cliente_id_hidden').value = '';
            modal.style.display = 'flex';
        }

        function editarCliente(c) {
            modalTitle.innerHTML = '<i class="fas fa-edit"></i> Editar Cliente';
            formAction.value = 'editar';
            document.getElementById('cliente_id_hidden').value = c.id;
            document.getElementById('modal_nombre').value = c.nombre_completo;
            document.getElementById('modal_email').value = c.email;
            document.getElementById('modal_telefono').value = c.telefono;
            document.getElementById('modal_direccion').value = c.direccion;
            document.getElementById('modal_tipo').value = c.tipo_cliente_id;
            modal.style.display = 'flex';
        }

        function cerrarModal() {
            modal.style.display = 'none';
        }

        function confirmarEliminar(id, nombre) {
            if (confirm(`¿Estás seguro de que deseas eliminar al cliente "${nombre}"? Esta acción no se puede deshacer.`)) {
                const f = document.createElement('form');
                f.method = 'POST';
                f.innerHTML = `
                    <input type="hidden" name="action" value="eliminar">
                    <input type="hidden" name="cliente_id" value="${id}">
                `;
                document.body.appendChild(f);
                f.submit();
            }
        }

        window.onclick = function(e) { if (e.target == modal) cerrarModal(); }
    </script>
    <script src="../js/admin.js"></script>
</body>
</html>