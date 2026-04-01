<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// Procesar Nueva Categoría
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nueva_categoria'])) {
    $nombre_cat = $_POST['nombre_cat'];
    $stmt = $pdo->prepare("INSERT INTO categorias (nombre) VALUES (?)");
    if ($stmt->execute([$nombre_cat])) {
        echo "<script>alert('Categoría agregada con éxito'); window.location.href='configuracion.php';</script>";
    }
}

// Aquí podrías procesar la configuración de correo/notificaciones en el futuro
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Configuración | AHD Clean</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        :root {
            --primary: #3182ce;
            --success: #38a169;
            --warning: #ecc94b;
            --danger: #e53e3e;
            --text-main: #2d3748;
            --text-muted: #718096;
            --bg-card: #ffffff;
        }

        .section-title { 
            margin: 30px 0 15px 0; 
            font-size: 1.1rem; 
            color: var(--text-muted); 
            text-transform: uppercase; 
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title::after { content: ""; flex: 1; height: 1px; background: #e2e8f0; }

        .config-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); 
            gap: 20px; 
        }

        .card-config {
            background: var(--bg-card);
            padding: 25px;
            border-radius: 15px;
            border: 1px solid #edf2f7;
            transition: all 0.3s ease;
            position: relative;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .card-config:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border-color: var(--primary);
        }

        .icon-box {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 1.4rem;
        }

        /* Colores de Iconos */
        .bg-gold { background: #fefcbf; color: #b7791f; }
        .bg-blue { background: #ebf8ff; color: #3182ce; }
        .bg-green { background: #f0fff4; color: #38a169; }
        .bg-purple { background: #faf5ff; color: #805ad5; }
        .bg-orange { background: #fffaf0; color: #dd6b20; }

        .card-config h3 { font-size: 1.15rem; color: var(--text-main); margin-bottom: 8px; }
        .card-config p { font-size: 0.9rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 20px; }

        .action-link { 
            font-size: 0.85rem; 
            font-weight: 700; 
            color: var(--primary); 
            display: flex; 
            align-items: center; 
            gap: 5px; 
        }

        /* Estilos del Modal Reutilizados y Mejorados */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(3px); }
        .modal-content { background: #fff; margin: 10% auto; padding: 30px; border-radius: 15px; width: 90%; max-width: 450px; animation: slideDown 0.4s ease; }
        .form-control-modern { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; margin-top: 10px; font-size: 1rem; }
        .btn-full { width: 100%; padding: 12px; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; margin-top: 15px; display: flex; align-items: center; justify-content: center; gap: 10px; }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header">
            <div>
                <h1><i class="fas fa-cog"></i> Configuración General</h1>
                <p style="color: var(--text-muted);">Ajusta los parámetros globales de AHD Clean.</p>
            </div>
        </div>

        <h2 class="section-title">Negocio y Ventas</h2>
        <div class="config-grid">
            <a href="niveles_ventas.php" class="card-config">
                <div>
                    <div class="icon-box bg-gold"><i class="fas fa-trophy"></i></div>
                    <h3>Metas y Niveles</h3>
                    <p>Umbrales de comisiones para Bronce, Plata y Oro según volumen de venta.</p>
                </div>
                <span class="action-link">Configurar Niveles <i class="fas fa-chevron-right"></i></span>
            </a>

            <a href="perfil_impuestos.php" class="card-config">
                <div>
                    <div class="icon-box bg-purple"><i class="fas fa-file-invoice"></i></div>
                    <h3>Perfil Fiscal</h3>
                    <p>Configuración de RFC, razón social y datos para facturación electrónica.</p>
                </div>
                <span class="action-link">Editar Datos <i class="fas fa-chevron-right"></i></span>
            </a>

            <div class="card-config" onclick="openModal()" style="cursor: pointer;">
                <div>
                    <div class="icon-box bg-green"><i class="fas fa-tags"></i></div>
                    <h3>Categorías</h3>
                    <p>Organiza tus productos químicos (Desinfectantes, Limpieza Carrocería, etc).</p>
                </div>
                <span class="action-link">Añadir Categoría <i class="fas fa-plus"></i></span>
            </div>
        </div>

        <h2 class="section-title">Seguridad y Accesos</h2>
        <div class="config-grid">
            <a href="usuarios.php" class="card-config">
                <div>
                    <div class="icon-box bg-blue"><i class="fas fa-user-shield"></i></div>
                    <h3>Roles de Usuario</h3>
                    <p>Administra quién puede ver costos de producción o editar fórmulas maestras.</p>
                </div>
                <span class="action-link">Gestionar Staff <i class="fas fa-chevron-right"></i></span>
            </a>
        </div>

        <h2 class="section-title">Comunicación y Alertas</h2>
        <div class="config-grid">
            <a href="configurar_correo.php" class="card-config">
                <div>
                    <div class="icon-box bg-orange"><i class="fas fa-envelope-open-text"></i></div>
                    <h3>Servidor de Correo</h3>
                    <p>Configura el SMTP para envío de facturas y órdenes de compra automáticas.</p>
                </div>
                <span class="action-link">Configurar SMTP <i class="fas fa-chevron-right"></i></span>
            </a>

            <a href="alertas_notificaciones.php" class="card-config">
                <div>
                    <div class="icon-box bg-red" style="background: #fff5f5; color: #e53e3e;"><i class="fas fa-bell"></i></div>
                    <h3>Notificaciones</h3>
                    <p>Alertas de stock bajo, pedidos nuevos o vencimientos de facturas.</p>
                </div>
                <span class="action-link">Gestionar Alertas <i class="fas fa-chevron-right"></i></span>
            </a>
        </div>
    </div>

    <div id="modalCategoria" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-tag"></i> Nueva Categoría</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST">
                <div style="padding: 15px 0;">
                    <label style="font-weight: bold; color: var(--text-main);">Nombre del grupo:</label>
                    <input type="text" name="nombre_cat" class="form-control-modern" placeholder="Ej: Jabones Industriales" required>
                </div>
                <button type="submit" name="nueva_categoria" class="btn-full" style="background: var(--success); color: white;">
                    <i class="fas fa-save"></i> Guardar Categoría
                </button>
            </form>
        </div>
    </div>

    <script>
        function openModal() { document.getElementById("modalCategoria").style.display = "block"; }
        function closeModal() { document.getElementById("modalCategoria").style.display = "none"; }
        window.onclick = function(event) {
            if (event.target == document.getElementById("modalCategoria")) closeModal();
        }
    </script>
    <script src="../js/admin.js"></script>
</body>
</html>