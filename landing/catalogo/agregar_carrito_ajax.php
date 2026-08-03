<?php
session_start();

// Inicializar carrito
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$response = ['success' => false, 'message' => 'Producto no encontrado', 'total_items' => 0];

if ($id > 0) {
    // Agregar al carrito
    if (isset($_SESSION['carrito'][$id])) {
        $_SESSION['carrito'][$id]['cantidad'] += 1;
    } else {
        $_SESSION['carrito'][$id] = [
            'id' => $id,
            'cantidad' => 1
        ];
    }
    
    // Calcular total de items
    $total = 0;
    foreach ($_SESSION['carrito'] as $item) {
        $total += $item['cantidad'];
    }
    
    $response = [
        'success' => true,
        'message' => '¡Producto agregado al carrito!',
        'total_items' => $total
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
?>