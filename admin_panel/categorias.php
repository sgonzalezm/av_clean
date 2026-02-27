<?php

// Categorias
// Agregar, editar, eliminar categorías de productos
// Similar a catalogo_productos.php pero con campos específicos para categorías (nombre, descripción, imagen, etc.)
// También se pueden mostrar estadísticas como cuántos productos hay en cada categoría, etc.
// Requiere permisos de editor o admin

?>
<!DOCTYPE html>
<html>
<head>
    <title>Categorias</title>
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
    
    <div class="main">
        <div class="header">
            <div>
                <h1>Categorias</h1>
            </div>
        </div>
    </div>
            
</body>
</html>