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
// Calculamos un alto aproximado base + productos + líneas de desglose adicionales
$alto = 105 + (count($productos) * 10); 

$pdf = new FPDF('P', 'mm', array($ancho, $alto));
$pdf->SetMargins(4, 2, 4); 
$pdf->SetAutoPageBreak(true, 2);
$pdf->AddPage();

// --- DISEÑO DEL TICKET ---
$pdf->SetFont('Arial', 'B', 12); 
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
$pdf->Cell(8, 4, 'CANT', 0, 0, 'L');
$pdf->Cell(27, 4, 'PRODUCTO', 0, 0, 'L');
$pdf->Cell(15, 4, 'TOTAL', 0, 1, 'R');
$pdf->Cell(50, 0, '', 'T', 1);
$pdf->Ln(1);

$pdf->SetFont('Arial', '', 7);
$subtotal_calculado = 0; // Variable para calcular el monto original sin descuento

foreach ($productos as $p) {
    $inicio_y = $pdf->GetY();
    
    // Formateamos cantidad con flotante para que si es 0.700L o 0.250L no se rompa la visual en el ticket
    $cantidad_formateada = (floatval($p['cantidad']) == intval($p['cantidad'])) ? intval($p['cantidad']) : floatval($p['cantidad']);
    
    // Cantidad
    $pdf->Cell(8, 4, $cantidad_formateada, 0, 0, 'L');
    
    // Producto (MultiCell permite saltos de línea si el nombre es largo)
    $x_producto = $pdf->GetX();
    $pdf->MultiCell(27, 4, t($p['producto_nombre']), 0, 'L');
    $final_y = $pdf->GetY(); 
    
    // Cálculo del importe de la línea
    $total_linea = $p['precio_unitario'] * $p['cantidad'];
    $subtotal_calculado += $total_linea;
    
    // Precio (Alineado con el inicio de la fila a la derecha)
    $pdf->SetXY($x_producto + 27, $inicio_y);
    $pdf->Cell(15, 4, '$' . number_format($total_linea, 2), 0, 1, 'R');
    
    // Forzamos el cursor a la posición más baja para evitar encimados
    $pdf->SetY(max($final_y, $pdf->GetY()));
    $pdf->Ln(1);
}

$pdf->Ln(1);
$pdf->Cell(50, 0, '', 'T', 1);
$pdf->Ln(1);

// --- SECCIÓN DE DESGLOSE DE PAGOS ORGANIZADOS ---
$pdf->SetFont('Arial', '', 7);

// 1. Mostrar Subtotal (siempre que el pedido haya tenido algún descuento real)
$ahorro = $subtotal_calculado - $pedido['total'];

if ($ahorro > 0.01) {
    $pdf->Cell(25, 4, 'Subtotal:', 0, 0, 'L');
    $pdf->Cell(25, 4, '$' . number_format($subtotal_calculado, 2), 0, 1, 'R');
    
    // 2. Mostrar línea de Descuento / Ahorro
    $pdf->Cell(25, 4, 'Descuento / Ahorro:', 0, 0, 'L');
    $pdf->Cell(25, 4, '-$' . number_format($ahorro, 2), 0, 1, 'R');
    
    $pdf->Ln(1);
    $pdf->Cell(50, 0, '', 'T', 1);
    $pdf->Ln(1);
}

// 3. GRAN TOTAL (Destacado)
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(25, 6, 'TOTAL:', 0, 0, 'L');
$pdf->Cell(25, 6, '$' . number_format($pedido['total'], 2), 0, 1, 'R');

$pdf->Ln(4);
$pdf->SetFont('Arial', 'I', 7);
$pdf->Cell(50, 4, t('¡Gracias por su compra!'), 0, 1, 'C');
$pdf->Cell(50, 4, '.', 0, 1, 'C'); 

ob_end_clean(); 
$pdf->Output('I', 'Ticket_'.$id.'.pdf');