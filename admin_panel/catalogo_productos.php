<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// Función para verificar permisos (Mantenida comentada)
/*if (!function_exists('tienePermiso')) {
    function tienePermiso($rol_requerido = 'editor') {
        $roles_jerarquia = ['visitante' => 1, 'editor' => 2, 'admin' => 3];
        $usuario_rol = $_SESSION['admin_rol'] ?? 'visitante';
        return ($roles_jerarquia[$usuario_rol] ?? 1) >= $roles_jerarquia[$rol_requerido];
    }
}*/

// Obtener solo productos y precios
$query = "SELECT id, nombre, categoria, precio FROM productos ORDER BY id DESC";
$productos = $pdo->query($query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Gestionar Productos</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .header-actions { display: flex; gap: 10px; align-items: center; }
        .btn-excel { background-color: #27ae60 !important; color: white !important; text-decoration: none; padding: 10px 15px; border-radius: 5px; font-size: 14px; }
        .btn-excel:hover { background-color: #219150 !important; }
    </style>
</head>
<body>
    <!-- Botón toggle para móvil -->
    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <!-- Sidebar (menú lateral) -->
    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <div class="header">
            <div>
                <h1>Gestión de Productos</h1>
                <?php if (!tienePermiso('editor')): ?>
                    <span class="badge-rol">Modo solo lectura</span>
                <?php endif; ?>
            </div>
            
            <div class="header-actions">
                <a href="exportar_productos.php" class="btn btn-excel">
                    <i class="fas fa-file-excel"></i> Exportar Excel
                </a>

                <?php if (tienePermiso('editor')): ?>
                    <a href="catalogo_agregar.php" class="btn">➕ Nuevo Producto</a>
                <?php else: ?>
                    <span class="btn disabled">🔒 Solo lectura</span>
                <?php endif; ?>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Categoría</th>
                    <th>Precio</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $p): ?>
                <tr>
                    <td><?php echo $p['id']; ?></td>
                    <td><?php echo htmlspecialchars($p['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($p['categoria']); ?></td>
                    <td>$<?php echo number_format($p['precio'], 2); ?></td>
                    <td>
                        <?php if (tienePermiso('editor')): ?>
                            <a href="catalogo_editar.php?id=<?php echo $p['id']; ?>" class="btn-small">✏️ Editar</a>
                            <a href="catalogo_eliminar.php?id=<?php echo $p['id']; ?>" class="btn-small red" onclick="return confirm('¿Eliminar?')">🗑️</a>
                        <?php else: ?>
                            <span class="btn-small disabled">🔍 Ver</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script src="../js/admin.js"></script>
</body>
</html>