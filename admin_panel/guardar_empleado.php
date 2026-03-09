<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Recibir y sanitizar datos
    $id = !empty($_POST['id']) ? intval($_POST['id']) : null;
    $nombre = trim($_POST['nombre']);
    $puesto = trim($_POST['puesto']);
    $sueldo_diario = floatval($_POST['sueldo_diario']);
    $fecha_ingreso = $_POST['fecha_ingreso'];
    $clabe = trim($_POST['clabe']);

    try {
        if ($id) {
            // LÓGICA DE ACTUALIZACIÓN
            $sql = "UPDATE empleados SET 
                        nombre = ?, 
                        puesto = ?, 
                        sueldo_diario = ?, 
                        fecha_ingreso = ?, 
                        clabe_interbancaria = ? 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $puesto, $sueldo_diario, $fecha_ingreso, $clabe, $id]);
            $mensaje = "Empleado actualizado correctamente.";
        } else {
            // LÓGICA DE INSERCIÓN NUEVA
            $sql = "INSERT INTO empleados (nombre, puesto, sueldo_diario, fecha_ingreso, clabe_interbancaria, estatus) 
                    VALUES (?, ?, ?, ?, ?, 'Activo')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $puesto, $sueldo_diario, $fecha_ingreso, $clabe]);
            $mensaje = "Nuevo empleado registrado con éxito.";
        }

        // Redirigir de vuelta al HUB de nómina con éxito
        header("Location: nomina.php?ok=1&msj=" . urlencode($mensaje));
        exit;

    } catch (PDOException $e) {
        // En caso de error, regresamos con el mensaje de error
        header("Location: nomina.php?error=1&msj=" . urlencode("Error en la base de datos: " . $e->getMessage()));
        exit;
    }
} else {
    // Si alguien intenta entrar directamente al archivo sin POST
    header("Location: nomina.php");
    exit;
}