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

// 2. PROCESAR FILTROS DE BÚSQUEDA
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
    <title>Catálogo de Productos | AHD Clean</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/store.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Ajustes para que el NAV sea flexible y no se amontone */
        .nav-flex { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
        .nav-right { display: flex; align-items: center; gap: 12px; }
        
        /* Botón de usuario / Mi Cuenta */
        .btn-user-nav { 
            background: rgba(255,255,255,0.1); 
            color: white; 
            padding: 8px 12px; 
            border-radius: 8px; 
            text-decoration: none; 
            font-size: 0.85rem; 
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .btn-user-nav:hover { background: rgba(255,255,255,0.2); }

        /* Modal y Timeline */
        .modal-rastreo { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); }
        .modal-content { background: white; width: 90%; max-width: 450px; margin: 8% auto; padding: 30px; border-radius: 15px; position: relative; font-family: 'Inter', sans-serif; }
        .close-btn { position: absolute; right: 20px; top: 15px; cursor: pointer; font-size: 1.5rem; color: #a0aec0; }
        .rastreo-input-group { display: flex; gap: 10px; margin-top: 20px; }
        .rastreo-input-group input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        .timeline { margin-top: 30px; border-left: 3px solid #edf2f7; margin-left: 20px; padding-left: 30px; display: none; }
        .step { margin-bottom: 25px; position: relative; color: #cbd5e1; }
        .step i { position: absolute; left: -42px; background: white; font-size: 1.2rem; }
        .step.active { color: #2d3748; font-weight: 700; }
        .step.active i { color: #38a169; }

        @media (max-width: 600px) {
            .btn-rastreo-nav span, .btn-user-nav span { display: none; } /* En móvil solo iconos */
        }
    </style>
</head>
<body>
    <div class="nav">
        <div class="container nav-flex">
            <div>
                <a href="/">← <span>Volver</span></a>
            </div>
            
            <div class="nav-right">
                <button class="btn-rastreo-nav" onclick="mostrarModalRastreo()">
                    <i class="fas fa-truck"></i> <span>Rastrear</span>
                </button>

                <?php if ($cliente_logueado): ?>
                    <a href="ver_carrito.php" class="btn-user-nav">
                        <i class="fas fa-user-circle"></i> <span>Hola, <?php echo $nombre_cliente; ?></span>
                    </a>
                <?php else: ?>
                    <a href="ver_carrito.php" class="btn-user-nav">
                        <i class="fas fa-sign-in-alt"></i> <span>Entrar</span>
                    </a>
                <?php endif; ?>

                <a href="ver_carrito.php" class="carrito-link">
                    <i class="fas fa-shopping-cart"></i>
                    <span id="carrito-count" class="badge">
                        <?php echo isset($_SESSION['carrito']) ? array_sum($_SESSION['carrito']) : 0; ?>
                    </span>
                </a>
            </div>
        </div>
    </div>

    <div class="container" style="margin-top: 40px;">
        <h2 style="text-align: center; margin-bottom: 30px;">Nuestros Productos</h2>
        
        <div class="filtros">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; justify-content: center; background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 40px;">
                <div class="filtro-grupo">
                    <label>🔍 Buscar:</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nombre...">
                </div>
                
                <div class="filtro-grupo">
                    <label>📂 Sección:</label>
                    <select name="categoria">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($categoria == $cat) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="align-self: flex-end;">
                    <button type="submit" class="btn-filtro" style="background: #1a365d; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer;">Filtrar</button>
                </div>
            </form>
        </div>
        
        <div class="productos" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px;">
            <?php if (count($productos) > 0): ?>
                <?php foreach ($productos as $p): ?>
                <div class="producto" style="border: 1px solid #edf2f7; padding: 20px; border-radius: 15px; display: flex; flex-direction: column; background: white;">
                    <div style="height: 180px; display: flex; align-items: center; justify-content: center; background: #f7fafc; border-radius: 10px; margin-bottom: 15px; overflow: hidden;">
                        <?php if (!empty($p['imagen_url'])): ?>
                            <img src="<?php echo htmlspecialchars($p['imagen_url']); ?>" alt="<?php echo htmlspecialchars($p['nombre']); ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                        <?php else: ?>
                            <span style="color: #cbd5e1;">Sin imagen</span>
                        <?php endif; ?>
                    </div>
                    
                    <span style="font-size: 0.75rem; color: #3182ce; font-weight: 700; text-transform: uppercase;"><?php echo htmlspecialchars($p['categoria']); ?></span>
                    <h3 style="margin: 10px 0; font-size: 1.1rem;"><?php echo htmlspecialchars($p['nombre']); ?></h3>
                    <div style="font-size: 1.3rem; font-weight: 700; margin-bottom: 15px; color: #2d3748;">$<?php echo number_format($p['precio'], 2); ?></div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: auto;">
                        <a href="detalles.php?id=<?php echo $p['id']; ?>" class="btn" style="background: #edf2f7; color: #2d3748; text-align: center; padding: 10px; border-radius: 8px; text-decoration: none; font-size: 0.85rem; font-weight: 600;">Ver más</a>
                        <button class="btn-agregar-ajax" data-id="<?php echo $p['id']; ?>" style="background: #002bff; color: white; border:none; padding: 10px; border-radius: 8px; cursor: pointer; font-size: 0.85rem; font-weight: 600;">Agregar</button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 50px;">📦 No hay resultados.</div>
            <?php endif; ?>
        </div>
    </div>

    <div id="modalRastreo" class="modal-rastreo">
        <div class="modal-content">
            <span class="close-btn" onclick="cerrarModalRastreo()">&times;</span>
            <h3>Rastrear Pedido</h3>
            <div class="rastreo-input-group">
                <input type="number" id="inputPedido" placeholder="ID de pedido...">
                <button onclick="buscarRastreo()" style="background: #1a365d; color: white; border: none; padding: 10px 15px; border-radius: 8px; cursor: pointer;">Buscar</button>
            </div>
            <div id="timelineRastreo" class="timeline">
                <div id="s1" class="step"><i class="fas fa-check-circle"></i> Recibido</div>
                <div id="s2" class="step"><i class="fas fa-flask"></i> Preparación</div>
                <div id="s3" class="step"><i class="fas fa-truck"></i> En Camino</div>
                <div id="s4" class="step"><i class="fas fa-home"></i> Entregado</div>
            </div>
            <div id="errorRastreo" style="display:none; color: #c53030; margin-top: 15px;"></div>
        </div>
    </div>

    <script>
    function mostrarModalRastreo() { document.getElementById('modalRastreo').style.display = 'block'; }
    function cerrarModalRastreo() { 
        document.getElementById('modalRastreo').style.display = 'none';
        document.getElementById('timelineRastreo').style.display = 'none';
        document.getElementById('errorRastreo').style.display = 'none';
    }

    function buscarRastreo() {
        const id = document.getElementById('inputPedido').value;
        if(!id) return;
        fetch(`obtener_status.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                if(data.error) {
                    document.getElementById('errorRastreo').textContent = "No encontrado.";
                    document.getElementById('errorRastreo').style.display = 'block';
                    document.getElementById('timelineRastreo').style.display = 'none';
                } else {
                    document.getElementById('errorRastreo').style.display = 'none';
                    document.getElementById('timelineRastreo').style.display = 'block';
                    actualizarTimeline(data.status);
                }
            });
    }

    function actualizarTimeline(status) {
        const pasos = ['s1', 's2', 's3', 's4'];
        pasos.forEach(p => document.getElementById(p).classList.remove('active'));
        document.getElementById('s1').classList.add('active');
        if(['Confirmado', 'En Preparación'].includes(status)) document.getElementById('s2').classList.add('active');
        if(['En Camino', 'Enviado'].includes(status)) {
            document.getElementById('s2').classList.add('active');
            document.getElementById('s3').classList.add('active');
        }
        if(['Completado', 'Entregado'].includes(status)) {
            document.getElementById('s2').classList.add('active');
            document.getElementById('s3').classList.add('active');
            document.getElementById('s4').classList.add('active');
        }
    }

    function actualizarContadorCarrito() {
        fetch('obtener_total_carrito.php')
            .then(res => res.text())
            .then(total => {
                document.getElementById('carrito-count').textContent = total.trim() || "0";
            });
    }

    document.querySelectorAll('.btn-agregar-ajax').forEach(boton => {
        boton.addEventListener('click', function(e) {
            const id = this.getAttribute('data-id');
            fetch(`agregar_carrito.php?id=${id}`).then(() => {
                actualizarContadorCarrito();
                this.textContent = "¡Añadido!";
                setTimeout(() => this.textContent = "Agregar", 1000);
            });
        });
    });
    </script>
</body>
</html>