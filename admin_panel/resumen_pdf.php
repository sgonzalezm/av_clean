<?php
session_start();
include '../includes/conexion.php';
// require_once '../vendor/autoload.php'; // Descomenta si usas Composer/Dompdf
// use Dompdf\Dompdf;

// 1. Obtener el filtro para saber qué imprimir
$filtro = $_GET['status'] ?? 'Todos';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    
    if ($filtro !== 'Todos') {
        $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE status = ? ORDER BY fecha_pedido DESC");
        $stmt->execute([$filtro]);
    } else {
        $stmt = $pdo->query("SELECT * FROM pedidos ORDER BY fecha_pedido DESC");
    }
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) { die("Error: " . $e->getMessage()); }

// 2. Construir el HTML del Reporte
$fecha_reporte = date('d/m/Y H:i');
$suma_total = 0;

ob_start(); // Iniciamos buffer para capturar el HTML
?>
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Helvetica', sans-serif; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #1a365d; padding-bottom: 10px; }
        .title { margin: 0; color: #1a365d; text-transform: uppercase; }
        .meta { font-size: 12px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; }
        th { background-color: #f2f2f2; padding: 10px; border: 1px solid #ddd; text-align: left; }
        td { padding: 10px; border: 1px solid #ddd; }
        .total-row { background-color: #eee; font-weight: bold; font-size: 14px; }
        .badge { padding: 3px 7px; border-radius: 10px; font-size: 10px; color: white; }
    </style>
</head>
<body>
    <!-- Botón toggle para móvil -->
    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <!-- Sidebar (menú lateral) -->
    <?php include 'sidebar.php'; ?>

    <div class="header">
        <h1 class="title">Reporte de Pedidos - AHD Clean</h1>
        <div class="meta">
            Filtro aplicado: <strong><?php echo $filtro; ?></strong> | 
            Generado el: <?php echo $fecha_reporte; ?>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Folio</th>
                <th>Fecha</th>
                <th>Cliente / Email</th>
                <th>Estado</th>
                <th style="text-align: right;">Monto Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($pedidos as $p): 
                $suma_total += $p['total'];
            ?>
            <tr>
                <td>#<?php echo $p['id']; ?></td>
                <td><?php echo date('d/m/y', strtotime($p['fecha_pedido'])); ?></td>
                <td><?php echo $p['email']; ?></td>
                <td><?php echo $p['status']; ?></td>
                <td style="text-align: right;">$<?php echo number_format($p['total'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="4" style="text-align: right;">SUMA TOTAL DEL REPORTE:</td>
                <td style="text-align: right;">$<?php echo number_format($suma_total, 2); ?></td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top: 50px; font-size: 10px; text-align: center; color: #999;">
        Documento interno para uso administrativo y financiero.
    </div>
    <script src="../js/admin.js"></script>  
</body>
</html>

<?php
$html = ob_get_clean();

// --- OPCIÓN A: IMPRESIÓN DIRECTA (Sin librerías) ---
echo $html;
echo "<script>window.print();</script>";

// --- OPCIÓN B: GENERAR PDF REAL (Requiere Dompdf) ---
/*
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Reporte_Finanzas_".date('Ymd').".pdf", ["Attachment" => false]);
*/
?>