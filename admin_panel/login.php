<?php
require_once '../includes/conexion.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = $_POST['usuario'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM usuarios_admin WHERE usuario = ? AND activo = 1");
    $stmt->execute([$usuario]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_nombre'] = $user['nombre'];
        $_SESSION['admin_usuario'] = $user['usuario'];
        $_SESSION['admin_rol'] = $user['rol'];
        
        // Actualizar último acceso
        $update = $pdo->prepare("UPDATE usuarios_admin SET ultimo_acceso = NOW() WHERE id = ?");
        $update->execute([$user['id']]);
        
        header('Location: index.php');
        exit;
    } else {
        $error = "Usuario o contraseña incorrectos";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Acceso Administrativo</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="login-box">
        <h2>🔐 Panel de Administración</h2>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="POST">
            <input type="text" name="usuario" placeholder="Usuario" required>
            <input type="password" name="password" placeholder="Contraseña" required>
            <button type="submit">Ingresar</button>
        </form>
    </div>
</body>
</html>