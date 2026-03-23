<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $precio = $_POST['precio'] ?? 0;
    $imagen_url = $_POST['imagen_archivo'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    $formula = $_POST['formula'] ?? 0;

    if (isset($_FILES['imagen_archivo']) && $_FILES['imagen_archivo']['error'] === UPLOAD_ERR_OK) {
        $ruta_destino = '../img/';
        
        if (!is_dir($ruta_destino)) {
            mkdir($ruta_destino, 0777, true);
        }

        // Nombre único para evitar duplicados
        $nombre_archivo = time() . '_' . basename($_FILES['imagen_archivo']['name']);
        $archivo_final = $ruta_destino . $nombre_archivo;
        
        $tipo_archivo = strtolower(pathinfo($archivo_final, PATHINFO_EXTENSION));
        $formatos_permitidos = ["png", "jpg", "jpeg", "webp"];

        if (in_array($tipo_archivo, $formatos_permitidos)) {
            if (move_uploaded_file($_FILES['imagen_archivo']['tmp_name'], $archivo_final)) {
                $imagen_url = $archivo_final; // Guardamos esta ruta en la BD
            } else {
                $error = "Error al subir el archivo al servidor.";
            }
        } else {
            $error = "Formato no permitido. Usa PNG, JPG o WebP.";
        }
    }

    // Lógica básica para evitar el error si la tabla productos requiere imagen_url
    $stmt = $pdo->prepare("INSERT INTO productos (nombre, descripcion, precio, imagen_url, categoria, id_formula_maestra) VALUES (?, ?, ?, ?, ?, ?)");
    
    try {
        if ($stmt->execute([$nombre, $descripcion, $precio, $imagen_url, $categoria, $formula])) {
            header('Location: catalogo_productos.php?ok=1');
            exit;
        } else {
            $error = "Error al guardar en la base de datos.";
        }
    } catch (PDOException $e) {
        $error = "Error crítico: " . $e->getMessage();
    }
}

// Obtener categorías de la base de datos
try {
    $stmt_cat = $pdo->query("SELECT * FROM categorias ORDER BY nombre ASC");
    $categorias_db = $stmt_cat->fetchAll();
} catch (PDOException $e) {
    $categorias_db = [];
    $error = "Error al cargar categorías: " . $e->getMessage();
}

// Obtener fórmulas maestras para el dropdown
try {
    $stmt_formulas = $pdo->query("SELECT id, nombre_formula FROM formulas_maestras ORDER BY nombre_formula ASC");
    $formulas_db = $stmt_formulas->fetchAll();
} catch (PDOException $e) {
    $formulas_db = [];
    $error = "Error al cargar fórmulas maestras: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Agregar Producto</title>
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
            <h1><i class="fas fa-plus-circle"></i> Agregar Nuevo Producto</h1>
            <a href="catalogo_productos.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
        </div>

        <?php if (isset($error)): ?>
            <div class="mensaje error" style="background:#fee2e2; color:#b91c1c; padding:15px; margin-bottom:20px; border-radius:8px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" enctype="multipart/form-data" class="slide-in">
                
                <div class="form-group">
                    <label for="nombre"><i class="fas fa-tag"></i> Nombre del Producto</label>
                    <input type="text" id="nombre" name="nombre" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="descripcion"><i class="fas fa-align-left"></i> Descripción</label>
                    <textarea id="descripcion" name="descripcion" class="form-control" rows="4" required></textarea>
                </div>

                <div class="form-group">
                    <label for="precio"><i class="fas fa-dollar-sign"></i> Precio</label>
                    <input type="number" id="precio" name="precio" class="form-control" step="0.01" min="0" required>
                </div>

                <div class="form-group">
                    <label for="imagen_archivo"><i class="fas fa-upload"></i> Seleccionar Imagen</label>
                    <input type="file" id="imagen_archivo" name="imagen_archivo" class="form-control" accept="image/*">
                    
                    <div class="info">
                        <i class="fas fa-info-circle"></i> 
                        Selecciona un archivo PNG o JPG.
                    </div>
                </div>

                <div class="form-group">
                    <label for="categoria"><i class="fas fa-folder"></i> Categoría</label>
                    <select id="categoria" name="categoria" class="form-control" required>
                        <option value="">Seleccionar categoría</option>
                        <?php foreach ($categorias_db as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['nombre']); ?>">
                                <?php echo htmlspecialchars($cat['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="formula"><i class="fas fa-folder"></i> Fórmula</label>
                    <select id="formula" name="formula" class="form-control" required>
                        <option value="">Seleccionar fórmula</option>
                        <?php foreach ($formulas_db as $form): ?>
                            <option value="<?php echo htmlspecialchars($form['id']); ?>">
                                <?php echo htmlspecialchars($form['nombre_formula']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-guardar">
                        <i class="fas fa-save"></i> Guardar Producto
                    </button>
                    <a href="catalogo_productos.php" class="btn-cancelar">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/admin.js"></script>
</body>
</html>