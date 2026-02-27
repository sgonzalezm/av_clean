<?php
session_start();

if (isset($_GET['accion'])) {
    $accion = $_GET['accion'];

    // Acción: Vaciar todo
    if ($accion == 'vaciar') {
        unset($_SESSION['carrito']);
    }

    // Acción: Eliminar un producto específico
    if ($accion == 'eliminar' && isset($_GET['id'])) {
        $id = $_GET['id'];
        unset($_SESSION['carrito'][$id]);
    }

    // Acción: Modificar cantidad (+ o -)
    if ($accion == 'cantidad' && isset($_GET['id']) && isset($_GET['meta'])) {
        $id = $_GET['id'];
        $meta = $_GET['meta'];

        if ($meta == 'mas') {
            $_SESSION['carrito'][$id]++;
        } elseif ($meta == 'menos') {
            $_SESSION['carrito'][$id]--;
            // Si la cantidad llega a 0, eliminamos el item
            if ($_SESSION['carrito'][$id] <= 0) {
                unset($_SESSION['carrito'][$id]);
            }
        }
    }
}

// Redirigir siempre de vuelta al carrito para ver los cambios
header("Location: ver_carrito.php");
exit();
?>