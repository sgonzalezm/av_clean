<?php
session_start();

// 1. Verificamos que el ID realmente exista para evitar errores
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = $_GET['id'];

    // 2. Si el carrito no existe, lo creamos
    if (!isset($_SESSION['carrito'])) {
        $_SESSION['carrito'] = array();
    }

    // 3. Añadimos el producto o aumentamos cantidad
    if (isset($_SESSION['carrito'][$id])) {
        $_SESSION['carrito'][$id]++;
    } else {
        $_SESSION['carrito'][$id] = 1;
    }

    // 4. En lugar de redireccionar, enviamos una respuesta de éxito
    // Esto es lo que el "fetch" de tu JS recibirá
    echo "success"; 
} else {
    // Si no hay ID, enviamos un código de error
    http_response_code(400);
    echo "ID de producto no recibido";
}
?>