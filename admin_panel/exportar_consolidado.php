<?php
require_once '../includes/session.php';
verificarSesion();

if (!isset($_SESSION['ultimo_reporte_consolidado']) || empty($_SESSION['ultimo_reporte_consolidado'])) {
    die("No hay un reporte activo para exportar.");
}

$reporte = $_SESSION['ultimo_reporte_consolidado'];
$filename = "solicitud_compra_ahd_" . date('d-m-Y') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // Soporte para acentos

// Encabezados del reporte consolidado
fputcsv($output, ['Materia Prima', 'Cantidad Necesaria', 'Unidad', 'Costo Estimado'], ';');

$gran_total = 0;
foreach ($reporte as $item) {
    $costo = $item['costo_estimado'];
    $gran_total += $costo;
    
    fputcsv($output, [
        $item['nombre'],
        number_format($item['cantidad'], 3, '.', ''),
        $item['unidad'],
        number_format($costo, 2, '.', '')
    ], ';');
}

// Añadir una fila de total al final
fputcsv($output, ['', '', 'TOTAL ESTIMADO:', number_format($gran_total, 2, '.', '')], ';');

fclose($output);
exit;