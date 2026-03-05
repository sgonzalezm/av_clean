<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();
// Solo admin puede editar usuarios
verificarRol(['admin']);

$id = $_GET['id'] ?? 0;
$usuario_stmt = $pdo->prepare("SELECT * FROM usuarios_admin WHERE id = ?");
$usuario_stmt->execute([$id]);
$u = $usuario_stmt->fetch();

if (!$u) {
    header('Location: usuarios.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = $_POST['nombre'];
    $email = $_POST['email'];
    $usuario_val = $_POST['usuario'];
    $rol = $_POST['rol'];
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    // Verificar si el usuario o email ya existen (excepto el actual)
    $check = $pdo->prepare("SELECT id FROM usuarios_admin WHERE (usuario = ? OR email = ?) AND id != ?");
    $check->execute([$usuario_val, $email, $id]);
    
    if ($check->rowCount() > 0) {
        $error = "El nombre de usuario o email ya está registrado por otra cuenta.";
    } else {
        if (!empty($_POST['nueva_password'])) {
            $password_hash = password_hash($_POST['nueva_password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios_admin SET nombre=?, email=?, usuario=?, password=?, rol=?, activo=? WHERE id=?");
            $result = $stmt->execute([$nombre, $email, $usuario_val, $password_hash, $rol, $activo, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE usuarios_admin SET nombre=?, email=?, usuario=?, rol=?, activo=? WHERE id=?");
            $result = $stmt->execute([$nombre, $email, $usuario_val, $rol, $activo, $id]);
        }
        
        if ($result) {
            header('Location: usuarios.php?ok=2');
            exit;
        } else {
            $error = "Error crítico al intentar actualizar los datos.";
        }
    }
}

$user_sesion = $_SESSION['usuario'] ?? ['nombre' => 'Usuario', 'rol' => 'usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Editar Usuario | Panel Admin</title>
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
    <!-- Sidebar (menú lateral) -->
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header">
            <div class="header-left">
                <h1><i class="fas fa-user-edit"></i> Editar Usuario</h1>
            </div>
            <div>
                <a href="usuarios.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver a Usuarios
                </a>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="mensaje error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" class="slide-in">
                
                <div class="form-group">
                    <label for="nombre">
                        <i class="fas fa-id-card"></i> Nombre Completo
                    </label>
                    <input type="text" id="nombre" name="nombre" class="form-control" 
                           value="<?php echo htmlspecialchars($u['nombre']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> Correo Electrónico
                    </label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($u['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="usuario">
                        <i class="fas fa-user"></i> Nombre de Usuario
                    </label>
                    <input type="text" id="usuario" name="usuario" class="form-control" 
                           value="<?php echo htmlspecialchars($u['usuario']); ?>" required>
                </div>

                <div class="form-section-separator" style="margin: 20px 0; border-top: 1px dashed #ccc;"></div>

                <div class="form-group">
                    <label for="nueva_password">
                        <i class="fas fa-key"></i> Cambiar Contraseña
                    </label>
                    <input type="password" id="nueva_password" name="nueva_password" 
                           class="form-control" placeholder="Dejar vacío para mantener actual" minlength="6">
                    <div class="info">
                        <i class="fas fa-info-circle"></i> Solo llenar si desea actualizar la seguridad.
                    </div>
                </div>

                <div class="form-group">
                    <label for="rol">
                        <i class="fas fa-user-shield"></i> Nivel de Acceso (Rol)
                    </label>
                    <select id="rol" name="rol" class="form-control" required>
                        <option value="admin" <?php echo $u['rol']=='admin'?'selected':''; ?>>🛡️ Administrador (Acceso Total)</option>
                        <option value="editor" <?php echo $u['rol']=='editor'?'selected':''; ?>>✍️ Editor (Gestión de contenido)</option>
                        <option value="visitante" <?php echo $u['rol']=='visitante'?'selected':''; ?>>👁️ Visitante (Solo lectura)</option>
                    </select>
                </div>

                <div class="form-group checkbox">
                    <input type="checkbox" id="activo" name="activo" <?php echo $u['activo']?'checked':''; ?>>
                    <label for="activo">Permitir acceso al sistema (Usuario Activo)</label>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-guardar">
                        <i class="fas fa-sync-alt"></i> Actualizar Usuario
                    </button>
                    <a href="usuarios.php" class="btn-cancelar">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/admin.js"></script>
    <script>
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const toggle = document.querySelector('.menu-toggle');
            if (window.innerWidth <= 768 && sidebar && toggle) {
                if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });
    </script>
</body>
</html>