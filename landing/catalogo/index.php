<?php
// 1. CONEXIÓN A LA BASE DE DATOS
$host = 'localhost';
$dbname = 'u918498641_catalogo_db';
$username = 'u918498641_sgonzalezm';
$password = '3lR10Quefluye$';

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
    <style>
        body { font-family: Arial; margin: 0; padding: 20px; background: #f5f5f5; }
        h1 { text-align: center; color: #333; }
        .productos { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); 
            gap: 20px; 
            max-width: 1200px;
            margin: 0 auto;
        }
        .producto { 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .producto-imagen {
            width: 100%;
            height: 180px;
            background-color: #e9ecef;
            border-radius: 5px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 14px;
        }
        .producto-imagen img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 5px;
        }
        .categoria { 
            background: #007bff; 
            color: white; 
            padding: 4px 12px; 
            border-radius: 20px; 
            font-size: 12px; 
            display: inline-block;
            margin-bottom: 10px;
        }
        h3 { 
            margin: 10px 0; 
            color: #1a365d;
            font-size: 1.2em;
        }
        .descripcion { 
            color: #666; 
            font-size: 0.9em;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        .precio { 
            color: #28a745; 
            font-size: 24px; 
            font-weight: bold;
            margin: 15px 0;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .sin-productos {
            text-align: center;
            padding: 60px;
            color: #666;
            grid-column: 1 / -1;
        }
        .nav {
            background: #1a365d;
            padding: 15px 0;
            margin-bottom: 30px;
        }
        .nav .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            color: white;
        }
        .nav a {
            color: white;
            text-decoration: none;
        }
    </style>
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
            <p>© 2024 Tu Empresa - Todos los derechos reservados</p>
        </div>
    </footer>
</body>
</html>