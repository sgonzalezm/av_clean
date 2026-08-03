<?php
// 1. CARGA DE CONFIGURACIÓN Y CONEXIÓN
include '../includes/conexion.php'; 
session_start(); 

// Inicializar carrito si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// 2. CAPTURA DE FILTROS
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';

// 3. CONSULTA DE PRODUCTOS
try {
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

    // OBTENER CATEGORÍAS
    $stmt_cat = $pdo->query("SELECT DISTINCT categoria FROM productos WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria ASC");
    $lista_categorias = $stmt_cat->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    $productos_padres = [];
    $lista_categorias = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AHD Clean | Catálogo de Productos</title>
    <meta name="description" content="Catálogo de productos químicos de limpieza para hogar, industria y automotriz.">
    
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #0f172a;
            --accent: #38bdf8;
            --success: #22c55e;
            --danger: #ef4444;
            --warning: #f59e0b;
            --white: #ffffff;
            --gray-light: #f1f5f9;
            --gray: #94a3b8;
            --shadow: 0 10px 30px rgba(0,0,0,0.08);
            --shadow-hover: 0 20px 40px rgba(0,0,0,0.12);
            --radius: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; scroll-behavior: smooth; }
        body { 
            font-family: 'Roboto', sans-serif; 
            background: #f8fafc; 
            color: var(--secondary); 
            padding-top: 80px;
        }

        /* NAVBAR */
        .navbar {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            padding: 12px 0;
            box-shadow: var(--shadow);
        }
        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
        }
        .logo-text .logo-main {
            display: block;
            font-family: 'Montserrat';
            font-weight: 800;
            font-size: 1.4rem;
            color: var(--primary);
            line-height: 1;
        }
        .logo-text .logo-sub {
            font-size: 0.65rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--secondary);
        }
        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .nav-link {
            text-decoration: none;
            color: var(--secondary);
            font-weight: 500;
            transition: 0.3s;
            font-size: 0.95rem;
        }
        .nav-link:hover { color: var(--primary); }
        
        /* CARRITO BUTTON */
        .cart-btn {
            position: relative;
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: 0.3s;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
        }
        .cart-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(239,68,68,0.4);
            transition: 0.3s;
            animation: pop 0.3s ease;
        }
        .cart-badge.hidden { display: none; }

        @keyframes pop {
            0% { transform: scale(0.5); }
            70% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        /* MAIN CONTENT */
        .main-content { max-width: 1200px; margin: 0 auto; padding: 0 25px 50px; }

        /* FILTROS */
        .filter-section {
            background: white;
            padding: 25px 30px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        .filter-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-weight: 600; font-size: 0.9rem; color: #64748b; }
        .filter-group input,
        .filter-group select {
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: 0.3s;
            background: white;
            font-family: inherit;
            width: 100%;
        }
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .btn-filter {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            font-size: 1rem;
            white-space: nowrap;
        }
        .btn-filter:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        .btn-clear {
            background: #e2e8f0;
            color: var(--secondary);
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            text-decoration: none;
            font-size: 1rem;
            white-space: nowrap;
        }
        .btn-clear:hover { background: #cbd5e1; }

        /* RESULTADOS HEADER */
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .results-count {
            color: #64748b;
            font-size: 0.95rem;
        }
        .results-count strong { color: var(--secondary); }

        /* PRODUCTOS GRID */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }
        .product-card {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            transition: all 0.3s;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        .product-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-hover);
        }
        .product-image {
            width: 100%;
            height: 220px;
            background: var(--gray-light);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 15px;
        }
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .product-image .no-image {
            color: #94a3b8;
            font-size: 3rem;
        }
        .product-category {
            color: var(--primary);
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }
        .product-name {
            font-family: 'Montserrat';
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 8px;
            line-height: 1.3;
        }
        .product-description {
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex: 1;
            margin-bottom: 15px;
        }
        .product-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        .product-price {
            font-family: 'Montserrat';
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
        }
        .product-actions {
            display: flex;
            gap: 8px;
        }
        .btn-detail {
            background: #e2e8f0;
            color: var(--secondary);
            border: none;
            padding: 8px 14px;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.3s;
            font-size: 0.85rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .btn-detail:hover { background: #cbd5e1; }
        .btn-add {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .btn-add:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }
        .btn-add.added {
            background: var(--success);
        }

        /* TOAST NOTIFICATION */
        .toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: var(--secondary);
            color: white;
            padding: 16px 30px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            z-index: 9999;
            opacity: 0;
            transition: all 0.5s ease;
            pointer-events: none;
        }
        .toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        .toast.success { background: var(--success); }
        .toast.error { background: var(--danger); }
        .toast i { font-size: 1.2rem; }

        /* EMPTY STATE */
        .empty-state {
            grid-column: 1/-1;
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 20px;
        }
        .empty-state h3 { font-size: 1.5rem; margin-bottom: 10px; }
        .empty-state p { color: #64748b; }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
            .filter-actions {
                flex-direction: column;
                width: 100%;
            }
            .filter-actions button,
            .filter-actions a {
                width: 100%;
                text-align: center;
                justify-content: center;
            }
            .nav-container { flex-wrap: wrap; gap: 10px; }
            .nav-right { width: 100%; justify-content: space-between; }
            .cart-btn span { display: none; }
            .products-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 480px) {
            .products-grid { grid-template-columns: 1fr; }
            .product-image { height: 180px; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="nav-container">
        <a href="../index.html" class="logo-container">
            <img src="../css/Logo_AHD_Clean.png" width="50" height="50" alt="AHD Clean" style="object-fit: contain;">
            <div class="logo-text">
                <span class="logo-main">AHD Clean</span>
                <span class="logo-sub">Catálogo de Productos</span>
            </div>
        </a>
        <div class="nav-right">
            <a href="../index.html#inicio" class="nav-link"><i class="fas fa-arrow-left"></i> Inicio</a>
            <button class="cart-btn" onclick="window.location.href='ver_carrito.php'" id="cartBtn">
                <i class="fas fa-shopping-cart"></i>
                <span>Carrito</span>
                <span class="cart-badge <?php echo empty($_SESSION['carrito']) ? 'hidden' : ''; ?>" id="cartBadge">
                    <?php echo count($_SESSION['carrito']); ?>
                </span>
            </button>
        </div>
    </div>
</nav>

<!-- MAIN CONTENT -->
<main class="main-content">

    <!-- FILTROS -->
    <section class="filter-section">
        <form class="filter-form" method="GET" id="filterForm">
            <div class="filter-group">
                <label for="search"><i class="fas fa-search"></i> Buscar</label>
                <input type="text" id="search" name="search" placeholder="¿Qué producto necesitas?" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-group">
                <label for="categoria"><i class="fas fa-tag"></i> Categoría</label>
                <select id="categoria" name="categoria">
                    <option value="">Todas las categorías</option>
                    <?php foreach($lista_categorias as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $categoria == $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filtrar</button>
                <a href="?<?php echo http_build_query(['search' => '', 'categoria' => '']); ?>" class="btn-clear"><i class="fas fa-times"></i> Limpiar</a>
            </div>
        </form>
    </section>

    <!-- RESULTADOS HEADER -->
    <div class="results-header">
        <span class="results-count">
            <strong><?php echo count($productos_padres); ?></strong> 
            <?php echo count($productos_padres) === 1 ? 'producto encontrado' : 'productos encontrados'; ?>
        </span>
        <span style="color: #64748b; font-size: 0.9rem;">
            <i class="fas fa-arrow-up"></i> Haz clic en "Ver más" para detalles
        </span>
    </div>

    <!-- PRODUCTOS GRID -->
    <div class="products-grid" id="productsGrid">
        <?php if (empty($productos_padres)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>No encontramos productos</h3>
                <p>Intenta con otros términos de búsqueda o selecciona otra categoría.</p>
                <a href="?" class="btn-filter" style="display: inline-block; margin-top: 20px; text-decoration: none;">
                    <i class="fas fa-redo"></i> Ver todos los productos
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($productos_padres as $p): ?>
                <div class="product-card" data-id="<?php echo $p['id']; ?>">
                    <div class="product-image">
                        <?php if (!empty($p['imagen_url']) && filter_var($p['imagen_url'], FILTER_VALIDATE_URL)): ?>
                            <img src="<?php echo htmlspecialchars($p['imagen_url']); ?>" alt="<?php echo htmlspecialchars($p['nombre']); ?>" loading="lazy">
                        <?php else: ?>
                            <div class="no-image">
                                <i class="fas fa-cube"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="product-category"><?php echo htmlspecialchars($p['categoria']); ?></div>
                    <h3 class="product-name"><?php echo htmlspecialchars($p['nombre']); ?></h3>
                    <p class="product-description"><?php echo htmlspecialchars($p['descripcion']); ?></p>
                    <div class="product-footer">
                        <span class="product-price">$<?php echo number_format($p['precio'], 2); ?></span>
                        <div class="product-actions">
                            <a href="detalles.php?id=<?php echo $p['id']; ?>" class="btn-detail">
                                <i class="fas fa-eye"></i> Ver más
                            </a>
                            <button class="btn-add" onclick="agregarCarrito(<?php echo $p['id']; ?>, this)">
                                <i class="fas fa-plus"></i> Agregar
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<!-- TOAST -->
<div class="toast" id="toast">
    <i class="fas fa-check-circle"></i>
    <span id="toastMessage">Producto agregado al carrito</span>
</div>

<script>
// CANTIDAD DE PRODUCTOS INICIAL
let cartCount = <?php echo count($_SESSION['carrito']); ?>;

// FUNCIÓN PARA ACTUALIZAR BADGE DEL CARRITO
function updateCartBadge() {
    const badge = document.getElementById('cartBadge');
    if (cartCount > 0) {
        badge.textContent = cartCount;
        badge.classList.remove('hidden');
        badge.style.animation = 'none';
        setTimeout(() => badge.style.animation = 'pop 0.3s ease', 10);
    } else {
        badge.classList.add('hidden');
    }
}

// FUNCIÓN PARA MOSTRAR TOAST
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toastMessage');
    const icon = toast.querySelector('i');
    
    toast.className = 'toast';
    toastMessage.textContent = message;
    
    if (type === 'success') {
        toast.classList.add('success');
        icon.className = 'fas fa-check-circle';
    } else {
        toast.classList.add('error');
        icon.className = 'fas fa-exclamation-circle';
    }
    
    toast.classList.add('show');
    clearTimeout(toast._timeout);
    toast._timeout = setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// FUNCIÓN PARA AGREGAR AL CARRITO (CON FETCH)
function agregarCarrito(id, btnElement) {
    // Mostrar estado de carga
    const originalHTML = btnElement.innerHTML;
    btnElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';
    btnElement.disabled = true;
    
    fetch('agregar_carrito.php?id=' + id, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) throw new Error('Error en el servidor');
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Actualizar contador
            cartCount = data.total_items || cartCount + 1;
            updateCartBadge();
            
            // Cambiar estilo del botón
            btnElement.classList.add('added');
            btnElement.innerHTML = '<i class="fas fa-check"></i> Añadido';
            
            // Mostrar toast
            showToast(data.message || '¡Producto agregado al carrito!', 'success');
            
            // Resetear botón después de 2 segundos
            setTimeout(() => {
                btnElement.classList.remove('added');
                btnElement.innerHTML = originalHTML;
                btnElement.disabled = false;
            }, 2000);
        } else {
            showToast(data.message || 'Error al agregar el producto', 'error');
            btnElement.innerHTML = originalHTML;
            btnElement.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error de conexión. Intenta de nuevo.', 'error');
        btnElement.innerHTML = originalHTML;
        btnElement.disabled = false;
    });
}

// FILTRADO EN TIEMPO REAL (sin recargar página)
let filterTimeout;

// Función para cargar productos vía AJAX
function filterProducts() {
    const search = document.getElementById('search').value.trim();
    const categoria = document.getElementById('categoria').value;
    
    // Construir URL con parámetros
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (categoria) params.append('categoria', categoria);
    params.append('ajax', '1');
    
    const url = '?' + params.toString();
    
    // Mostrar estado de carga
    const grid = document.getElementById('productsGrid');
    grid.innerHTML = `
        <div style="grid-column:1/-1; text-align:center; padding:60px 0;">
            <i class="fas fa-spinner fa-spin" style="font-size:2rem; color:var(--primary);"></i>
            <p style="color:#64748b; margin-top:15px;">Cargando productos...</p>
        </div>
    `;
    
    fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.text())
    .then(html => {
        // Extraer solo el grid de productos
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newGrid = doc.getElementById('productsGrid');
        
        if (newGrid) {
            grid.innerHTML = newGrid.innerHTML;
        } else {
            grid.innerHTML = html;
        }
        
        // Actualizar contador de resultados
        const countMatch = html.match(/<strong>(\d+)<\/strong>/);
        if (countMatch) {
            const countEl = document.querySelector('.results-header strong');
            if (countEl) countEl.textContent = countMatch[1];
        }
    })
    .catch(error => {
        console.error('Error:', error);
        grid.innerHTML = `
            <div style="grid-column:1/-1; text-align:center; padding:60px 0;">
                <i class="fas fa-exclamation-circle" style="font-size:2rem; color:var(--danger);"></i>
                <p style="color:#64748b; margin-top:15px;">Error al cargar productos</p>
            </div>
        `;
    });
}

// Event listeners para filtros en tiempo real
document.getElementById('search').addEventListener('input', function() {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(filterProducts, 500);
});

document.getElementById('categoria').addEventListener('change', function() {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(filterProducts, 300);
});

// Prevenir submit normal del formulario
document.getElementById('filterForm').addEventListener('submit', function(e) {
    e.preventDefault();
    filterProducts();
});

// Cargar productos si hay parámetros en la URL al iniciar
window.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    if (params.has('search') || params.has('categoria')) {
        // Ya está cargado desde el PHP
    }
});
</script>

</body>
</html>