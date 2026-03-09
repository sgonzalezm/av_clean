<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// Aquí podrías agregar consultas rápidas para mostrar números en las tarjetas si quisieras, 
// por ejemplo: contar órdenes pendientes.
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Gestión de Compras | AHD Clean</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        /* Estilos específicos para el menú de tarjetas */
        .metricas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .card-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .card.clickable {
            background: white;
            padding: 30px;
            border-radius: 12px;
            border: 1px solid #edf2f7;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .card.clickable:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border-color: #4299e1;
        }
        .btn-editar-mini {
            margin-top: auto;
            color: #3182ce;
            font-weight: bold;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        .btn {
            background: #3182ce;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #2b6cb0;
        }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <div class="header">
            <div>
                <h1><i class="fas fa-shopping-cart"></i> Gestión de Compras</h1>
                <p style="color: #718096; font-size: 0.9rem;">Adquisiciones, proveedores y control de stock entrante.</p>
            </div>
            <div class="header-actions">
                <a href="nueva_compra.php" class="btn">
                    <i class="fas fa-plus"></i> Registrar Compra
                </a>
            </div>
        </div>

        <div class="metricas-grid">
            <a href="proveedores.php" class="card-link">
                <div class="card clickable">
                    <i class="fas fa-truck-loading fa-2x" style="color: #4299e1;"></i>
                    <h3>Proveedores</h3>
                    <p>Directorio de fabricantes de químicos, envases y materia prima.</p>
                    <span class="btn-editar-mini">Gestionar <i class="fas fa-chevron-right"></i></span>
                </div>
            </a>

            <a href="ordenes_compra.php" class="card-link">
                <div class="card clickable">
                    <i class="fas fa-file-invoice-dollar fa-2x" style="color: #48bb78;"></i>
                    <h3>Órdenes de Compra</h3>
                    <p>Historial de pedidos realizados y estados de pago.</p>
                    <span class="btn-editar-mini">Ver historial <i class="fas fa-chevron-right"></i></span>
                </div>
            </a>

            <a href="recepcion_stock.php" class="card-link">
                <div class="card clickable">
                    <i class="fas fa-boxes fa-2x" style="color: #ed8936;"></i>
                    <h3>Entrada de Almacén</h3>
                    <p>Validar productos recibidos para actualizar el stock automáticamente.</p>
                    <span class="btn-editar-mini">Cargar stock <i class="fas fa-chevron-right"></i></span>
                </div>
            </a>

            <div class="card" style="opacity: 0.6; cursor: not-allowed; background: #f7fafc; padding: 30px; border-radius: 12px; border: 1px dotted #cbd5e1;">
                <i class="fas fa-receipt fa-2x" style="color: #a0aec0;"></i>
                <h3>Gastos Fijos</h3>
                <p>Próximamente: Registro de renta, servicios y nómina operativa.</p>
            </div>
        </div>
    </div>

    <script src="../js/admin.js"></script>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }
    </script>
</body>
</html>