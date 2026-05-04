<?php
// 1. CONEXIÓN A LA BASE DE DATOS Y SESIÓN
include '../includes/conexion.php';
session_start(); 

$cliente_logueado = isset($_SESSION['cliente_id']);
$nombre_cliente = $cliente_logueado ? explode(' ', $_SESSION['cliente_nombre'])[0] : '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// 2. PROCESAR FILTROS
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';

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

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt_categorias = $pdo->query("SELECT DISTINCT categoria FROM productos WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria");
$categorias = $stmt_categorias->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Catálogo AHD Clean | Innovación en Limpieza</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a365d;
            --accent: #002bff;
            --bg-light: #f8fafc;
            --text-dark: #2d3748;
            --white: #ffffff;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            margin: 0;
            color: var(--text-dark);
        }

        /* --- NAVEGACIÓN FULL WIDTH --- */
        .nav-modern {
            background: var(--primary);
            padding: 15px 4%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            color: white;
        }

        .logo-img { height: 45px; }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-nav {
            background: rgba(255,255,255,0.1);
            color: white;
            padding: 10px 18px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .btn-nav:hover { background: var(--accent); transform: translateY(-2px); }

        .badge-cart {
            background: #ff3b30;
            color: white;
            padding: 2px 7px;
            border-radius: 50%;
            font-size: 0.75rem;
            position: absolute;
            top: -5px;
            right: -10px;
        }

        /* --- LAYOUT --- */
        .main-content {
            padding: 40px 4%;
            max-width: 1800px;
            margin: 0 auto;
        }

        .filter-bar {
            background: white;
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 50px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            align-items: flex-end;
        }

        .filter-group { display: flex; flex-direction: column; gap: 8px; }
        .filter-group label { font-size: 0.8rem; font-weight: 700; color: #64748b; text-transform: uppercase; }
        .filter-group input, .filter-group select { padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 10px; min-width: 200px; }

        /* --- GRID PRODUCTOS --- */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
        }

        .product-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            transition: all 0.4s ease;
            display: flex;
            flex-direction: column;
            border: 1px solid #f1f5f9;
        }

        .product-card:hover { transform: translateY(-10px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }

        .image-container {
            height: 220px;
            background: #f8fafc;
            border-radius: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .image-container img { max-width: 90%; max-height: 90%; object-fit: contain; }

        .price-tag { font-size: 1.5rem; font-weight: 700; color: var(--text-dark); margin-bottom: 20px; }

        .card-buttons { display: grid; grid-template-columns: 1fr 1.2fr; gap: 10px; margin-top: auto; }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            text-decoration: none;
            padding: 12px;
            border-radius: 10px;
            text-align: center;
            font-weight: 600;
        }

        /* ESTE ES EL BOTÓN CLAVE */
        .btn-agregar-ajax {
            background: var(--accent);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
        }

        .btn-agregar-ajax:hover { opacity: 0.9; }

        /* MODALES */
        .modal-rastreo { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); }
        .modal-content { background: white; width: 90%; max-width: 480px; margin: 5% auto; padding: 35px; border-radius: 25px; position: relative; }
    </style>
</head>
<body>

    <nav class="nav-modern">
        <a href="/" class="brand">
            <img src="../css/Logo_AHD_Clean.png" width="80" height="80" alt="Logo AHD Clean" class="logo-img"> 
            <span style="font-weight: 800; font-size: 1.2rem;">AHD <span style="font-weight: 300;">CLEAN</span></span>
        </a>

        <div class="nav-actions">
            <button class="btn-nav" onclick="mostrarModalRastreo()">
                <i class="fas fa-truck-fast"></i> <span>Rastrear</span>
            </button>

            <a href="ver_carrito.php" class="btn-nav" style="position: relative; background: var(--white); color: var(--primary);">
                <i class="fas fa-cart-shopping"></i>
                <span id="carrito-count" class="badge-cart">
                    <?php echo isset($_SESSION['carrito']) ? array_sum($_SESSION['carrito']) : 0; ?>
                </span>
            </a>
        </div>
    </nav>

    <main class="main-content">
        <div class="filter-bar">
            <form method="GET" style="display: contents;">
                <div class="filter-group">
                    <label>Buscar</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <label>Categoría</label>
                    <select name="categoria">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($categoria == $cat) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-nav" style="background: var(--primary);">Filtrar</button>
            </form>
        </div>

        <div class="products-grid">
            <?php foreach ($productos as $p): ?>
            <div class="product-card">
                <div class="image-container">
                    <?php if (!empty($p['imagen_url'])): ?>
                        <img src="<?php echo htmlspecialchars($p['imagen_url']); ?>">
                    <?php else: ?>
                        <i class="fas fa-box-open" style="font-size: 3rem; color: #e2e8f0;"></i>
                    <?php endif; ?>
                </div>
                
                <h3 style="margin-bottom: 10px;"><?php echo htmlspecialchars($p['nombre']); ?></h3>
                <div class="price-tag">$<?php echo number_format($p['precio'], 2); ?></div>
                
                <div class="card-buttons">
                    <a href="detalles.php?id=<?php echo $p['id']; ?>" class="btn-secondary">Ver más</a>
                    <button class="btn-agregar-ajax" data-id="<?php echo $p['id']; ?>">
                        <i class="fas fa-plus"></i> Agregar
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>

    <script>
    // 1. FUNCIÓN PARA ACTUALIZAR EL CONTADOR
    function actualizarContadorCarrito() {
        fetch('obtener_total_carrito.php')
            .then(res => res.text())
            .then(total => {
                const badge = document.getElementById('carrito-count');
                if(badge) badge.textContent = total.trim() || "0";
            })
            .catch(err => console.error("Error actualizando contador:", err));
    }

    // 2. EVENT LISTENER PARA LOS BOTONES (Corregido para la nueva clase)
    document.addEventListener('click', function(e) {
        // Buscamos si el elemento clickeado es el botón de agregar
        if (e.target && (e.target.classList.contains('btn-agregar-ajax') || e.target.parentElement.classList.contains('btn-agregar-ajax'))) {
            const boton = e.target.classList.contains('btn-agregar-ajax') ? e.target : e.target.parentElement;
            const id = boton.getAttribute('data-id');
            
            if(id) {
                fetch(`agregar_carrito.php?id=${id}`)
                    .then(() => {
                        actualizarContadorCarrito();
                        const textoOriginal = boton.innerHTML;
                        boton.innerHTML = "<i class='fas fa-check'></i> ¡Añadido!";
                        boton.style.background = "#28a745";
                        
                        setTimeout(() => {
                            boton.innerHTML = textoOriginal;
                            boton.style.background = "";
                        }, 1000);
                    })
                    .catch(err => alert("Error al agregar al carrito"));
            }
        }
    });

    function mostrarModalRastreo() { document.getElementById('modalRastreo').style.display = 'block'; }
    function cerrarModalRastreo() { document.getElementById('modalRastreo').style.display = 'none'; }
    </script>
</body>
</html>