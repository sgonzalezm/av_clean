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
?>
<!DOCTYPE html>
<html>
<head>
    <title>Agregar Producto</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="form-container">
        <h1>➕ Agregar Producto</h1>
        
        <?php if (isset($error)) echo "<p style='color:red'>$error</p>"; ?>
        
        <form method="POST">
            <label>Nombre del Producto</label>
            <input type="text" name="nombre" required>
            
            <label>Descripción</label>
            <textarea name="descripcion" rows="4" required></textarea>
            
            <label>Precio ($)</label>
            <input type="number" step="0.01" name="precio" required>
            
            <label>URL de la Imagen</label>
            <input type="text" name="imagen_url" placeholder="https://ejemplo.com/imagen.jpg">
            
            <label>Categoría</label>
            <select name="categoria" required>
                <option value="">Seleccionar</option>
                <option value="Hogar">Hogar</option>
                <option value="Industrial">Industrial</option>
                <option value="Automotriz">Automotriz</option>
            </select>
            
            <button type="submit">Guardar Producto</button>
            <a href="catalogo_productos.php"><button type="button" class="cancelar">Cancelar</button></a>
        </form>
    </div>
</body>
</html>