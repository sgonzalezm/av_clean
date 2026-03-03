<?php
session_start();
include '../includes/conexion.php';

// 1. Configurar la cabecera para que el navegador descargue el archivo
$filename = "pedidos_ahd_clean_" . date('Y-m-d_H-i') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// 2. Abrir el flujo de salida hacia el navegador
$output = fopen('php://output', 'w');

// 3. (Opcional) Corregir caracteres especiales para Excel en Windows (BOM UTF-8)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// 4. Definir los títulos de las columnas
fputcsv($output, ['Folio', 'Fecha', 'Email Cliente', 'Teléfono', 'Domicilio', 'Total', 'Estado']);

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    
    // 5. Verificar si hay un filtro de estado en la URL (opcional)
    $estado_filtro = $_GET['estado'] ?? null;
    
    if ($estado_filtro) {
        $stmt = $pdo->prepare("SELECT id, fecha_pedido, email, telefono, domicilio, total, status FROM pedidos WHERE status = ? ORDER BY id DESC");
        $stmt->execute([$estado_filtro]);
    } else {
        $stmt = $pdo->query("SELECT id, fecha_pedido, email, telefono, domicilio, total, status FROM pedidos ORDER BY id DESC");
    }

    // 6. Recorrer los datos y escribirlos en el CSV
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Formatear la fecha para que sea más legible en Excel
        $row['fecha_pedido'] = date('d/m/Y H:i', strtotime($row['fecha_pedido']));
        // Asegurar que el total sea un número limpio
        $row['total'] = number_format($row['total'], 2, '.', '');
        
        fputcsv($output, $row);
    }

} catch (PDOException $e) {
    // En un script de descarga, los errores de texto pueden romper el archivo, 
    // pero es bueno saber si algo falla.
    die("Error al exportar: " . $e->getMessage());
}

fclose($output);
exit;