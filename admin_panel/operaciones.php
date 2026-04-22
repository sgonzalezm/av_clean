<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

/**
 * Consultas rápidas para Mini-Métricas (Opcional)
 * Esto le da vida al Hub mostrando alertas inmediatas.
 */
// 1. Contar insumos con bajo stock (menor a 5 unidades por ejemplo)
$bajo_stock = $pdo->query("SELECT COUNT(*) FROM insumos WHERE stock_actual < 5")->fetchColumn();

// 2. Contar órdenes de compra pendientes
// $pendientes = $pdo->query("SELECT COUNT(*) FROM ordenes_compra WHERE estado = 'pendiente'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Gestión de Operaciones | AHD Clean</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        /* Estilos del Hub de Tarjetas */
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
            gap: 12px;
            position: relative;
            overflow: hidden;
        }
        .card.clickable:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border-color: #4299e1;
        }
        .card h3 {
            margin: 10px 0 5px 0;
            font-size: 1.25rem;
            color: #2d3748;
        }
        .card p {
            color: #718096;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        .btn-editar-mini {
            margin-top: auto;
            color: #3182ce;
            font-weight: bold;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        /* Badge de alerta para stock bajo */
        .badge-alerta {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #f56565;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            gap: 20px;
        }
        .btn-principal {
            background: #3182ce;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 10px;
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
                <h1><i class="fas fa-industry"></i> Operaciones y Suministros</h1>
                <p style="color: #718096;">Centro de control para fabricación, insumos y compras de AHD Clean.</p>
            </div>
            <div class="header-actions">
            </div>
        </div>

        <div class="metricas-grid">
            
            <a href="insumos.php" class="card-link">
                <div class="card clickable">
                    <?php if($bajo_stock > 0): ?>
                        <span class="badge-alerta"><?php echo $bajo_stock; ?> críticos</span>
                    <?php endif; ?>
                    <i class="fas fa-flask fa-2x" style="color: #9f7aea;"></i>
                    <h3>Materias Primas</h3>
                    <p>Gestión de químicos, envases y fragancias. Control de stock mínimo para producción.</p>
                    <span class="btn-editar-mini">Inventario Insumos <i class="fas fa-arrow-right"></i></span>
                </div>
            </a>

            <a href="formulas.php" class="card-link">
                <div class="card clickable">
                    <i class="fas fa-microscope fa-2x" style="color: #38b2ac;"></i>
                    <h3>Fórmulas / Recetario</h3>
                    <p>Configuración de ingredientes y proporciones por litro para cada producto.</p>
                    <span class="btn-editar-mini">Ver Fórmulas <i class="fas fa-arrow-right"></i></span>
                </div>
            </a>

            <a href="produccion_lotes.php" class="card-link">
                <div class="card clickable" style="border-top: 4px solid #2d3748;">
                    <i class="fas fa-layer-group fa-2x" style="color: #2d3748;"></i>
                    <h3>Lotes de Fabricación</h3>
                    <p>Consolidar producción, calcular explosión de materiales y descargar inventarios.</p>
                    <span class="btn-editar-mini">Planear Producción <i class="fas fa-arrow-right"></i></span>
                </div>
            </a>

            <a href="proveedores.php" class="card-link">
                <div class="card clickable">
                    <i class="fas fa-truck-moving fa-2x" style="color: #4299e1;"></i>
                    <h3>Proveedores</h3>
                    <p>Directorio de contactos, tiempos de entrega y listas de precios de fábrica.</p>
                    <span class="btn-editar-mini">Ver Directorio <i class="fas fa-arrow-right"></i></span>
                </div>
            </a>

            <a href="historial_ordenes.php" class="card-link">
                <div class="card clickable">
                    <i class="fas fa-industry fa-2x" style="color: #48bb78;"></i>
                    <h3>Órdenes de Produccion</h3>
                    <p>Seguimiento de produccion, ordenes de compra y recepciones pendientes.</p>
                    <span class="btn-editar-mini">Historial de Compras <i class="fas fa-arrow-right"></i></span>
                </div>
            </a>

            <a href="recepcion_stock.php" class="card-link">
                <div class="card clickable">
                    <i class="fas fa-boxes fa-2x" style="color: #ed8936;"></i>
                    <h3>Entrada de Almacén</h3>
                    <p>Recepción física de mercancía para actualización automática de existencias.</p>
                    <span class="btn-editar-mini">Cargar Stock <i class="fas fa-arrow-right"></i></span>
                </div>
            </a>

            <a href="ordenes_trabajo.php" class="card-link">
                <div class="card clickable">
                    <i class="fas fa-tasks fa-2x" style="color: #686563;"></i>
                    <h3>Ordenes de trabajo</h3>
                    <p>Ordenes de trabajo para ventas y surtido</p>
                    <span class="btn-editar-mini">Crear Orden <i class="fas fa-arrow-right"></i></span>
                </div>
            </a>

            <a href="gasto_nuevo.php" class="card-link">
                <div class="card clickable">
                    <i class="fas fa-toolbox fa-2x" style="color: #17558f;"></i>
                    <h3>Equipo y consumibles</h3>
                    <p>Herramienta, equipamiento, insumos y consumibles requeridos por la operacion.</p>
                    <span class="btn-editar-mini">Administrar <i class="fas fa-arrow-right"></i></span>
                </div>
            </a>

            <a href="cuentas_cobrar.php" class="card-link">
                <div class="card clickable">
                    <i class="fas fa-wallet fa-2x" style="color: #d76b23;"></i>
                    <h3>Cuentas por Cobrar</h3>
                    <p>Seguimiento de saldos y vencimientos de clientes.</p>
                    <span class="btn-editar-mini">Revisar <i class="fas fa-arrow-right"></i></span>
                </div>
            </a>

            <a href="pasivos.php" class="card-link">
                <div class="card clickable">
                    <i class="fas fa-file-invoice fa-2x" style="color: #72260c;"></i>
                    <h3>Cuentas por Pagar</h3>
                    <p>Seguimiento de saldos y vencimientos de proveedores.</p>
                    <span class="btn-editar-mini">Revisar <i class="fas fa-arrow-right"></i></span>
                </div>
            </a>


        </div> </div> <script src="../js/admin.js"></script>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }
    </script>
</body>
</html>