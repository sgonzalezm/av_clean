<?php
// 1. CONEXIÓN A LA BASE DE DATOS
include '../includes/conexion.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// 3. PROCESAR FILTROS DE BÚSQUEDA
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';

// Construir la consulta SQL con filtros
$sql = "SELECT * FROM productos WHERE 1=1";
$params = array();

if (!empty($search)) {
    $sql .= " AND (nombre LIKE :search OR descripcion LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($categoria)) {
    $sql .= " AND categoria = :categoria";
    $params[':categoria'] = $categoria;
}

$sql .= " ORDER BY categoria, nombre";

// 2. OBTENER PRODUCTOS CON FILTROS
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener categorías únicas para el filtro
$stmt_categorias = $pdo->query("SELECT DISTINCT categoria FROM productos WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria");
$categorias = $stmt_categorias->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Catálogo de Productos</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../css/store.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

</head>
<body>
    <div class="nav">
        <div class="container">
            <a href="/">← Volver al Inicio</a>
            <span>Catálogo de Productos</span>
        </div>
    </div>

    <header>
        <div class="carrito-contenedor">
        <a href="ver_carrito.php" class="carrito-link">
            <i class="fas fa-shopping-cart"></i> 🛒 <span id="carrito-count" class="badge">
                <?php 
                    // Sumamos todas las cantidades del array de sesión
                    echo isset($_SESSION['carrito']) ? array_sum($_SESSION['carrito']) : 0; 
                ?>
        </span>
    </a>
</div>
    </header>

    <div class="container">
        <h2 style="text-align: center; margin-bottom: 10px;">Nuestros Productos</h2>
        <!-- FILTROS DE BÚSQUEDA -->
        <div class="filtros">
            <form method="GET" style="display: contents;">
                <div class="filtro-grupo">
                    <label for="search">🔍 Buscar producto:</label>
                    <input type="text" 
                           id="search" 
                           name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Nombre o descripción...">
                </div>
                
                <div class="filtro-grupo">
                    <label for="categoria">📂 Filtrar por sección:</label>
                    <select id="categoria" name="categoria">
                        <option value="">Todas las secciones</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" 
                                <?php echo ($categoria == $cat) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <button type="submit" class="btn-filtro">Aplicar filtros</button>
                    <?php if (!empty($search) || !empty($categoria)): ?>
                        <a href="?" class="btn-limpiar">Limpiar filtros</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- INFO DE RESULTADOS -->
        <?php if (!empty($search) || !empty($categoria)): ?>
            <div class="resultados-info">
                <?php 
                $total = count($productos);
                $mensaje = "Mostrando $total producto" . ($total != 1 ? 's' : '');
                
                if (!empty($search) && !empty($categoria)) {
                    $mensaje .= " que coinciden con la búsqueda \"$search\" en la sección \"$categoria\"";
                } elseif (!empty($search)) {
                    $mensaje .= " que coinciden con la búsqueda \"$search\"";
                } elseif (!empty($categoria)) {
                    $mensaje .= " de la sección \"$categoria\"";
                }
                ?>
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <div class="productos">
            <?php if (count($productos) > 0): ?>
                <?php foreach ($productos as $p): ?>
                <div class="producto">
                    <div class="producto-imagen">
                        <?php if (!empty($p['imagen_url'])): ?>
                            <img src="<?php echo htmlspecialchars($p['imagen_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($p['nombre']); ?>">
                        <?php else: ?>
                            <span>Sin imagen</span>
                        <?php endif; ?>
                    </div>
                    
                    <span class="categoria"><?php echo htmlspecialchars($p['categoria']); ?></span>
                    
                    <h3><?php echo htmlspecialchars($p['nombre']); ?></h3>
                    
                    <div class="descripcion">
                        <?php echo nl2br(htmlspecialchars($p['descripcion'])); ?>
                    </div>
                    
                    <div class="precio">$<?php echo number_format($p['precio'], 2); ?></div>
                    <div class="producto-botones">
                        <a href="#" class="btn">Ver detalles</a>
                        <a href="#" class="btn btn-agregar-ajax" data-id="<?php echo $p['id']; ?>">Agregar al carrito</a>                   
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="sin-productos">
                    <p>📦 No se encontraron productos que coincidan con los filtros aplicados.</p>
                    <?php if (!empty($search) || !empty($categoria)): ?>
                        <p style="margin-top: 20px;">
                            <a href="?" style="color: #4299e1;">→ Ver todos los productos</a>
                        </p>
                    <?php else: ?>
                        <p style="margin-top: 20px;">Próximamente mostraremos más productos en nuestro catálogo.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer style="background: #1a365d; color: white; padding: 30px 0; margin-top: 60px;">
        <div style="text-align: center;">
            <p>© 2026 AHD Clean - Todos los derechos reservados</p>
        </div>
    </footer>

    <script>
        document.querySelectorAll('.btn-agregar-ajax').forEach(boton => {
        boton.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            
            fetch(`agregar_carrito.php?id=${id}`)
                .then(res => {
                    if(res.ok) {
                        // Animación visual de éxito
                        this.textContent = "¡Añadido!";
                        this.style.backgroundColor = "#28a745"; // Cambia a verde

                        const badge = document.getElementById('carrito-count');
                        let cantidadActual = parseInt(badge.textContent);
                        badge.textContent = cantidadActual + 1;

                        
                        
                        setTimeout(() => {
                            this.textContent = "Agregar al carrito";
                            this.style.backgroundColor = "#002bff"; // Vuelve al azul original
                        }, 1500);
                    }
                });
            });
        });
    </script>
</body>
</html>
