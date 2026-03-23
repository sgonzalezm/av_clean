<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$id = $_GET['id'] ?? 0;
// 1. OBTENER EL PRODUCTO (Aseguramos traer id_formula_maestra)
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
    $id_formula_maestra = !empty($_POST['id_formula_maestra']) ? $_POST['id_formula_maestra'] : null; // Nuevo campo
    $imagen_url = $p['imagen_url']; 

    // Lógica de subida de archivo
    if (isset($_FILES['imagen_archivo']) && $_FILES['imagen_archivo']['error'] === UPLOAD_ERR_OK) {
        $ruta_destino = '../img/';
        if (!is_dir($ruta_destino)) {
            mkdir($ruta_destino, 0777, true);
        }

        $nombre_archivo = time() . '_' . basename($_FILES['imagen_archivo']['name']);
        $archivo_final = $ruta_destino . $nombre_archivo;
        
        $tipo_archivo = strtolower(pathinfo($archivo_final, PATHINFO_EXTENSION));
        if ($tipo_archivo == "png" || $tipo_archivo == "jpg" || $tipo_archivo == "jpeg") {
            if (move_uploaded_file($_FILES['imagen_archivo']['tmp_name'], $archivo_final)) {
                $imagen_url = $archivo_final; 
            } else {
                $error = "Error al mover el archivo al servidor.";
            }
        } else {
            $error = "Solo se permiten archivos PNG, JPG o JPEG.";
        }
    }

    if (!isset($error)) {
        // Actualizamos incluyendo id_formula_maestra
        $sql = "UPDATE productos SET nombre=?, descripcion=?, precio=?, imagen_url=?, categoria=?, id_formula_maestra=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$nombre, $descripcion, $precio, $imagen_url, $categoria, $id_formula_maestra, $id])) {
            header('Location: catalogo_productos.php?ok=2');
            exit;
        } else {
            $error = "Error al actualizar en la base de datos.";
        }
    }
}

// 2. CONSULTAR CATEGORÍAS
$stmt_cat = $pdo->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC");
$categorias_db = $stmt_cat->fetchAll();

// 3. CONSULTAR FÓRMULAS MAESTRAS (Para el nuevo selector)
$stmt_formulas = $pdo->query("SELECT id, nombre_formula FROM formulas_maestras ORDER BY nombre_formula ASC");
$formulas_db = $stmt_formulas->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Editar Producto | AHD Clean</title>
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
                    <label><i class="fas fa-tag"></i> Nombre del Producto</label>
                    <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($p['nombre']); ?>" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-flask"></i> Fórmula Química Base (Cerebro)</label>
                    <select name="id_formula_maestra" class="form-control">
                        <option value="">-- Sin fórmula (Solo comercial) --</option>
                        <?php foreach ($formulas_db as $form): ?>
                            <option value="<?php echo $form['id']; ?>" <?php echo ($p['id_formula_maestra'] == $form['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($form['nombre_formula']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #718096;">Asignar una fórmula permitirá calcular insumos en la sección de Lotes.</small>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="3" required><?php echo htmlspecialchars($p['descripcion']); ?></textarea>
                </div>

                <div style="display: flex; gap: 20px;">
                    <div class="form-group" style="flex: 1;">
                        <label><i class="fas fa-dollar-sign"></i> Precio de Venta</label>
                        <input type="number" name="precio" class="form-control" step="0.01" value="<?php echo $p['precio']; ?>" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label><i class="fas fa-folder"></i> Categoría</label>
                        <select name="categoria" class="form-control" required>
                            <?php foreach ($categorias_db as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo ($p['categoria'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="imagen_archivo"><i class="fas fa-image"></i> Imagen del Producto</label>
                    <input type="file" id="imagen_archivo" name="imagen_archivo" class="form-control" accept="image/*">
                    <?php if (!empty($p['imagen_url'])): ?>
                        <p style="font-size: 0.8rem; margin-top: 5px;">Ruta: <code><?php echo $p['imagen_url']; ?></code></p>
                    <?php endif; ?>
                </div>

                <div class="button-group" style="margin-top: 30px;">
                    <button type="submit" class="btn-guardar" style="background: #2b6cb0;"><i class="fas fa-save"></i> Guardar Cambios</button>
                    <a href="catalogo_productos.php" class="btn-cancelar">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    <script src="../js/admin.js"></script>
</body>
</html>