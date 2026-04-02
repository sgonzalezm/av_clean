<?php
/**
 * actualizar_carrito.php
 * Sistema: AHD Clean - Gestión de Carrito
 */
session_start();

if (isset($_GET['accion'])) {
    $accion = $_GET['accion'];

    // 1. Acción: Vaciar todo el carrito
    if ($accion == 'vaciar') {
        unset($_SESSION['carrito']);
    }

    // 2. Acción: Eliminar un producto específico por ID
    if ($accion == 'eliminar' && isset($_GET['id'])) {
        $id = $_GET['id'];
        unset($_SESSION['carrito'][$id]);
    }

    // 3. Acción: Modificar cantidad (+ o -) de un producto
    if ($accion == 'cantidad' && isset($_GET['id']) && isset($_GET['meta'])) {
        $id = $_GET['id'];
        $meta = $_GET['meta'];

        if ($meta == 'mas') {
            $_SESSION['carrito'][$id]++;
        } elseif ($meta == 'menos') {
            $_SESSION['carrito'][$id]--;
            // Si la cantidad llega a 0 o menos, lo eliminamos del carrito
            if ($_SESSION['carrito'][$id] <= 0) {
                unset($_SESSION['carrito'][$id]);
            }
        }
    }

    // 4. Acción: Intercambiar Presentación (SWAP) 
    // Esta versión mantiene el ORDEN original de los productos en la tabla
    if ($accion == 'swap' && isset($_GET['id_actual']) && isset($_GET['id_nuevo'])) {
        $id_actual = $_GET['id_actual'];
        $id_nuevo = $_GET['id_nuevo'];

        if (isset($_SESSION['carrito'][$id_actual])) {
            $nuevo_carrito = []; // Contenedor temporal para reconstruir el carrito

            foreach ($_SESSION['carrito'] as $key => $cantidad) {
                if ($key == $id_actual) {
                    // Detectamos la posición del producto viejo e insertamos el nuevo aquí mismo
                    $nuevo_carrito[$id_nuevo] = $cantidad;
                } else {
                    // Si no es el ID que estamos cambiando, lo pasamos tal cual al nuevo orden
                    $nuevo_carrito[$key] = $cantidad;
                }
            }

            // Reemplazamos el carrito desordenado por el nuevo carrito ordenado
            $_SESSION['carrito'] = $nuevo_carrito;
        }
    }
}

// Redirigir siempre de vuelta a la vista del carrito para ver los cambios aplicados
header("Location: ver_carrito.php");
exit();