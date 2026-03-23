<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// 1. PROCESAR GUARDADO DE NUEVO PROVEEDOR
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nuevo_proveedor'])) {
    $nombre = $_POST['nombre_empresa'];
    $contacto = $_POST['contacto_nombre'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];

    $sql_insert = "INSERT INTO proveedores (nombre_empresa, contacto_nombre, telefono, email) 
                   VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql_insert);
    $stmt->execute([$nombre, $contacto, $telefono, $email]);

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// 2. CONSULTA DE PROVEEDORES
$proveedores = $pdo->query("SELECT * FROM proveedores ORDER BY nombre_empresa ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proveedores - AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); }
        .modal-content { background:white; width:90%; max-width:500px; margin:5% auto; padding:25px; border-radius:12px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .form-group { margin-bottom: 15px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        .btn-edit { color: #007bff; cursor: pointer; margin-right: 10px; }
        .btn-delete { color: #dc3545; cursor: pointer; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <div class="header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h1><i class="fas fa-truck"></i> Gestión de Proveedores</h1>
            <button class="btn" onclick="document.getElementById('modalProveedor').style.display='block'" style="padding:10px 20px; cursor:pointer;">
                <i class="fas fa-plus"></i> Añadir Proveedor
            </button>
        </div>

        <table border="1" style="width:100%; border-collapse: collapse; background: white;">
            <thead>
                <tr style="background: #f4f4f4;">
                    <th>ID</th>
                    <th>Empresa</th>
                    <th>Contacto</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($proveedores as $p): ?>
                <tr>
                    <td><?php echo $p['id_proveedor']; ?></td>
                    <td><strong><?php echo htmlspecialchars($p['nombre_empresa']); ?></strong></td>
                    <td><?php echo htmlspecialchars($p['contacto_nombre']); ?></td>
                    <td><?php echo htmlspecialchars($p['telefono']); ?></td>
                    <td><?php echo htmlspecialchars($p['email']); ?></td>
                    <td>
                        <i class="fas fa-edit btn-edit" title="Editar"></i>
                        <i class="fas fa-trash btn-delete" title="Eliminar"></i>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($proveedores)): ?>
                    <tr><td colspan="6" style="text-align:center; padding:20px;">No hay proveedores registrados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div id="modalProveedor" class="modal">
        <div class="modal-content">
            <h2 style="margin-top:0;"><i class="fas fa-address-card"></i> Nuevo Proveedor</h2>
            <hr style="margin-bottom:20px;">
            <form method="POST">
                <input type="hidden" name="nuevo_proveedor" value="1">
                
                <div class="form-group">
                    <label>Nombre de la Empresa:</label>
                    <input type="text" name="nombre_empresa" class="form-control" placeholder="Ej. Químicos de México S.A." required>
                </div>

                <div class="form-group">
                    <label>Nombre del Contacto:</label>
                    <input type="text" name="contacto_nombre" class="form-control" placeholder="Ej. Ing. Alberto García">
                </div>

                <div style="display:flex; gap:10px;">
                    <div class="form-group" style="flex:1;">
                        <label>Teléfono:</label>
                        <input type="tel" name="telefono" class="form-control" placeholder="33 0000 0000">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Email:</label>
                        <input type="email" name="email" class="form-control" placeholder="ventas@proveedor.com">
                    </div>
                </div>

                <div style="margin-top:20px;">
                    <button type="submit" class="btn" style="width:100%; padding:12px; background:#007bff; color:white; border:none; border-radius:5px; cursor:pointer; font-weight:bold;">
                        <i class="fas fa-save"></i> Registrar Proveedor
                    </button>
                    <button type="button" onclick="document.getElementById('modalProveedor').style.display='none'" 
                            style="width:100%; margin-top:10px; background:none; border:none; color:#666; cursor:pointer;">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            let modal = document.getElementById('modalProveedor');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>