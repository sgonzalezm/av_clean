<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $precio = $_POST['precio'];
    $imagen_url = $_POST['imagen_url'];
    $categoria = $_POST['categoria'];
    
    $stmt = $pdo->prepare("INSERT INTO productos (nombre, descripcion, precio, imagen_url, categoria) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$nombre, $descripcion, $precio, $imagen_url, $categoria])) {
        header('Location: productos.php?ok=1');
        exit;
    } else {
        $error = "Error al guardar";
    }
}

// Obtener información del usuario para el sidebar
$user = $_SESSION['usuario'] ?? ['nombre' => 'Usuario', 'rol' => 'usuario'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Agregar Producto</title>
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
    <?php include 'sidebar.php'; ?>

    <!-- Contenido principal -->
    <div class="main">
        <!-- Header / Barra superior -->
        <div class="header">
            <div class="header-left">
                <h1><i class="fas fa-plus-circle"></i> Agregar Nuevo Producto</h1>
            </div>
            <div>
                <a href="productos.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver a Productos
                </a>
            </div>
        </div>

        <!-- Mensajes de error/success -->
        <?php if (isset($error)): ?>
            <div class="mensaje error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Formulario -->
        <div class="form-container">
            <form method="POST" class="slide-in">
                <div class="form-group">
                    <label for="nombre">
                        <i class="fas fa-tag"></i> Nombre del Producto
                    </label>
                    <input type="text" 
                           id="nombre" 
                           name="nombre" 
                           class="form-control" 
                           placeholder="Ej: Lámpara de escritorio LED"
                           required>
                </div>

                <div class="form-group">
                    <label for="descripcion">
                        <i class="fas fa-align-left"></i> Descripción
                    </label>
                    <textarea id="descripcion" 
                              name="descripcion" 
                              class="form-control" 
                              rows="4" 
                              placeholder="Describe las características del producto..."
                              required></textarea>
                </div>

                <div class="form-group">
                    <label for="precio">
                        <i class="fas fa-dollar-sign"></i> Precio
                    </label>
                    <input type="number" 
                           id="precio" 
                           name="precio" 
                           class="form-control" 
                           step="0.01" 
                           min="0" 
                           placeholder="0.00"
                           required>
                </div>

                <div class="form-group">
                    <label for="imagen_url">
                        <i class="fas fa-image"></i> URL de la Imagen
                    </label>
                    <input type="url" 
                           id="imagen_url" 
                           name="imagen_url" 
                           class="form-control" 
                           placeholder="https://ejemplo.com/imagen.jpg">
                    <div class="info">
                        <i class="fas fa-info-circle"></i>
                        Deja vacío si no tienes una imagen
                    </div>
                </div>

                <div class="form-group">
                    <label for="categoria">
                        <i class="fas fa-folder"></i> Categoría
                    </label>
                    <select id="categoria" name="categoria" class="form-control" required>
                        <option value="">Seleccionar categoría</option>
                        <option value="Hogar">🏠 Hogar</option>
                        <option value="Industrial">🏭 Industrial</option>
                        <option value="Automotriz">🚗 Automotriz</option>
                        <option value="Electrónica">💻 Electrónica</option>
                        <option value="Jardín">🌱 Jardín</option>
                    </select>
                </div>

                <div class="form-group checkbox">
                    <input type="checkbox" id="destacado" name="destacado">
                    <label for="destacado">Marcar como producto destacado</label>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-guardar">
                        <i class="fas fa-save"></i> Guardar Producto
                    </button>
                    <a href="productos.php" class="btn-cancelar">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/admin.js"></script>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }

        // Cerrar sidebar al hacer clic fuera en móvil
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const toggle = document.querySelector('.menu-toggle');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });
    </script>
</body>
</html>