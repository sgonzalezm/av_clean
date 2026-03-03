<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$id = $_GET['id'] ?? 0;
$producto = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
$producto->execute([$id]);
$p = $producto->fetch();

if (!$p) {
    header('Location: catalogo_productos.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $precio = $_POST['precio'];
    $categoria = $_POST['categoria'];
    $imagen_url = $p['imagen_url']; // Mantener la actual por defecto

    // Lógica de subida de archivo
    if (isset($_FILES['imagen_archivo']) && $_FILES['imagen_archivo']['error'] === UPLOAD_ERR_OK) {
        $ruta_destino = '../img/';
        
        // Crear carpeta si no existe
        if (!is_dir($ruta_destino)) {
            mkdir($ruta_destino, 0777, true);
        }

        $nombre_archivo = time() . '_' . basename($_FILES['imagen_archivo']['name']);
        $archivo_final = $ruta_destino . $nombre_archivo;
        
        // Validar que sea imagen (puedes añadir más extensiones si quieres)
        $tipo_archivo = strtolower(pathinfo($archivo_final, PATHINFO_EXTENSION));
        if ($tipo_archivo == "png" || $tipo_archivo == "jpg" || $tipo_archivo == "jpeg") {
            if (move_uploaded_file($_FILES['imagen_archivo']['tmp_name'], $archivo_final)) {
                $imagen_url = $archivo_final; // Guardamos la ruta relativa en la BD
            } else {
                $error = "Error al mover el archivo al servidor.";
            }
        } else {
            $error = "Solo se permiten archivos PNG, JPG o JPEG.";
        }
    }

    if (!isset($error)) {
        $stmt = $pdo->prepare("UPDATE productos SET nombre=?, descripcion=?, precio=?, imagen_url=?, categoria=? WHERE id=?");
        if ($stmt->execute([$nombre, $descripcion, $precio, $imagen_url, $categoria, $id])) {
            header('Location: catalogo_productos.php?ok=2');
            exit;
        } else {
            $error = "Error al actualizar en la base de datos.";
        }
    }
}

$user = $_SESSION['usuario'] ?? ['nombre' => 'Usuario', 'rol' => 'usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Editar Producto | Panel Admin</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header">
            <div class="header-left">
                <h1><i class="fas fa-edit"></i> Editar Producto</h1>
            </div>
            <a href="catalogo_productos.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
        </div>

        <?php if (isset($error)): ?>
            <div class="mensaje error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" enctype="multipart/form-data" class="slide-in">
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Nombre</label>
                    <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($p['nombre']); ?>" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="4" required><?php echo htmlspecialchars($p['descripcion']); ?></textarea>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-dollar-sign"></i> Precio</label>
                    <input type="number" name="precio" class="form-control" step="0.01" value="<?php echo $p['precio']; ?>" required>
                </div>

                <div class="form-group">
                    <label for="imagen_archivo">
                        <i class="fas fa-upload"></i> Subir Nueva Imagen (PNG/JPG)
                    </label>
                    <input type="file" id="imagen_archivo" name="imagen_archivo" class="form-control" accept="image/png, image/jpeg">
                    
                    <div class="info">
                        <?php if (!empty($p['imagen_url'])): ?>
                            <i class="fas fa-image"></i> Imagen actual: 
                            <a href="<?php echo $p['imagen_url']; ?>" target="_blank" style="color: var(--primary-color)">Ver actual</a>
                        <?php else: ?>
                            <i class="fas fa-info-circle"></i> No hay imagen asignada.
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-folder"></i> Categoría</label>
                    <select name="categoria" class="form-control" required>
                        <option value="Hogar" <?php echo $p['categoria']=='Hogar'?'selected':''; ?>>🏠 Hogar</option>
                        <option value="Industrial" <?php echo $p['categoria']=='Industrial'?'selected':''; ?>>🏭 Industrial</option>
                        <option value="Automotriz" <?php echo $p['categoria']=='Automotriz'?'selected':''; ?>>🚗 Automotriz</option>
                    </select>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-guardar"><i class="fas fa-save"></i> Actualizar</button>
                    <a href="catalogo_productos.php" class="btn-cancelar"><i class="fas fa-times"></i> Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    <script src="../js/admin.js"></script>
</body>
</html>