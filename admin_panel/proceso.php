<?php

// Procesos de negocio
// Flujo de trabajo, tareas, asignaciones, seguimiento de procesos internos (ej. aprobación de productos, gestión de pedidos, etc.)
// Requiere permisos de editor o admin
// menu de procesos, con submenús para cada tipo de proceso (ej. aprobación de productos, gestión de pedidos, etc.)
// Cada submenú muestra una lista de procesos en curso, con detalles y opciones para gestionar cada proceso (ej. aprobar/rechazar, asignar a un usuario, marcar como completado, etc.)


?>
<!DOCTYPE html>
<html>
<head>
    <title>Proceso</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <!-- Botón toggle para móvil -->
    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>

    <!-- Sidebar (menú lateral) -->
    <?php include 'sidebar.php'; ?>   
    
    <div class="main">
        <div class="header">
            <div>
                <h1>Proceso</h1>
            </div>
        </div>
    </div>
          
    <script src="../js/admin.js"></script>
</body>
</html>