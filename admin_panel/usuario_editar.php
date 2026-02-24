<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();
// Solo admin puede editar usuarios
verificarRol(['admin']);

$id = $_GET['id'] ?? 0;
$usuario = $pdo->prepare("SELECT * FROM usuarios_admin WHERE id = ?");
$usuario->execute([$id]);
$u = $usuario->fetch();

if (!$u) {
    header('Location: usuarios.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = $_POST['nombre'];
    $email = $_POST['email'];
    $usuario = $_POST['usuario'];
    $rol = $_POST['rol'];
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    // Verificar si el usuario o email ya existen (excepto el actual)
    $check = $pdo->prepare("SELECT id FROM usuarios_admin WHERE (usuario = ? OR email = ?) AND id != ?");
    $check->execute([$usuario, $email, $id]);
    
    if ($check->rowCount() > 0) {
        $error = "El usuario o email ya existe";
    } else {
        // Si se proporcionó nueva contraseña
        if (!empty($_POST['nueva_password'])) {
            $password_hash = password_hash($_POST['nueva_password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios_admin SET nombre=?, email=?, usuario=?, password=?, rol=?, activo=? WHERE id=?");
            $result = $stmt->execute([$nombre, $email, $usuario, $password_hash, $rol, $activo, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE usuarios_admin SET nombre=?, email=?, usuario=?, rol=?, activo=? WHERE id=?");
            $result = $stmt->execute([$nombre, $email, $usuario, $rol, $activo, $id]);
        }
        
        if ($result) {
            header('Location: usuarios.php?ok=2');
            exit;
        } else {
            $error = "Error al actualizar";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Editar Usuario</title>
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
        <h1>✏️ Editar Usuario</h1>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <label>Nombre Completo</label>
            <input type="text" name="nombre" value="<?php echo htmlspecialchars($u['nombre']); ?>" required>
            
            <label>Email</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($u['email']); ?>" required>
            
            <label>Nombre de Usuario</label>
            <input type="text" name="usuario" value="<?php echo htmlspecialchars($u['usuario']); ?>" required>
            
            <div class="password-section">
                <h4 style="margin-top: 0;">Cambiar Contraseña</h4>
                <label>Nueva Contraseña (dejar vacío para no cambiar)</label>
                <input type="password" name="nueva_password" minlength="6">
                <div class="info">Mínimo 6 caracteres. Solo llenar si quieres cambiar la contraseña</div>
            </div>
            
            <label>Rol</label>
            <select name="rol" required>
                <option value="admin" <?php echo $u['rol']=='admin'?'selected':''; ?>>Administrador (Acceso total)</option>
                <option value="editor" <?php echo $u['rol']=='editor'?'selected':''; ?>>Editor (Puede gestionar productos)</option>
                <option value="visitante" <?php echo $u['rol']=='visitante'?'selected':''; ?>>Visitante (Solo ver)</option>
            </select>
            
            <div class="checkbox">
                <input type="checkbox" name="activo" id="activo" <?php echo $u['activo']?'checked':''; ?>>
                <label for="activo" style="display: inline; margin: 0;">Usuario activo</label>
            </div>
            
            <div class="button-group">
                <button type="submit" class="btn-guardar">Actualizar Usuario</button>
                <a href="usuarios.php" class="btn-cancelar">Cancelar</a>
            </div>
        </form>
    </div>
    <script src="../js/admin.js"></script>
</body>
</html>