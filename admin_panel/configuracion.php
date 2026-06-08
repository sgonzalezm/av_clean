<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// ==========================================
// 1. PROCESAMIENTO DE CATEGORÍAS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nueva_categoria'])) {
    $nombre_cat = trim($_POST['nombre_cat']);
    if (!empty($nombre_cat)) {
        $stmt = $pdo->prepare("INSERT INTO categorias (nombre) VALUES (?)");
        if ($stmt->execute([$nombre_cat])) {
            echo "<script>alert('Categoría agregada con éxito'); window.location.href='configuracion.php';</script>";
            exit;
        }
    }
}

// ==========================================
// 2. PROCESAMIENTO DE TIPOS DE CLIENTES
// ==========================================
// Agregar Nuevo Tipo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nuevo_tipo_cliente'])) {
    $nombre_tipo = trim($_POST['nombre_tipo']);
    $descuento = floatval($_POST['descuento_porcentaje'] ?? 0);

    if (!empty($nombre_tipo)) {
        $stmt = $pdo->prepare("INSERT INTO tipos_cliente (nombre, descuento_porcentaje) VALUES (?, ?)");
        if ($stmt->execute([$nombre_tipo, $descuento])) {
            echo "<script>alert('Tipo de cliente agregado con éxito'); window.location.href='configuracion.php';</script>";
            exit;
        }
    }
}

// Eliminar Tipo (Control rápido por parámetro GET)
if (isset($_GET['eliminar_tipo'])) {
    $id_eliminar = intval($_GET['eliminar_tipo']);
    if ($id_eliminar > 0) {
        $stmt = $pdo->prepare("DELETE FROM tipos_cliente WHERE id = ?");
        if ($stmt->execute([$id_eliminar])) {
            echo "<script>alert('Tipo de cliente eliminado'); window.location.href='configuracion.php';</script>";
            exit;
        }
    }
}

// Obtener listado de Tipos de Clientes actuales para la tabla interna del modal
$tipos_clientes_bd = $pdo->query("SELECT * FROM tipos_cliente ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
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
        .bg-red { background: #fff5f5; color: #e53e3e; }

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
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(3px); overflow-y: auto; }
        .modal-content { background: #fff; margin: 5% auto; padding: 30px; border-radius: 15px; width: 90%; max-width: 500px; animation: slideDown 0.4s ease; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #edf2f7; padding-bottom: 15px; margin-bottom: 15px; }
        .modal-header h2 { font-size: 1.3rem; color: var(--text-main); margin: 0; }
        .close { font-size: 1.5rem; cursor: pointer; color: var(--text-muted); }
        .close:hover { color: var(--danger); }
        .form-group-modern { margin-bottom: 15px; }
        .form-group-modern label { font-weight: bold; color: var(--text-main); display: block; margin-bottom: 5px; font-size: 0.9rem; }
        .form-control-modern { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 1rem; box-sizing: border-box; }
        .btn-full { width: 100%; padding: 12px; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; margin-top: 10px; display: flex; align-items: center; justify-content: center; gap: 10px; transition: background 0.2s; }
        
        /* Tabla interna para el listado de tipos */
        .tabla-modal-wrapper { max-height: 200px; overflow-y: auto; margin-top: 20px; border: 1px solid #e2e8f0; border-radius: 8px; }
        .tabla-modal { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; }
        .tabla-modal th { background: #f7fafc; padding: 10px; color: var(--text-muted); font-weight: 600; border-bottom: 1px solid #e2e8f0; }
        .tabla-modal td { padding: 10px; border-bottom: 1px solid #edf2f7; color: var(--text-main); }
        .btn-eliminar-mini { color: var(--danger); border: none; background: none; cursor: pointer; font-size: 1rem; padding: 2px 6px; border-radius: 4px; }
        .btn-eliminar-mini:hover { background: #fff5f5; }

        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
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
                    <div class="icon-box bg-blue"><i class="fas fa-file-invoice"></i></div>
                    <h3>Perfil Fiscal</h3>
                    <p>Configuración de RFC, razón social y datos para facturación electrónica.</p>
                </div>
                <span class="action-link">Editar Datos <i class="fas fa-chevron-right"></i></span>
            </a>

            <a href="capital_social.php" class="card-config">
                <div>
                    <div class="icon-box bg-purple"><i class="fas fa-coins"></i></div>
                    <h3>Capital Social</h3>
                    <p>Configuración del capital social de la empresa.</p>
                </div>
                <span class="action-link">Editar Datos <i class="fas fa-chevron-right"></i></span>
            </a>

            <div class="card-config" onclick="openModal('modalCategoria')" style="cursor: pointer;">
                <div>
                    <div class="icon-box bg-green"><i class="fas fa-tags"></i></div>
                    <h3>Categorías</h3>
                    <p>Organiza tus productos químicos (Desinfectantes, Limpieza Carrocería, etc).</p>
                </div>
                <span class="action-link">Añadir Categoría <i class="fas fa-plus"></i></span>
            </div>

            <div class="card-config" onclick="openModal('modalTiposClientes')" style="cursor: pointer;">
                <div>
                    <div class="icon-box bg-red"><i class="fas fa-users"></i></div>
                    <h3>Tipos de Clientes</h3>
                    <p>Define los diferentes tipos de clientes y sus niveles de precios.</p>
                </div>
                <span class="action-link">Gestionar Tipos <i class="fas fa-plus"></i></span>
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
                <span class="close" onclick="closeModal('modalCategoria')">&times;</span>
            </div>
            <form method="POST">
                <div class="form-group-modern">
                    <label>Nombre del grupo:</label>
                    <input type="text" name="nombre_cat" class="form-control-modern" placeholder="Ej: Jabones Industriales" required>
                </div>
                <button type="submit" name="nueva_categoria" class="btn-full" style="background: var(--success); color: white;">
                    <i class="fas fa-save"></i> Guardar Categoría
                </button>
            </form>
        </div>
    </div>

    <div id="modalTiposClientes" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-users"></i> Tipos de Clientes</h2>
                <span class="close" onclick="closeModal('modalTiposClientes')">&times;</span>
            </div>
            
            <form method="POST" style="border-bottom: 1px dashed #e2e8f0; padding-bottom: 20px;">
                <div class="form-group-modern">
                    <label>Nombre del Tipo:</label>
                    <input type="text" name="nombre_tipo" class="form-control-modern" placeholder="Ej: Distribuidor / VIP" required>
                </div>
                <div class="form-group-modern">
                    <label>Porcentaje Descuento (%):</label>
                    <input type="number" step="0.01" name="descuento_porcentaje" class="form-control-modern" placeholder="Ej: 10.00" min="0" max="100" required>
                </div>
                <button type="submit" name="nuevo_tipo_cliente" class="btn-full" style="background: var(--primary); color: white;">
                    <i class="fas fa-plus"></i> Agregar Tipo
                </button>
            </form>

            <h3 style="margin: 20px 0 10px 0; font-size: 1rem; color: var(--text-main);"><i class="fas fa-list"></i> Registrados Actualmente</h3>
            <div class="tabla-modal-wrapper">
                <table class="tabla-modal">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Desc. %</th>
                            <th style="text-align: center;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tipos_clientes_bd)): ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 15px;">No hay registros.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tipos_clientes_bd as $tipo): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($tipo['nombre']) ?></strong></td>
                                    <td><?= number_format($tipo['descuento_porcentaje'], 2) ?>%</td>
                                    <td style="text-align: center;">
                                        <button type="button" class="btn-eliminar-mini" onclick="confirmarEliminarTipo(<?= $tipo['id'] ?>)" title="Eliminar">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function openModal(idModal) { 
            document.getElementById(idModal).style.display = "block"; 
        }
        
        function closeModal(idModal) { 
            document.getElementById(idModal).style.display = "none"; 
        }

        function confirmarEliminarTipo(id) {
            if (confirm("¿Estás seguro de que deseas eliminar este tipo de cliente? Esto podría afectar a los clientes enlazados a él.")) {
                window.location.href = "configuracion.php?eliminar_tipo=" + id;
            }
        }
        
        // Cerrar modales al hacer clic fuera del recuadro blanco
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = "none";
            }
        }
    </script>
    <script src="../js/admin.js"></script>
</body>
</html>