<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$filename = "catalogo_productos_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // Soporte acentos

// Encabezados simples
fputcsv($output, ['ID', 'Nombre', 'Categoría', 'Precio'], ',');

try {
    // Consulta directa a la tabla productos
    $stmt = $pdo->query("SELECT id, nombre, categoria, precio FROM productos ORDER BY id DESC");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['nombre'],
            $row['categoria'],
            number_format($row['precio'], 2, '.', '')
        ], ',');
    }

} catch (PDOException $e) {
    die("Error al exportar: " . $e->getMessage());
}

fclose($output);
exit;