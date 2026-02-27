<?php

// Pedidos
// Agregar una sección para gestionar pedidos, con filtros por estado (pendiente, enviado, entregado) y opciones para actualizar el estado de cada pedido.
// Opcion para exportar pedidos a CSV o Excel para análisis externo.
// Opcion para exportar a PDF para imprimir facturas o resúmenes de pedidos.

?>
<!DOCTYPE html>
<html>
<head>
    <title>Pedidos</title>
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
                <h1>Pedidos</h1>
            </div>
        </div>
    </div>
            
</body>
</html>
