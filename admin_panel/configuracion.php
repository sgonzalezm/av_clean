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
// Editar los niveles de objetivos de ventas: Nivel oro, plata, bronce. Agregar recompensas para cada nivel. Agregar un bloque de "Recompensas" para configurar esto.
// Cambiar de status de pedido: pendiente, en proceso, enviado, entregado, cancelado. Agregar un bloque de "Pedidos" para gestionar esto.


?>
<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Configuración General</title>
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
            <h1><i class="fas fa-tools"></i> Panel de Configuración</h1>
        </div>

        <div class="metricas-grid">
            <a href="niveles_ventas.php" class="card-link">
                <div class="card clickable">
                    <i class="fas fa-trophy fa-2x" style="color: #ecc94b;"></i>
                    <h3>Metas y Niveles</h3>
                    <p>Configura umbrales de Bronce, Plata, Oro y sus comisiones.</p>
                    <span class="btn-editar-mini">Configurar <i class="fas fa-chevron-right"></i></span>
                </div>
            </a>

            <div class="card" style="opacity: 0.6; cursor: not-allowed;">
                <i class="fas fa-users-cog fa-2x"></i>
                <h3>Roles de Usuario</h3>
                <p>Próximamente: Gestión de permisos y accesos al sistema.</p>
            </div>

            <div class="card" style="opacity: 0.6; cursor: not-allowed;">
                <i class="fas fa-file-invoice fa-2x"></i>
                <h3>Datos de Facturación</h3>
                <p>Próximamente: Configuración de RFC, sellos y folios.</p>
            </div>
        </div>
    </div>

    <style>
        .card-link { text-decoration: none; color: inherit; display: block; }
        .clickable:hover { transform: translateY(-5px); transition: 0.3s; border: 1px solid #4299e1; }
        .btn-editar-mini { display: inline-block; margin-top: 15px; color: #4299e1; font-weight: bold; font-size: 0.9rem; }
        .card h3 { margin: 15px 0 10px 0; }
        .card p { font-size: 0.85rem; color: #718096; line-height: 1.4; }
    </style>
    <script src="../js/admin.js"></script>
</body>
</html>