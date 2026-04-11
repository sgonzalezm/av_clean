<?php
// 1. Limpieza de salida y configuración de errores
ob_start(); // Inicia el búfer de salida para evitar el error "Some data has already been output"
ini_set('display_errors', 0); // Desactivamos mostrar errores en pantalla para que no rompan el PDF
error_reporting(E_ALL);

require_once '../includes/session.php';
require_once '../includes/conexion.php';
require_once '../includes/fpdf.php'; 

verificarSesion();

$id = $_GET['id'] ?? die("Sin ID");

// Consulta de datos
$stmt = $pdo->prepare("SELECT p.*, u.nombre as vendedor FROM pedidos p 
                       LEFT JOIN usuarios_admin u ON p.usuario_id = u.id 
                       WHERE p.id = ?");
$stmt->execute([$id]);
$pedido = $stmt->fetch();

$detalles = $pdo->prepare("SELECT * FROM detalle_pedido WHERE pedido_id = ?");
$detalles->execute([$id]);
$productos = $detalles->fetchAll();

// Función auxiliar para convertir texto a formato compatible con FPDF (ISO-8859-1)
// reemplaza al viejo utf8_decode()
function t($texto) {
    return iconv('UTF-8', 'windows-1252//IGNORE', $texto);
}

// Configuración del PDF
$ancho = 58;
$alto = 80 + (count($productos) * 8); 

$pdf = new FPDF('P', 'mm', array($ancho, $alto));
$pdf->SetMargins(4, 2, 4); 
$pdf->SetAutoPageBreak(true, 2);
$pdf->AddPage();

// --- DISEÑO DEL TICKET ---
$pdf->SetFont('Courier', 'B', 12); // Usamos Courier que es más nativa para tickets
$pdf->Cell(50, 6, 'AHD CLEAN', 0, 1, 'C');

$pdf->SetFont('Courier', '', 8);
$pdf->Cell(50, 4, t('Expertos en Limpieza'), 0, 1, 'C');
$pdf->Cell(50, 4, date("d/m/Y H:i"), 0, 1, 'C');

$pdf->Ln(2);
$pdf->SetFont('Courier', 'B', 8);
$pdf->Cell(50, 4, 'FOLIO: #' . $pedido['id'], 0, 1, 'L');
$pdf->SetFont('Courier', '', 8);
$pdf->Cell(50, 4, 'CLIENTE: ' . t(substr($pedido['nombre'], 0, 22)), 0, 1, 'L');

$pdf->Ln(1);
$pdf->Cell(50, 0, '', 'T', 1);
$pdf->Ln(1);

// Tabla de productos
$pdf->SetFont('Courier', 'B', 7);
$pdf->Cell(8, 4, 'CAN', 0, 0, 'L');
$pdf->Cell(28, 4, 'PRODUCTO', 0, 0, 'L');
$pdf->Cell(14, 4, 'TOTAL', 0, 1, 'R');
$pdf->Cell(50, 0, '', 'T', 1);

$pdf->SetFont('Courier', '', 7);
foreach ($productos as $p) {
    $pdf->Cell(8, 4, $p['cantidad'], 0, 0, 'L');
    
    $x = $pdf->GetX();
    $y = $pdf->GetY();
    $pdf->MultiCell(28, 4, t($p['producto_nombre']), 0, 'L');
    $pdf->SetXY($x + 28, $y);
    
    $pdf->Cell(14, 4, '$' . number_format($p['precio_unitario'] * $p['cantidad'], 0), 0, 1, 'R');
}

$pdf->Ln(2);
$pdf->Cell(50, 0, '', 'T', 1);
$pdf->SetFont('Courier', 'B', 10);
$pdf->Cell(25, 6, 'TOTAL:', 0, 0, 'L');
$pdf->Cell(25, 6, '$' . number_format($pedido['total'], 0), 0, 1, 'R');

$pdf->Ln(4);
$pdf->SetFont('Courier', 'I', 7);
$pdf->Cell(50, 4, t('¡Gracias por su compra!'), 0, 1, 'C');
$pdf->Cell(50, 4, '.', 0, 1, 'C'); 

// Limpiamos cualquier salida previa y generamos el PDF
ob_end_clean(); 
$pdf->Output('I', 'Ticket_'.$id.'.pdf');