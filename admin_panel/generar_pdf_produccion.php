<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$id_orden = $_GET['id'] ?? null;
if (!$id_orden) die("ID de orden no especificado.");

// 1. Obtener cabecera de la orden
$stmt = $pdo->prepare("SELECT * FROM ordenes_produccion WHERE id = ?");
$stmt->execute([$id_orden]);
$orden = $stmt->fetch();

if (!$orden) die("La orden no existe.");

// 2. Obtener solo los productos fabricados
$stmt_p = $pdo->prepare("
    SELECT odp.*, p.nombre 
    FROM orden_detalle_productos odp 
    JOIN productos p ON odp.id_producto = p.id 
    WHERE odp.id_orden = ?
");
$stmt_p->execute([$id_orden]);
$productos = $stmt_p->fetchAll();

ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Hoja de Producción #<?php echo $id_orden; ?></title>
    <style>
        body { font-family: 'Helvetica', Arial, sans-serif; color: #333; padding: 30px; }
        .header { text-align: center; border-bottom: 3px solid #2b6cb0; margin-bottom: 30px; padding-bottom: 10px; }
        .company-name { font-size: 24px; font-weight: bold; color: #2b6cb0; }
        .doc-title { text-transform: uppercase; font-size: 16px; margin: 5px 0; color: #4a5568; }
        
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th { background: #f8fafc; border: 1px solid #cbd5e0; padding: 12px; text-align: left; font-size: 13px; }
        .table td { border: 1px solid #cbd5e0; padding: 15px; font-size: 14px; }
        
        .check-box { width: 20px; height: 20px; border: 1px solid #333; display: inline-block; margin-right: 10px; }
        .footer { margin-top: 50px; text-align: center; font-size: 11px; color: #a0aec0; border-top: 1px solid #eee; padding-top: 10px; }
        
        .instrucciones { background: #ebf8ff; padding: 15px; border-radius: 5px; margin-bottom: 20px; font-size: 13px; border-left: 5px solid #3182ce; }
        
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

    <div class="no-print" style="text-align: right; margin-bottom: 20px;">
        <button onclick="window.print()" style="background: #2b6cb0; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold;">
            <i class="fas fa-print"></i> Imprimir Hoja de Trabajo
        </button>
    </div>

    <div class="header">
        <div class="company-name">AHD CLEAN</div>
        <h2 class="doc-title">Hoja de Trabajo de Producción</h2>
        <div style="font-size: 14px;">Orden de Control: <strong>#<?php echo $id_orden; ?></strong></div>
    </div>

    <div class="instrucciones">
        <strong>Instrucciones para el Operador:</strong><br>
        1. Verifique que cuenta con todos los insumos entregados por almacén.<br>
        2. Siga el orden de mezclado de la fórmula maestra.<br>
        3. Marque con una (X) cada producto una vez terminado y envasado.
    </div>

    <table class="table">
        <thead>
            <tr>
                <th style="width: 50%;">Producto a Fabricar</th>
                <th style="width: 25%; text-align: center;">Cantidad Objetivo</th>
                <th style="width: 25%; text-align: center;">Estado / Firma</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($productos as $p): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($p['nombre']); ?></strong></td>
                <td style="text-align: center; font-size: 16px;">
                    <strong><?php echo number_format($p['cantidad_litros'], 2); ?> Litros</strong>
                </td>
                <td style="text-align: center;">
                    <div style="display: flex; align-items: center; justify-content: center;">
                        <div class="check-box"></div> <span>Terminado</span>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top: 80px; display: table; width: 100%;">
        <div style="display: table-cell; width: 50%; text-align: center;">
            <div style="border-top: 1px solid #000; width: 200px; margin: 0 auto;"></div>
            <p style="font-size: 12px;">Responsable de Mezclado</p>
        </div>
        <div style="display: table-cell; width: 50%; text-align: center;">
            <div style="border-top: 1px solid #000; width: 200px; margin: 0 auto;"></div>
            <p style="font-size: 12px;">Supervisor de Calidad</p>
        </div>
    </div>

    <div class="footer">
        Fecha de Impresión: <?php echo date('d/m/Y H:i'); ?> | AHD Clean - Control de Procesos
    </div>

    <script>window.print();</script>
</body>
</html>
<?php echo ob_get_clean(); ?>