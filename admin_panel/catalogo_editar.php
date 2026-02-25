<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$id = $_GET['id'] ?? 0;
$producto = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
$producto->execute([$id]);
$p = $producto->fetch();

if (!$p) {
    header('Location: productos.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $precio = $_POST['precio'];
    $imagen_url = $_POST['imagen_url'];
    $categoria = $_POST['categoria'];
    
    $stmt = $pdo->prepare("UPDATE productos SET nombre=?, descripcion=?, precio=?, imagen_url=?, categoria=? WHERE id=?");
    if ($stmt->execute([$nombre, $descripcion, $precio, $imagen_url, $categoria, $id])) {
        header('Location: catalogo_productos.php?ok=2');
        exit;
    } else {
        $error = "Error al actualizar";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Editar Producto</title>
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

    <div class="form-container">
        <h1>✏️ Editar Producto</h1>
        
        <?php if (isset($error)) echo "<p style='color:red'>$error</p>"; ?>
        
        <form method="POST">
            <label>Nombre del Producto</label>
            <input type="text" name="nombre" value="<?php echo htmlspecialchars($p['nombre']); ?>" required>
            
            <label>Descripción</label>
            <textarea name="descripcion" rows="4" required><?php echo htmlspecialchars($p['descripcion']); ?></textarea>
            
            <label>Precio ($)</label>
            <input type="number" step="0.01" name="precio" value="<?php echo $p['precio']; ?>" required>
            
            <label>URL de la Imagen</label>
            <input type="text" name="imagen_url" value="<?php echo htmlspecialchars($p['imagen_url']); ?>">
            <?php if (!empty($p['imagen_url'])): ?>
                <div class="imagen-actual">
                    Imagen actual: <a href="<?php echo $p['imagen_url']; ?>" target="_blank">Ver</a>
                </div>
            <?php endif; ?>
            
            <label>Categoría</label>
            <select name="categoria" required>
                <option value="Hogar" <?php echo $p['categoria']=='Hogar'?'selected':''; ?>>Hogar</option>
                <option value="Industrial" <?php echo $p['categoria']=='Industrial'?'selected':''; ?>>Industrial</option>
                <option value="Automotriz" <?php echo $p['categoria']=='Automotriz'?'selected':''; ?>>Automotriz</option>
            </select>
            
            <button type="submit">Actualizar Producto</button>
            <a href="catalogo_productos.php"><button type="button" class="cancelar">Cancelar</button></a>
        </form>
    </div>
    <script src="../js/admin.js"></script>
</body>
</html>