<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// Función para verificar permisos por rol
/*
function tienePermiso($rol_requerido = 'editor') {
    $roles_jerarquia = [
        'visitante' => 1,
        'editor' => 2,
        'admin' => 3
    ];
    
    $usuario_rol = $_SESSION['admin_rol'] ?? 'visitante';
    
    return $roles_jerarquia[$usuario_rol] >= $roles_jerarquia[$rol_requerido];
}*/

// Obtener productos
$productos = $pdo->query("SELECT * FROM productos ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Gestionar Productos</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="sidebar">
        <h2>⚙️ Admin Panel</h2>
        <a href="index.php">📊 Dashboard</a>
        <a href="catalogo_productos.php" class="activo">📦 Productos</a>
        <a href="usuarios.php">👥 Usuarios</a>
        <a href="logout.php">🚪 Salir</a>
        <div style="position: absolute; bottom: 20px; left: 20px; color: #aaa; font-size: 12px;">
            Rol: <strong><?php echo $_SESSION['admin_rol'] ?? 'visitante'; ?></strong>
        </div>
    </div>
    
    <div class="main">
        <div class="header">
            <div>
                <h1>Gestión de Productos</h1>
                <?php if (!tienePermiso('editor')): ?>
                    <span class="badge-rol">Modo solo lectura</span>
                <?php endif; ?>
            </div>
            
            <?php if (tienePermiso('editor')): ?>
                <a href="catalogo_agregar.php" class="btn">➕ Nuevo Producto</a>
            <?php else: ?>
                <span class="btn disabled">🔒 Solo lectura</span>
            <?php endif; ?>
        </div>
        
        <?php if (isset($_GET['error']) && $_GET['error'] == 'permiso'): ?>
            <div class="mensaje-permiso">
                ⚠️ No tienes permisos suficientes para realizar esa acción.
            </div>
        <?php endif; ?>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Categoría</th>
                    <th>Precio</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $p): ?>
                <tr>
                    <td><?php echo $p['id']; ?></td>
                    <td><?php echo htmlspecialchars($p['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($p['categoria']); ?></td>
                    <td>$<?php echo number_format($p['precio'], 2); ?></td>
                    <td>
                        <?php if (tienePermiso('editor')): ?>
                            <!-- Usuario con permisos de edición -->
                            <a href="catalogo_editar.php?id=<?php echo $p['id']; ?>" class="btn-small">✏️ Editar</a>
                            <a href="catalogo_eliminar.php?id=<?php echo $p['id']; ?>" class="btn-small red" onclick="return confirm('¿Eliminar este producto?')">🗑️ Eliminar</a>
                        <?php else: ?>
                            <!-- Usuario sin permisos de edición -->
                            <span class="btn-small disabled" title="No tienes permisos para editar">🔍 Ver solo</span>
                            <span class="btn-small disabled" title="No tienes permisos para eliminar">🚫 Sin acceso</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if (count($productos) == 0): ?>
            <p style="text-align: center; padding: 40px; color: #666;">No hay productos registrados</p>
        <?php endif; ?>
    </div>
</body>
</html>