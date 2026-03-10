<?php
session_start();
include '../includes/conexion.php'; // Asegúrate de que esta ruta sea correcta

$accion = $_GET['accion'] ?? '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Establecer conexión PDO
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // --- CASO 1: REGISTRO DE NUEVO CLIENTE ---
        if ($accion == 'registro') {
            $nombre = trim($_POST['nombre']);
            $email = trim($_POST['email']);
            $pass_plano = $_POST['pass'];
            
            // Encriptamos la contraseña por seguridad
            $pass_hash = password_hash($pass_plano, PASSWORD_BCRYPT);

            // Insertamos con tipo_cliente_id = 1 (Público General por defecto)
            // y estatus 'Activo' como aparece en tu tabla
            $sql = "INSERT INTO clientes (tipo_cliente_id, nombre_completo, email, password, estatus) 
                    VALUES (1, ?, ?, ?, 'Activo')";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $email, $pass_hash]);

            // Auto-login: Guardamos los datos en la sesión para que el carrito aplique el descuento
            $_SESSION['cliente_id'] = $pdo->lastInsertId();
            $_SESSION['cliente_nombre'] = $nombre;
            $_SESSION['cliente_email'] = $email;
            $_SESSION['tipo_cliente'] = 1;

            // Redirigimos al carrito con un mensaje de éxito
            header("Location: ver_carrito.php?reg_ok=1");
            exit;
        }

        // --- CASO 2: LOGIN DE CLIENTE EXISTENTE ---
        if ($accion == 'login') {
            $email = trim($_POST['email']);
            $pass_intento = $_POST['pass'];

            // Buscamos al cliente por su email
            $stmt = $pdo->prepare("SELECT id, nombre_completo, email, password, tipo_cliente_id, estatus FROM clientes WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verificamos si existe, si está activo y si la contraseña coincide con el hash
            if ($user && $user['estatus'] == 'Activo' && password_verify($pass_intento, $user['password'])) {
                
                // Guardamos datos en sesión
                $_SESSION['cliente_id'] = $user['id'];
                $_SESSION['cliente_nombre'] = $user['nombre_completo'];
                $_SESSION['cliente_email'] = $user['email'];
                $_SESSION['tipo_cliente'] = $user['tipo_cliente_id'];

                header("Location: ver_carrito.php?login_ok=1");
                exit;
            } else {
                // Si los datos son incorrectos o el usuario no está activo
                header("Location: ver_carrito.php?error_auth=1");
                exit;
            }
        }

    } catch (PDOException $e) {
        // Manejo de errores específicos
        if ($e->getCode() == 23000) {
            // Error de duplicado (el email ya existe en la base de datos)
            header("Location: ver_carrito.php?error_dup=1");
        } else {
            // Otros errores de base de datos
            die("Error en el servidor: " . $e->getMessage());
        }
        exit;
    }
} else {
    // Si alguien intenta entrar a este archivo sin enviar el formulario
    header("Location: ver_carrito.php");
    exit;
}