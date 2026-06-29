<?php
// ACTIVAR MODO DE DEPURACIÓN (solo para pruebas)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// 1. Configuración y Conexión - RUTA CORREGIDA
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/conexion.php';

// 2. Validación de Seguridad (API KEY)
$headers = getallheaders();
$api_key_servidor = '3Lk28$.n37';  // Cambia esto por tu clave

if (!isset($headers['X-API-KEY']) || $headers['X-API-KEY'] !== $api_key_servidor) {
    http_response_code(401);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit;
}

// 3. Enrutamiento de la petición
$accion = $_GET['accion'] ?? '';

try {
    switch ($accion) {
        case 'consultar_inventario':
            // Consulta simple de inventario de insumos
            $stmt = $pdo->query("SELECT id, nombre, stock_actual, stock_minimo FROM insumos");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'consultar_tanques':
            // Consulta de tanques (fórmulas)
            $stmt = $pdo->query("SELECT id, nombre_formula, stock_litros_disponibles FROM formulas_maestras");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        default:
            echo json_encode(['error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en el servidor', 'detalle' => $e->getMessage()]);
}
?>