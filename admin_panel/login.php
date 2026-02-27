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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="login-box">
        <h2>
            <i class="fas fa-store"></i>
            Panel de Administración
        </h2>
        
        <?php if (isset($error)): ?>
            <p class="error">
                <?php echo htmlspecialchars($error); ?>
            </p>
        <?php endif; ?>
        
        <form method="POST">
            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" 
                       name="usuario" 
                       placeholder="Usuario" 
                       required 
                       autocomplete="username"
                       autofocus>
            </div>
            
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" 
                       name="password" 
                       placeholder="Contraseña" 
                       required
                       autocomplete="current-password">
            </div>
            
            <button type="submit">
                <i class="fas fa-sign-in-alt"></i>
                Ingresar
            </button>
        </form>
        
        <!-- Opcional: Agregar enlace para recuperar contraseña -->
        <div class="login-footer">
            <a href="recuperar.php">
                <i class="fas fa-question-circle"></i>
                ¿Olvidaste tu contraseña?
            </a>
        </div>
    </div>
    
    <script src="../js/admin.js"></script>
</body>
</html>