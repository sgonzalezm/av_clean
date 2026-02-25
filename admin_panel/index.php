<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// Estadísticas
$totalProductos = $pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn();
$totalCategorias = $pdo->query("SELECT COUNT(DISTINCT categoria) FROM productos")->fetchColumn();
$totalUsuarios = $pdo->query("SELECT COUNT(*) FROM usuarios_admin")->fetchColumn();
$totalPedidos = $pdo->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <!-- Botón toggle para móvil -->
    <button class="menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar (menú lateral) -->
    <div class="sidebar">
        <h2><i class="fas fa-store"></i> Panel Admin</h2>
        <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'activo' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="catalogo_productos.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'catalogo_productos.php' ? 'activo' : ''; ?>">
            <i class="fas fa-box"></i> Productos
        </a>
        <a href="categorias.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'categorias.php' ? 'activo' : ''; ?>">
            <i class="fas fa-tags"></i> Categorías
        </a>
        <a href="usuarios.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'activo' : ''; ?>">
            <i class="fas fa-users"></i> Usuarios
        </a>
        <a href="pedidos.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'pedidos.php' ? 'activo' : ''; ?>">
            <i class="fas fa-shopping-cart"></i> Pedidos
        </a>
        <a href="configuracion.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'configuracion.php' ? 'activo' : ''; ?>">
            <i class="fas fa-cog"></i> Configuración
        </a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Salir</a>
        <div class="rol-info">
            <i class="fas fa-user-circle"></i> 
            <?php echo htmlspecialchars($user['nombre']); ?> (<?php echo htmlspecialchars($user['rol']); ?>)
        </div>
    </div>
    
    <div class="main">
        <div class="header">
            <h1>Bienvenido, <?php echo $_SESSION['admin_nombre']; ?></h1>
            <span>Rol: <?php echo $_SESSION['admin_rol']; ?></span>
        </div>
        
        <div class="cards">
            <div class="card">
                <h3>Total Productos</h3>
                <div class="numero"><?php echo $totalProductos; ?></div>
            </div>
            <div class="card">
                <h3>Categorías</h3>
                <div class="numero"><?php echo $totalCategorias; ?></div>
            </div>
            <div class="card">
                <h3>Usuarios Admin</h3>
                <div class="numero"><?php echo $totalUsuarios; ?></div>
            </div>
            <div class="card">
                <h3>Pedidos</h3>
                <div class="card-sub">Pendientes
                    <div class="numero"><?php echo $totalPedidos; ?></div>
                </div>
                <div class="card-sub">Completados
                    <div class="numero"><?php echo $totalPedidos; ?></div>
                </div>
            </div>
        </div>
        
        <!-- Aquí puedes agregar más bloques: productos recientes, etc. -->
    </div>
    <script src="../js/admin.js"></script>
</body>
</html>