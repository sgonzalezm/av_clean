<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();
// Solo admin puede ver usuarios
verificarRol(['admin']);

// Obtener usuarios
$usuarios = $pdo->query("SELECT * FROM usuarios_admin ORDER BY id DESC")->fetchAll();

// Mensajes de feedback
$mensaje = '';
if (isset($_GET['ok'])) {
    if ($_GET['ok'] == 1) $mensaje = "Usuario agregado correctamente";
    if ($_GET['ok'] == 2) $mensaje = "Usuario actualizado correctamente";
    if ($_GET['ok'] == 3) $mensaje = "Usuario eliminado";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Gestionar Usuarios</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="sidebar">
        <h2>⚙️ Admin Panel</h2>
        <a href="index.php">📊 Dashboard</a>
        <a href="catalogo_productos.php">📦 Productos</a>
        <a href="usuarios.php" class="activo">👥 Usuarios</a>
        <a href="logout.php">🚪 Salir</a>
    </div>
    
    <div class="main">
        <div class="header">
            <h1>Gestión de Usuarios Administradores</h1>
            <a href="usuario_agregar.php" class="btn">➕ Nuevo Usuario</a>
        </div>
        
        <?php if ($mensaje): ?>
            <div class="mensaje"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Último Acceso</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?php echo $u['id']; ?></td>
                    <td><?php echo htmlspecialchars($u['nombre']); ?></td>
                    <td><strong><?php echo htmlspecialchars($u['usuario']); ?></strong></td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td>
                        <span class="rol rol-<?php echo $u['rol']; ?>">
                            <?php 
                                switch($u['rol']) {
                                    case 'admin': echo 'Administrador'; break;
                                    case 'editor': echo 'Editor'; break;
                                    case 'visitante': echo 'Visitante'; break;
                                }
                            ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($u['activo']): ?>
                            <span class="activo-si">✅ Activo</span>
                        <?php else: ?>
                            <span class="activo-no">❌ Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                            if ($u['ultimo_acceso']) {
                                echo date('d/m/Y H:i', strtotime($u['ultimo_acceso']));
                            } else {
                                echo 'Nunca';
                            }
                        ?>
                    </td>
                    <td>
                        <a href="usuario_editar.php?id=<?php echo $u['id']; ?>" class="btn-small">✏️ Editar</a>
                        <?php if ($u['id'] != $_SESSION['admin_id']): // No puede eliminarse a sí mismo ?>
                            <a href="usuario_eliminar.php?id=<?php echo $u['id']; ?>" class="btn-small red" onclick="return confirm('¿Estás seguro de eliminar este usuario?')">🗑️ Eliminar</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>