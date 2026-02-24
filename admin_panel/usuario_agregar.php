<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();
// Solo admin puede agregar usuarios
verificarRol(['admin']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = $_POST['nombre'];
    $email = $_POST['email'];
    $usuario = $_POST['usuario'];
    $password = $_POST['password'];
    $rol = $_POST['rol'];
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    // Validar que el usuario o email no existan
    $check = $pdo->prepare("SELECT id FROM usuarios_admin WHERE usuario = ? OR email = ?");
    $check->execute([$usuario, $email]);
    
    if ($check->rowCount() > 0) {
        $error = "El usuario o email ya existe";
    } else {
        // Hash de la contraseña
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO usuarios_admin (nombre, email, usuario, password, rol, activo) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$nombre, $email, $usuario, $password_hash, $rol, $activo])) {
            header('Location: usuarios.php?ok=1');
            exit;
        } else {
            $error = "Error al guardar el usuario";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Agregar Usuario</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <!-- Botón toggle para móvil -->
    <button class="menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <div class="form-container">
        <h1>➕ Agregar Nuevo Usuario</h1>
        <form method="POST">
            <label>Nombre Completo
                <input type="text" name="nombre" required>
            </label>
            
            <label>Email
                <input type="email" name="email" required>
            </label>
            
            <label>Nombre de Usuario
                <input type="text" name="usuario" required>
            </label>
            <div class="info">Se usará para iniciar sesión</div>
            
            <label>Contraseña</label>
            <input type="password" name="password" required minlength="6">
            <div class="info">Mínimo 6 caracteres</div>
            
            <label>Rol</label>
            <select name="rol" required>
                <option value="admin">Administrador (Acceso total)</option>
                <option value="editor" selected>Editor (Puede gestionar productos)</option>
                <option value="visitante">Visitante (Solo ver)</option>
            </select>
            
            <div class="checkbox">
                <input type="checkbox" name="activo" id="activo" checked>
                <label for="activo" style="display: inline; margin: 0;">Usuario activo</label>
            </div>
            
            <div class="button-group">
                <button type="submit" class="btn-guardar">Guardar Usuario</button>
                <a href="usuarios.php" class="btn-cancelar">Cancelar</a>
            </div>
        </form>

         <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
    </div>
    <script src="../js/admin.js"></script>
</body>
</html>