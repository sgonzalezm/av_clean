<?php

// Editar correo 
// En caso de ser administrador que pueda subir de nivel de acceso a los usuarios, o eliminar usuarios
// Agregar un bloque de "Notificaciones" para configurar alertas por email, etc.
// Cambiar de status de pedido: pendiente, en proceso, enviado, entregado, cancelado. Agregar un bloque de "Pedidos" para gestionar esto.

?>
<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nueva_categoria'])) {
    $nombre_cat = $_POST['nombre_cat'];
    $stmt = $pdo->prepare("INSERT INTO categorias (nombre) VALUES (?)"); // Asumiendo que tienes una tabla 'categorias'
    if ($stmt->execute([$nombre_cat])) {
        echo "<script>alert('Categoría agregada con éxito'); window.location.href='configuracion.php';</script>";
    }
}

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
            <a href="usuarios.php" class="card-link">
                <div class="card clickable">
                    <i class="fas fa-users-cog fa-2x"></i>
                    <h3>Roles de Usuario</h3>
                    <p>Gestión de permisos y accesos al sistema.</p>
                    <span class="btn-editar-mini">Configurar <i class="fas fa-chevron-right"></i></span>
                </div>
            </a>
            <a href="perfil_impuestos.php" class="card-link">
                <div class="card clickable">
                    <i class="fas fa-file-invoice fa-2x"></i>
                    <h3>Perfil de Impuestos</h3>
                    <p>Configuración de RFC, sellos y folios.</p>
                    <span class="btn-editar-mini">Configurar <i class="fas fa-chevron-right"></i></span>
                </div>
            </a>

            <div class="card clickable" onclick="openModal()">
            <i class="fas fa-folder-plus fa-2x" style="color: #48bb78;"></i>
            <h3>Categorías</h3>
            <p>Agrega nuevas categorías para organizar tus productos químicos.</p>
            <span class="btn-editar-mini">Agregar Nueva <i class="fas fa-plus"></i></span>
        </div>

        <div id="modalCategoria" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2><i class="fas fa-tag"></i> Nueva Categoría</h2>
                    <span class="close" onclick="closeModal()">&times;</span>
                </div>
                <form method="POST">
                    <div class="form-group" style="padding: 20px 0;">
                        <label>Nombre de la categoría:</label>
                        <input type="text" name="nombre_cat" class="form-control" placeholder="Ej: Desinfectantes, Automotriz..." required style="width: 100%; padding: 10px; margin-top: 10px;">
                    </div>
                    <button type="submit" name="nueva_categoria" class="btn-guardar" style="width: 100%; background: #48bb78; color: white; border: none; padding: 12px; border-radius: 5px; cursor: pointer;">
                        <i class="fas fa-save"></i> Guardar Categoría
                    </button>
                </form>
            </div>
            </div>
        </div>
    </div>

    <style>
        .card-link { text-decoration: none; color: inherit; display: block; }
        .clickable:hover { transform: translateY(-5px); transition: 0.3s; border: 1px solid #4299e1; }
        .btn-editar-mini { display: inline-block; margin-top: 15px; color: #4299e1; font-weight: bold; font-size: 0.9rem; }
        .card h3 { margin: 15px 0 10px 0; }
        .card p { font-size: 0.85rem; color: #718096; line-height: 1.4; }

        /* Estilos del Modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fff; margin: 10% auto; padding: 25px; border-radius: 8px; width: 90%; max-width: 400px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); animation: slideDown 0.3s; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .close { font-size: 28px; font-weight: bold; cursor: pointer; color: #aaa; }
        .close:hover { color: #000; }

        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
    <script>
        function openModal() {
            document.getElementById("modalCategoria").style.display = "block";
        }

        function closeModal() {
            document.getElementById("modalCategoria").style.display = "none";
        }

        // Cerrar si el usuario hace clic fuera del contenido blanco
        window.onclick = function(event) {
            let modal = document.getElementById("modalCategoria");
            if (event.target == modal) {
                closeModal();
            }
        }
        </script>
    <script src="../js/admin.js"></script>
</body>
</html>