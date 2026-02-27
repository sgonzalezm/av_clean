<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();
// Solo admin puede agregar usuarios
verificarRol(['admin']);

// Obtener información del usuario para el sidebar
$user = $_SESSION['usuario'] ?? ['nombre' => 'Usuario', 'rol' => 'admin'];

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
    <title>Agregar Usuario | Panel Admin</title>
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

    <!-- Contenido principal - ¡ESTO FALTABA! -->
    <div class="main">
        <!-- Header / Barra superior -->
        <div class="header">
            <div class="header-left">
                <h1><i class="fas fa-user-plus"></i> Agregar Nuevo Usuario</h1>
            </div>
            <div>
                <a href="usuarios.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver a Usuarios
                </a>
            </div>
        </div>

        <!-- Mensajes de error -->
        <?php if (isset($error)): ?>
            <div class="mensaje error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Formulario con las clases correctas -->
        <div class="form-container">
            <form method="POST" class="slide-in">
                <div class="form-group">
                    <label for="nombre">
                        <i class="fas fa-user"></i> Nombre Completo
                    </label>
                    <input type="text" 
                           id="nombre" 
                           name="nombre" 
                           class="form-control" 
                           placeholder="Ej: Juan Pérez"
                           value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> Email
                    </label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="form-control" 
                           placeholder="ejemplo@correo.com"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="usuario">
                        <i class="fas fa-id-card"></i> Nombre de Usuario
                    </label>
                    <input type="text" 
                           id="usuario" 
                           name="usuario" 
                           class="form-control" 
                           placeholder="ej: juanperez123"
                           value="<?php echo isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : ''; ?>"
                           required>
                    <div class="info">
                        <i class="fas fa-info-circle"></i>
                        Se usará para iniciar sesión
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Contraseña
                    </label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-control" 
                           placeholder="••••••••"
                           minlength="6"
                           required>
                    <div class="info">
                        <i class="fas fa-info-circle"></i>
                        Mínimo 6 caracteres
                    </div>
                </div>

                <div class="form-group">
                    <label for="rol">
                        <i class="fas fa-shield-alt"></i> Rol
                    </label>
                    <select id="rol" name="rol" class="form-control" required>
                        <option value="admin" <?php echo (isset($_POST['rol']) && $_POST['rol'] == 'admin') ? 'selected' : ''; ?>>
                            👑 Administrador (Acceso total)
                        </option>
                        <option value="editor" <?php echo (!isset($_POST['rol']) || $_POST['rol'] == 'editor') ? 'selected' : ''; ?>>
                            ✏️ Editor (Puede gestionar productos)
                        </option>
                        <option value="visitante" <?php echo (isset($_POST['rol']) && $_POST['rol'] == 'visitante') ? 'selected' : ''; ?>>
                            👁️ Visitante (Solo ver)
                        </option>
                    </select>
                </div>

                <div class="form-group checkbox">
                    <input type="checkbox" 
                           name="activo" 
                           id="activo" 
                           <?php echo (!isset($_POST['activo']) || $_POST['activo'] == 'on') ? 'checked' : ''; ?>>
                    <label for="activo">
                        <i class="fas fa-check-circle"></i>
                        Usuario activo (puede iniciar sesión)
                    </label>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-guardar">
                        <i class="fas fa-save"></i> Guardar Usuario
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
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }

        // Cerrar sidebar al hacer clic fuera en móvil
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const toggle = document.querySelector('.menu-toggle');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });

        // Validar formulario antes de enviar
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            if (password.length < 6) {
                e.preventDefault();
                alert('La contraseña debe tener al menos 6 caracteres');
            }
        });
    </script>
</body>
</html>