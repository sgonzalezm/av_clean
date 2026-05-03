<?php
ob_start(); 
ini_set('display_errors', 0); 
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

function t($texto) {
    return iconv('UTF-8', 'windows-1252//IGNORE', $texto);
}

// Configuración del PDF (58mm de ancho)
$ancho = 58;
// Calculamos un alto aproximado base + productos
$alto = 90 + (count($productos) * 10); 

$pdf = new FPDF('P', 'mm', array($ancho, $alto));
$pdf->SetMargins(4, 2, 4); 
$pdf->SetAutoPageBreak(true, 2);
$pdf->AddPage();

// --- DISEÑO DEL TICKET ---
$pdf->SetFont('Arial', 'B', 12); // Cambié a Arial para mejor legibilidad en pequeño
$pdf->Cell(50, 6, 'AHD CLEAN', 0, 1, 'C');

$pdf->SetFont('Arial', '', 7);
$pdf->Cell(50, 3, t('Expertos en Limpieza'), 0, 1, 'C');
$pdf->Cell(50, 3, date("d/m/Y H:i"), 0, 1, 'C');

$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(50, 4, 'FOLIO: #' . $pedido['id'], 0, 1, 'L');
$pdf->SetFont('Arial', '', 7);
$pdf->Cell(50, 4, 'CLIENTE: ' . t(substr($pedido['nombre'], 0, 25)), 0, 1, 'L');

$pdf->Ln(1);
$pdf->Cell(50, 0, '', 'T', 1);
$pdf->Ln(1);

// Tabla de productos
$pdf->SetFont('Arial', 'B', 7);
$pdf->Cell(7, 4, 'CANT', 0, 0, 'L');
$pdf->Cell(28, 4, 'PRODUCTO', 0, 0, 'L');
$pdf->Cell(15, 4, 'TOTAL', 0, 1, 'R');
$pdf->Cell(50, 0, '', 'T', 1);
$pdf->Ln(1);

$pdf->SetFont('Arial', '', 7);
foreach ($productos as $p) {
    $inicio_y = $pdf->GetY();
    
    // Cantidad
    $pdf->Cell(7, 4, $p['cantidad'], 0, 0, 'L');
    
    // Producto (MultiCell permite saltos de línea si el nombre es largo)
    $x_producto = $pdf->GetX();
    $pdf->MultiCell(28, 4, t($p['producto_nombre']), 0, 'L');
    $final_y = $pdf->GetY(); // Guardamos donde terminó el texto largo
    
    // Precio (Lo colocamos a la derecha, alineado con el inicio de la fila)
    $pdf->SetXY($x_producto + 28, $inicio_y);
    $pdf->Cell(15, 4, '$' . number_format($p['precio_unitario'] * $p['cantidad'], 0), 0, 1, 'R');
    
    // Forzamos el cursor a la posición más baja para que la siguiente fila no se encime
    $pdf->SetY(max($final_y, $pdf->GetY()));
    $pdf->Ln(1);
}

$pdf->Ln(1);
$pdf->Cell(50, 0, '', 'T', 1);
$pdf->Ln(1);

// TOTAL
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(25, 6, 'TOTAL:', 0, 0, 'L');
$pdf->Cell(25, 6, '$' . number_format($pedido['total'], 0), 0, 1, 'R');

$pdf->Ln(4);
$pdf->SetFont('Arial', 'I', 7);
$pdf->Cell(50, 4, t('¡Gracias por su compra!'), 0, 1, 'C');
$pdf->Cell(50, 4, '.', 0, 1, 'C'); 

ob_end_clean(); 
$pdf->Output('I', 'Ticket_'.$id.'.pdf');