<?php

// Cambio de password de usuario
// Editar correo 
// En caso de ser administrador que pueda subir de nivel de acceso a los usuarios, o eliminar usuarios
// Agregar un bloque de "Configuración General" para cosas como: nombre de la tienda
// Agregar un bloque de "Seguridad" para cosas como: cambiar contraseña del admin actual, configurar 2FA, etc.
// Agregar un bloque de "Notificaciones" para configurar alertas por email, etc.
// Agregar un bloque de "Integraciones" para configurar APIs externas, etc.
// Agregar un bloque de "Personalización" para configurar el tema del panel, colores, etc.
// Agregar un bloque de "Logs" para ver actividad reciente, errores, etc.
// Agregar un bloque de "Backups" para gestionar copias de seguridad de la base de datos, etc.

?>
<!DOCTYPE html>
<html>
<head>
    <title>Configuracion</title>
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
                <h1>Configuracion</h1>
            </div>
        </div>
    </div>
            
            
</body>
</html>

