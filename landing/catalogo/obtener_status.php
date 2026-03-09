<?php
// Incluimos la conexión
include '../includes/conexion.php';

// Establecemos que la respuesta será un JSON
header('Content-Type: application/json');

// Recibimos el ID del pedido
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        
        // Buscamos el status del pedido
        $stmt = $pdo->prepare("SELECT status FROM pedidos WHERE id = ?");
        $stmt->execute([$id]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pedido) {
            // Si existe, enviamos el status
            echo json_encode([
                'status' => $pedido['status']
            ]);
        } else {
            // Si no existe el ID
            echo json_encode(['error' => 'Pedido no encontrado']);
        }

    } catch (PDOException $e) {
        echo json_encode(['error' => 'Error de conexión']);
    }
} else {
    echo json_encode(['error' => 'ID no válido']);
}