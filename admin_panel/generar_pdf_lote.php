<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// 1. Recuperar los datos del último cálculo realizado en lotes.php
$reporte = $_SESSION['ultimo_reporte_ahd'] ?? [];
$lotes = $_SESSION['ultimo_calculo_lotes'] ?? [];

if (empty($reporte)) {
    die("No hay datos de producción para generar el reporte. Por favor, calcula primero en la página de Lotes.");
}

$fecha_reporte = date('d/m/Y H:i');
$total_inversion = 0;

ob_start(); 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Hoja de Pesado - AHD Clean</title>
    <style>
        body { font-family: 'Helvetica', Arial, sans-serif; color: #333; line-height: 1.5; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #2b6cb0; padding-bottom: 10px; margin-bottom: 20px; }
        .logo-placeholder { font-size: 24px; font-weight: bold; color: #2b6cb0; }
        .title { text-transform: uppercase; margin: 5px 0; font-size: 18px; }
        
        .info-section { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 13px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #edf2f7; padding: 10px; border: 1px solid #cbd5e0; text-align: left; font-size: 12px; }
        td { padding: 10px; border: 1px solid #cbd5e0; font-size: 12px; }
        
        .total-row { background-color: #f7fafc; font-weight: bold; }
        
        .footer { margin-top: 50px; text-align: center; font-size: 10px; color: #718096; }
        .signature-grid { display: flex; justify-content: space-around; margin-top: 60px; }
        .signature-box { border-top: 1px solid #000; width: 200px; text-align: center; padding-top: 5px; font-size: 12px; }

        /* Estilos para impresión */
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 20px; background: #fff5f5; padding: 10px; border: 1px solid #feb2b2; text-align: center;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; background: #2b6cb0; color: white; border: none; border-radius: 5px;">
            <i class="fas fa-print"></i> Imprimir Hoja de Pesado
        </button>
        <p style="font-size: 12px; margin-top: 5px;">Presiona el botón para imprimir o guardar como PDF.</p>
    </div>

    <div class="header">
        <div class="logo-placeholder">AHD CLEAN</div>
        <h2 class="title">Hoja de Pesado y Orden de Producción</h2>
        <div style="font-size: 12px; color: #4a5568;">Control de Calidad e Inventarios</div>
    </div>

    <div class="info-section">
        <div>
            <strong>Fecha de Emisión:</strong> <?php echo $fecha_reporte; ?><br>
            <strong>Responsable:</strong> <?php echo $_SESSION['usuario'] ?? 'Administrador'; ?>
        </div>
        <div style="text-align: right;">
            <strong>Planta:</strong> México HQ<br>
            <strong>Estado:</strong> Borrador de Producción
        </div>
    </div>

    <h3 style="font-size: 14px; border-left: 4px solid #2b6cb0; padding-left: 10px;">1. MATERIALES A PESAR (INSUMOS)</h3>
    <table>
        <thead>
            <tr>
                <th>Descripción del Insumo / Materia Prima</th>
                <th style="text-align: center;">Cantidad Requerida</th>
                <th style="text-align: center;">Unidad</th>
                <th style="text-align: right;">Costo Est.</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($reporte as $item): 
                $total_inversion += $item['costo_estimado'];
            ?>
            <tr>
                <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                <td style="text-align: center; font-weight: bold; font-size: 14px;">
                    <?php echo number_format($item['cantidad_requerida'], 3); ?>
                </td>
                <td style="text-align: center;"><?php echo $item['unidad']; ?></td>
                <td style="text-align: right;">$<?php echo number_format($item['costo_estimado'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="3" style="text-align: right;">INVERSIÓN TOTAL EN MATERIA PRIMA:</td>
                <td style="text-align: right;">$<?php echo number_format($total_inversion, 2); ?></td>
            </tr>
        </tfoot>
    </table>

    <h3 style="font-size: 14px; border-left: 4px solid #2b6cb0; padding-left: 10px; margin-top: 30px;">2. PRODUCTOS TERMINADOS A OBTENER</h3>
    <ul>
        <?php 
        foreach($lotes as $id_p => $litros): 
            if($litros > 0):
                $stmt = $pdo->prepare("SELECT nombre FROM productos WHERE id = ?");
                $stmt->execute([$id_p]);
                $p_nombre = $stmt->fetchColumn();
        ?>
            <li style="font-size: 13px; margin-bottom: 5px;">
                <strong><?php echo htmlspecialchars($p_nombre); ?>:</strong> <?php echo $litros; ?> Litros totales.
            </li>
        <?php 
            endif;
        endforeach; 
        ?>
    </ul>

    <div class="signature-grid">
        <div class="signature-box">Autorizó (Admin)</div>
        <div class="signature-box">Pesó (Almacén)</div>
        <div class="signature-box">Recibió (Producción)</div>
    </div>

    <div class="footer">
        AHD Clean - Reporte generado automáticamente por el sistema de gestión de fórmulas.<br>
        Este documento es indispensable para el control de lotes y trazabilidad química.
    </div>

</body>
</html>
<?php
$html = ob_get_clean();
echo $html;
echo "<script>window.print();</script>";
?>