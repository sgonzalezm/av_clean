<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// Estadísticas
$totalProductos = $pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn();
$totalCategorias = $pdo->query("SELECT COUNT(DISTINCT categoria) FROM productos")->fetchColumn();
$totalUsuarios = $pdo->query("SELECT COUNT(*) FROM usuarios_admin")->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="sidebar">
        <h2>⚙️ Admin Panel</h2>
        <a href="index.php">📊 Dashboard</a>
        <a href="catalogo_productos.php">📦 Productos</a>
        <a href="usuarios.php">👥 Usuarios</a>
        <a href="logout.php">🚪 Salir</a>
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
        </div>
        
        <!-- Aquí puedes agregar más bloques: productos recientes, etc. -->
    </div>
</body>
</html>