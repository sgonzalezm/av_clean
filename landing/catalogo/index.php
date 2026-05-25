<?php
// 1. CARGA DE CONFIGURACIÓN Y CONEXIÓN
// Asumimos que este archivo ya define la variable $pdo correctamente
include '../includes/conexion.php'; 
session_start(); 

// 2. CAPTURA DE FILTROS
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';

// 3. CONSULTA DE PRODUCTOS (Lógica central)
try {
    // Buscamos productos que NO tengan padre (son los principales)
    // Usamos (id_padre IS NULL OR id_padre = 0) para cubrir ambos casos posibles en DB
    $sql = "SELECT * FROM productos WHERE (id_padre IS NULL OR id_padre = 0 OR id_padre = '')";
    $params = array();

    if (!empty($search)) {
        $sql .= " AND (nombre LIKE :search OR descripcion LIKE :search)";
        $params[':search'] = "%$search%";
    }

    if (!empty($categoria)) {
        $sql .= " AND categoria = :categoria";
        $params[':categoria'] = $categoria;
    }

    $sql .= " ORDER BY nombre ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $productos_padres = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. OBTENER CATEGORÍAS PARA EL SELECT
    $stmt_cat = $pdo->query("SELECT DISTINCT categoria FROM productos WHERE categoria IS NOT NULL AND categoria != ''");
    $lista_categorias = $stmt_cat->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    // Si hay un error, lo mostramos solo para desarrollo
    echo "Error en la consulta: " . $e->getMessage();
    $productos_padres = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AHD Clean | Tienda</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #1a365d; --accent: #002bff; --bg: #f8fafc; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--bg); margin: 0; }
        
        .navbar { background: var(--primary); padding: 1rem 5%; display: flex; justify-content: space-between; align-items: center; color: white; }
        .logo-container { display: flex; align-items: center; gap: 10px; }
        
        .main-content { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        
        /* Buscador */
        .search-bar { background: white; padding: 20px; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .search-form { display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; }
        
        /* Grid de Productos */
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; }
        .product-card { background: white; border-radius: 20px; padding: 20px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); transition: 0.3s; }
        .product-card:hover { transform: translateY(-5px); }
        
        .img-placeholder { width: 100%; height: 200px; background: #eee; border-radius: 15px; display: flex; align-items: center; justify-content: center; overflow: hidden !important; }
        .img-placeholder img { max-width: 100%; max-height: 100%; object-fit: cover !important; object-position: center !important; }

        .price-tag { font-size: 1.5rem; font-weight: bold; color: var(--primary); }
        .btn-add { background: var(--accent); color: white; border: none; padding: 10px 20px; border-radius: 10px; cursor: pointer; width: 100%; font-weight: bold; margin-top: 10px; }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="logo-container">
        <img src="css/Logo_AHD_Clean.png" alt="AHD Clean" height="50">
        <h1 style="margin:0; font-size: 1.5rem;">Tienda en Línea</h1>
    </div>
    <div>
        <a href="ver_carrito.php" style="color: white; text-decoration: none; font-weight: bold;">
            <i class="fas fa-shopping-cart"></i> Carrito (<?php echo isset($_SESSION['carrito']) ? count($_SESSION['carrito']) : 0; ?>)
        </a>
    </div>
</nav>

<main class="main-content">
    <section class="search-bar">
        <form class="search-form" method="GET">
            <input type="text" name="search" placeholder="Buscar producto..." value="<?php echo htmlspecialchars($search); ?>" style="padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
            <select name="categoria" style="padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                <option value="">Todas las categorías</option>
                <?php foreach($lista_categorias as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $categoria == $cat ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" style="background: var(--primary); color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer;">Filtrar</button>
        </form>
    </section>

    <div class="products-grid">
        <?php if (empty($productos_padres)): ?>
            <p style="grid-column: 1/-1; text-align: center; color: #666; padding: 50px;">No se encontraron productos con los criterios seleccionados.</p>
        <?php else: ?>
            <?php foreach ($productos_padres as $p): ?>
                <div class="product-card">
                    <div class="img-placeholder">
                        <img src="<?php echo !empty($p['imagen_url']) ? $p['imagen_url'] : 'css/placeholder.png'; ?>" alt="Producto">
                    </div>
                    <p style="color: var(--accent); font-weight: bold; font-size: 0.8rem; margin-top: 15px;"><?php echo htmlspecialchars($p['categoria']); ?></p>
                    <h3 style="margin: 5px 0;"><?php echo htmlspecialchars($p['nombre']); ?></h3>
                    <p style="color: #666; font-size: 0.9rem; height: 3em; overflow: hidden;"><?php echo htmlspecialchars($p['descripcion']); ?></p>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
                        <span class="price-tag">$<?php echo number_format($p['precio'], 2); ?></span>
                        <a href="detalles.php?id=<?php echo $p['id']; ?>" style="color: var(--primary); text-decoration: none; font-size: 0.8rem;">Ver más</a>
                    </div>
                    
                    <button class="btn-add" onclick="agregarCarrito(<?php echo $p['id']; ?>)">
                        Agregar al Carrito
                    </button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<script>
function agregarCarrito(id) {
    fetch('agregar_carrito.php?id=' + id)
        .then(response => response.text())
        .then(data => {
            alert('Producto añadido al carrito');
            location.reload();
        });
}
</script>

</body>
</html>