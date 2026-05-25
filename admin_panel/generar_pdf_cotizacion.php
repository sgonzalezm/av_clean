<?php
// 1. Limpieza estricta de salida para evitar errores de corrupción del PDF
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../includes/session.php';
require_once '../includes/conexion.php';
require_once '../includes/fpdf.php';

verificarSesion();

// Validar que se reciba un ID válido
$id_cotizacion = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id_cotizacion <= 0) {
    die("Error: Folio de cotización no válido.");
}

// 2. CONSULTA DE ENCABEZADO (Cotización, Cliente y Vendedor)
$sql_coti = "SELECT c.*, cl.nombre_completo AS cliente_nombre, cl.telefono AS cliente_telefono, u.nombre AS vendedor_nombre 
             FROM cotizaciones c
             INNER JOIN clientes cl ON c.cliente_id = cl.id
             LEFT JOIN usuarios_admin u ON c.usuario_id = u.id
             WHERE c.id = ?";
$stmt = $pdo->prepare($sql_coti);
$stmt->execute([$id_cotizacion]);
$cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cotizacion) {
    die("Error: La cotización solicitada no existe.");
}

// 3. CONSULTA DE DETALLES (Artículos)
$sql_det = "SELECT d.*, p.nombre AS producto_nombre 
            FROM detalle_cotizacion d 
            LEFT JOIN productos p ON d.producto_id = p.id 
            WHERE d.cotizacion_id = ?";
$stmt_det = $pdo->prepare($sql_det);
$stmt_det->execute([$id_cotizacion]);
$articulos = $stmt_det->fetchAll(PDO::FETCH_ASSOC);

// Función auxiliar para sanitizar caracteres especiales a formato ISO-8859-1 (FPDF Nativo)
function t($texto) {
    return iconv('UTF-8', 'windows-1252//TRANSLIT', $texto ?? '');
}

// =========================================================================
// CLASE EXTENDIDA DE FPDF PARA DISEÑO MINIMALISTA Y FORMAL CON EL NUEVO FOOTER
// =========================================================================
class PDF_Cotizacion extends FPDF {
    // Pie de página elegante, formal y sumamente chulo
    function Footer() {
        // Posicionarse a 30 mm del final de la página para darle aire al bloque
        $this->SetY(-32);
        
        // Línea sutil superior que delimita el footer
        $this->SetDrawColor(226, 232, 240);
        $this->SetLineWidth(0.4);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(3);
        
        // --- Bloque del QR (Esquina Inferior Izquierda) ---
        // Generamos dinámicamente el QR apuntando a tu web de forma limpia
        $url_qr = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://ahd-clean.com";
        // Dibujamos el QR en X=15, Y actual, con un tamaño cuadrado discreto de 18mm
        $current_y = $this->GetY();
        $this->Image($url_qr, 15, $current_y, 18, 18, 'PNG');
        
        // --- Bloque de Información de Contacto (Al lado del QR) ---
        $this->SetXY(37, $current_y + 1); // Desplazar a la derecha del QR
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(71, 85, 105); // Slate gris medio
        $this->Cell(100, 4, t('AHD CLEAN - ATENCIÓN COMERCIAL'), 0, 1, 'L');
        
        $this->SetX(37);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(100, 116, 139); // Slate suave
        $this->Cell(100, 4, t('Teléfono: 33 2898 8987'), 0, 1, 'L');
        
        $this->SetX(37);
        $this->Cell(100, 4, t('Correo: ventas@ahd-clean.com'), 0, 1, 'L');
        
        $this->SetX(37);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(100, 4, t('Visítanos en: ahd-clean.com'), 0, 1, 'L');
        
        // --- Bloque del Paginador (Esquina Inferior Derecha) ---
        $this->SetXY(135, $current_y + 7);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(148, 163, 184); // Gris tenue elegante
        $this->Cell(60, 5, t('Página ') . $this->PageNo() . t(' de {nb}'), 0, 0, 'R');
    }
}

// Inicializar documento en formato A4 / Vertical
$pdf = new PDF_Cotizacion('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage();

// =========================================================================
// ENCABEZADO MINIMALISTA
// =========================================================================
// Nombre de la marca principal
$pdf->SetFont('Arial', 'B', 22);
$pdf->SetTextColor(30, 41, 59); // Slate oscuro corporativo
$pdf->Cell(110, 10, t('AHD Clean'), 0, 0, 'L');

// Título del documento e identificador de Folio
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(49, 130, 206); // Azul primario del portal
$pdf->Cell(70, 10, t('COTIZACIÓN FORMAL'), 0, 1, 'R');

$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(110, 5, t('Industrial - Home - Automotive Solutions'), 0, 0, 'L');

$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(30, 41, 59);
$pdf->Cell(70, 5, t('Folio: #') . str_pad($cotizacion['id'], 5, "0", STR_PAD_LEFT), 0, 1, 'R');

$pdf->Ln(8);

// Línea sutil de división estética superior
$pdf->SetDrawColor(226, 232, 240);
$pdf->SetLineWidth(0.5);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(6);

// =========================================================================
// BLOQUE DE DATOS COMERCIALES (CLIENTE, EMISIÓN Y VENCIMIENTO)
// =========================================================================
$start_y = $pdf->GetY();

// Columna izquierda: Información del Cliente
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(95, 5, t('PREPARADO PARA:'), 0, 1, 'L');
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(30, 41, 59);
$pdf->Cell(95, 6, t($cotizacion['cliente_nombre']), 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(100, 116, 139);
if(!empty($cotizacion['cliente_telefono'])) {
    $pdf->Cell(95, 5, t('Teléfono: ') . t($cotizacion['cliente_telefono']), 0, 1, 'L');
}

// Retornar al mismo nivel para la columna derecha usando SetXY
$pdf->SetXY(115, $start_y);

// Columna derecha: Fechas y Estado
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(80, 5, t('DETALLES COMERCIALES:'), 0, 1, 'R');

$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(30, 41, 59);
$pdf->SetX(115);
$pdf->Cell(80, 5, t('Fecha Emisión: ') . date('d/m/Y', strtotime($cotizacion['fecha_emision'])), 0, 1, 'R');
$pdf->SetX(115);
$pdf->Cell(80, 5, t('Fecha Vencimiento: ') . date('d/m/Y', strtotime($cotizacion['fecha_vencimiento'])), 0, 1, 'R');
$pdf->SetX(115);
$pdf->Cell(80, 5, t('Atendió: ') . t($cotizacion['vendedor_nombre'] ?? 'Administrador'), 0, 1, 'R');

$pdf->Ln(10);

// =========================================================================
// TABLA DE ARTÍCULOS COTIZADOS
// =========================================================================
// Encabezado de la tabla con fondo sutil gris claro
$pdf->SetFillColor(241, 245, 249);
$pdf->SetTextColor(71, 85, 105);
$pdf->SetFont('Arial', 'B', 10);

$pdf->Cell(95, 8, t('Descripción del Artículo'), 1, 0, 'L', true);
$pdf->Cell(25, 8, t('Cantidad'), 1, 0, 'C', true);
$pdf->Cell(30, 8, t('Precio Unitario'), 1, 0, 'R', true);
$pdf->Cell(30, 8, t('Subtotal'), 1, 1, 'R', true);

// Cuerpo de la tabla (Listado de productos)
$pdf->SetTextColor(30, 41, 59);
$pdf->SetFont('Arial', '', 10);
$pdf->SetDrawColor(226, 232, 240); // Bordes suaves

foreach ($articulos as $art) {
    $nombre_producto = $art['producto_nombre'] ? $art['producto_nombre'] : 'Producto sin nombre (ID: ' . $art['producto_id'] . ')';
    
    // Medir altura requerida de la celda de descripción por si el nombre se desborda a 2 líneas
    $x_actual = $pdf->GetX();
    $y_actual = $pdf->GetY();
    
    // MultiCell dibuja el texto de descripción de forma segura en un ancho de 95mm
    $pdf->MultiCell(95, 6, t($nombre_producto), 'LBR', 'L');
    $final_y = $pdf->GetY();
    $altura_fila = $final_y - $y_actual;
    
    // Reubicar cursor a la derecha de la celda de descripción para colocar el resto de columnas en la misma línea
    $pdf->SetXY($x_actual + 95, $y_actual);
    
    // Imprimir columnas restantes alineando la altura calculada dinámicamente
    $pdf->Cell(25, $altura_fila, number_format($art['cantidad'], 1), 'BR', 0, 'C');
    $pdf->Cell(30, $altura_fila, '$' . number_format($art['precio_unitario'], 2), 'BR', 0, 'R');
    $pdf->Cell(30, $altura_fila, '$' . number_format($art['subtotal'], 2), 'BR', 1, 'R');
    
    // Dejar el puntero listo al final de la fila más alta
    $pdf->SetY($final_y);
}

// =========================================================================
// BLOQUE DE TOTALES Y CONDICIONES
// =========================================================================
$pdf->Ln(4);
$bloque_totales_y = $pdf->GetY();

// Sección izquierda de Notas o Condiciones Comerciales
if (!empty($cotizacion['notas'])) {
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->Cell(100, 5, t('TÉRMINOS Y CONDICIONES:'), 0, 1, 'L');
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->MultiCell(100, 4, t($cotizacion['notas']), 0, 'L');
}

// Sección derecha: Cuadro de totales formales
$pdf->SetXY(135, $bloque_totales_y);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(30, 6, t('Subtotal:'), 0, 0, 'L');
$pdf->SetTextColor(30, 41, 59);
$pdf->Cell(30, 6, '$' . number_format($cotizacion['subtotal'], 2), 0, 1, 'R');

$pdf->SetX(135);
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(49, 130, 206);
$pdf->Cell(30, 8, t('Total Neto:'), 0, 0, 'L');
$pdf->Cell(30, 8, '$' . number_format($cotizacion['total'], 2), 0, 1, 'R');

// 4. Salida final del documento PDF al navegador en modo visualización interactiva
ob_end_clean();
$pdf->Output('I', 'Cotizacion_AHD_' . str_pad($cotizacion['id'], 5, "0", STR_PAD_LEFT) . '.pdf');
exit;
?>