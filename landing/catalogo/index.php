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
    <style>
        /* Estilos adicionales para los filtros */
        .filtros {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .filtro-grupo {
            flex: 1;
            min-width: 200px;
        }
        
        .filtro-grupo label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #2d3748;
        }
        
        .filtro-grupo input,
        .filtro-grupo select {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .filtro-grupo input:focus,
        .filtro-grupo select:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }
        
        .btn-filtro {
            background: #4299e1;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: background 0.3s;
        }
        
        .btn-filtro:hover {
            background: #3182ce;
        }
        
        .btn-limpiar {
            background: #718096;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }
        
        .btn-limpiar:hover {
            background: #4a5568;
        }
        
        .resultados-info {
            margin-bottom: 20px;
            padding: 10px;
            background: #ebf8ff;
            border-radius: 4px;
            color: #2c5282;
        }
        
        @media (max-width: 768px) {
            .filtros {
                flex-direction: column;
                gap: 10px;
            }
            
            .filtro-grupo {
                width: 50%;
                padding: 10px;
            }
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
</body>
</html>