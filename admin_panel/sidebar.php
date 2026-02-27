<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();
// sidebar.php
// No inicies sesión aquí porque ya debería estar iniciada en la página principal
// pero verificamos que exista la variable de sesión

// Verificar que la sesión tenga los datos necesarios
if (!isset($_SESSION['admin_nombre']) || !isset($_SESSION['admin_rol'])) {
    // Si no hay datos, intentamos obtenerlos o mostramos valores por defecto
    $nombre_usuario = $_SESSION['admin_nombre'] ?? 'Usuario';
    $rol_usuario = $_SESSION['admin_rol'] ?? 'visitante';
} else {
    $nombre_usuario = $_SESSION['admin_nombre'];
    $rol_usuario = $_SESSION['admin_rol'];
}

// Función para verificar si mostrar un enlace según el rol
function mostrarEnlace($rol_requerido = 'visitante') {
    return tienePermiso($rol_requerido);
}

// Función para obtener la clase activa
function claseActiva($pagina) {
    return basename($_SERVER['PHP_SELF']) == $pagina ? 'activo' : '';
}
?>
<div class="sidebar">
    <h2><i class="fas fa-store"></i> Panel Admin</h2>
    
    <!-- Enlaces visibles para todos los usuarios autenticados -->
    <a href="index.php" class="<?php echo claseActiva('index.php'); ?>">
        <i class="fas fa-tachometer-alt"></i> Dashboard
    </a>
    
    <!-- Solo para editores y admins -->
    <?php if (mostrarEnlace('editor')): ?>
    <a href="catalogo_productos.php" class="<?php echo claseActiva('catalogo_productos.php'); ?>">
        <i class="fas fa-box"></i> Productos
    </a>
    <a href="categorias.php" class="<?php echo claseActiva('categorias.php'); ?>">
        <i class="fas fa-tags"></i> Categorías
    </a>
    <?php endif; ?>
    
    <!-- Solo para admins -->
    <?php if (mostrarEnlace('admin')): ?>
    <a href="usuarios.php" class="<?php echo claseActiva('usuarios.php'); ?>">
        <i class="fas fa-users"></i> Usuarios
    </a>
    <?php endif; ?>
    
    <!-- Enlaces para editores y admins (continuación) -->
    <?php if (mostrarEnlace('editor')): ?>
    <a href="pedidos.php" class="<?php echo claseActiva('pedidos.php'); ?>">
        <i class="fas fa-shopping-cart"></i> Pedidos
    </a>
    <a href="inventario.php" class="<?php echo claseActiva('inventario.php'); ?>">
        <i class="fas fa-warehouse"></i> Inventario
    </a>
    <?php endif; ?>
    
    <!-- Solo para admins -->
    <?php if (mostrarEnlace('admin')): ?>
    <a href="configuracion.php" class="<?php echo claseActiva('configuracion.php'); ?>">
        <i class="fas fa-cog"></i> Configuración
    </a>
    <?php endif; ?>
    
    <!-- Salir visible para todos -->
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Salir</a>
    
    <!-- Información del usuario -->
    <div class="rol-info">
        <i class="fas fa-user-circle"></i> 
        <?php echo htmlspecialchars($nombre_usuario); ?> 
        (<?php echo htmlspecialchars($rol_usuario); ?>)
        <?php if (tienePermiso('admin')): ?>
            <span class="badge-admin">Admin</span>
        <?php elseif (tienePermiso('editor')): ?>
            <span class="badge-editor">Editor</span>
        <?php endif; ?>
    </div>
</div>