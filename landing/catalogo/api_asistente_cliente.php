<?php
include '../includes/conexion.php';

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);
$mensajeUsuario = $input['mensaje'] ?? '';

if (empty($mensajeUsuario)) {
    echo json_encode(['respuesta' => '¡Hola! Soy el asistente de AHD Clean. ¿En qué puedo ayudarte?']);
    exit;
}

// 1. OBTENER CATÁLOGO DE PRODUCTOS
$stmt = $pdo->query("SELECT nombre, descripcion, precio FROM productos");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$catalogoTexto = "Eres el asistente experto de AHD Clean. Tu catálogo es:\n";
foreach($productos as $p) {
    $catalogoTexto .= "- {$p['nombre']}: {$p['descripcion']}. Precio: ${$p['precio']} MXN.\n";
}

// 2. CONFIGURACIÓN DE GEMINI
$apiKey = ''; // Pega aquí tu llave de Google AI Studio
$url = "" . $apiKey;

// Estructura de datos para Gemini
$data = [
    "contents" => [
        [
            "parts" => [
                ["text" => $catalogoTexto . "\n\nCliente pregunta: " . $mensajeUsuario]
            ]
        ]
    ],
    "generationConfig" => [
        "temperature" => 0.7,
        "maxOutputTokens" => 800
    ]
];

// 3. ENVIAR PETICIÓN (CURL)
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Útil si Hostinger tiene problemas con certificados

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['respuesta' => 'Lo siento, tuve un problema de conexión.']);
} else {
    $resArray = json_decode($response, true);
    // Gemini devuelve la respuesta en este camino específico del JSON:
    $respuestaIA = $resArray['candidates'][0]['content']['parts'][0]['text'] ?? 'No pude procesar esa consulta.';
    echo json_encode(['respuesta' => $respuestaIA]);
}