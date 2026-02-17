<?php
// 1. CONEXIÓN A LA BASE DE DATOS
include '../../includes/conexion.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// 2. OBTENER PRODUCTOS (SIN filtro "activo" porque tu BD no tiene ese campo)
$stmt = $pdo->query("SELECT * FROM productos ORDER BY categoria, nombre");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Catálogo de Productos</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../css/store.css">
</head>
<body>
    <div class="nav">
        <div class="container">
            <a href="/">← Volver al Inicio</a>
            <span>Catálogo de Productos</span>
        </div>
    </div>

    <div class="container">
        <h1 style="text-align: center; margin-bottom: 40px;">Nuestros Productos</h1>
        
        <div class="productos">
            <?php if (count($productos) > 0): ?>
                <?php foreach ($productos as $p): ?>
                <div class="producto">
                    <div class="producto-imagen">
                        <?php if (!empty($p['imagen_url'])): ?>
                            <img src="<?php echo htmlspecialchars($p['imagen_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($p['nombre']); ?>">
                        <?php else: ?>
                            <span>📷 Sin imagen</span>
                        <?php endif; ?>
                    </div>
                    
                    <span class="categoria"><?php echo htmlspecialchars($p['categoria']); ?></span>
                    
                    <h3><?php echo htmlspecialchars($p['nombre']); ?></h3>
                    
                    <div class="descripcion">
                        <?php echo nl2br(htmlspecialchars($p['descripcion'])); ?>
                    </div>
                    
                    <div class="precio">$<?php echo number_format($p['precio'], 2); ?></div>
                    
                    <a href="#" class="btn">Ver detalles</a>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="sin-productos">
                    <p>📦 Próximamente mostraremos nuestro catálogo de productos.</p>
                    <p style="margin-top: 20px;">Mientras tanto, contáctanos para más información.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer style="background: #1a365d; color: white; padding: 30px 0; margin-top: 60px;">
        <div style="text-align: center;">
            <p>© 2026 AHD Clean - Todos los derechos reservados</p>
        </div>
    </footer>
</body>
</html>