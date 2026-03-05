<?php
session_start();
include '../includes/conexion.php';

// Conexión
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// 1. Lógica para actualizar estado si se recibe por POST
if(isset($_POST['actualizar_estado'])) {
    $id = $_POST['pedido_id'];
    $nuevo_estado = $_POST['nuevo_estado'];
    $stmt = $pdo->prepare("UPDATE pedidos SET status = ? WHERE id = ?");
    $stmt->execute([$nuevo_estado, $id]);
    header("Location: pedidos.php?msg=actualizado");
    exit;
}

// 2. Filtro de estado por URL
$filtro = $_GET['estado'] ?? 'Pendiente';

// 3. Consulta de pedidos
$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE status = ? ORDER BY fecha_pedido DESC");
$stmt->execute([$filtro]);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Panel de Pedidos - AHD Clean</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        /* Estilos rápidos para el panel */
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; overflow-x: auto; }
        .tab { padding: 10px 20px; text-decoration: none; color: #666; border-radius: 8px; font-weight: 600; white-space: nowrap; }
        .tab.active { background: #1a365d; color: white; }
        .tab-Pendiente.active { background: #ecc94b; color: #744210; } /* Amarillo */
        .tab-Confirmado.active { background: #4299e1; color: white; } /* Azul */
        .tab-Entregado.active { background: #48bb78; color: white; } /* Verde */

        .tabla-pedidos { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .tabla-pedidos th { background: #f8fafc; padding: 15px; text-align: left; color: #4a5568; }
        .tabla-pedidos td { padding: 15px; border-top: 1px solid #edf2f7; vertical-align: middle; }
        
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 0.85rem; font-weight: bold; }
        .export-btns { margin-bottom: 20px; display: flex; gap: 10px; }
        .btn-export { padding: 8px 15px; border-radius: 6px; text-decoration: none; font-size: 0.9rem; color: white; }
        
        .select-estado { padding: 5px; border-radius: 5px; border: 1px solid #cbd5e0; }
    </style>
</head>
<body>
    <!-- Botón toggle para móvil -->
    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <!-- Sidebar (menú lateral) -->
    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <div class="header">
            <h1>Gestión de Pedidos</h1>
            <div class="export-btns">
                <a href="exportar_csv.php" class="btn-export" style="background: #38a169;"><i class="fas fa-file-excel"></i> Excel/CSV</a>
                <a href="resumen_pdf.php" class="btn-export" style="background: #e53e3e;"><i class="fas fa-file-pdf"></i> Reporte PDF</a>
            </div>
        </div>

        <div class="tabs">
            <?php 
            $estados = ['Pendiente', 'Confirmado', 'En Camino', 'Entregado', 'Cancelado'];
            foreach($estados as $e): 
                $active = ($filtro == $e) ? 'active tab-'.$e : '';
            ?>
                <a href="?estado=<?php echo $e; ?>" class="tab <?php echo $active; ?>">
                    <?php echo $e; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <table class="tabla-pedidos">
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Cliente / Contacto</th>
                    <th>Fecha</th>
                    <th>Total</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($pedidos)): ?>
                    <tr><td colspan="5" style="text-align:center; padding:30px;">No hay pedidos en este estado.</td></tr>
                <?php endif; ?>

                <?php foreach($pedidos as $p): ?>
                <tr>
                    <td><strong>#<?php echo $p['id']; ?></strong></td>
                    <td>
                        <div style="font-size: 0.9rem;">
                            <i class="fas fa-user"></i> <?php echo $p['email']; ?><br>
                            <i class="fas fa-phone"></i> <?php echo $p['telefono']; ?>
                        </div>
                    </td>
                    <td><?php echo date('d/m/Y H:i', strtotime($p['fecha_pedido'])); ?></td>
                    <td style="font-weight: bold; color: #2d3748;">$<?php echo number_format($p['total'], 2); ?></td>
                    <td>
                        <div style="display:flex; gap:10px;">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="pedido_id" value="<?php echo $p['id']; ?>">
                                <select name="nuevo_estado" class="select-estado" onchange="this.form.submit()">
                                    <option value="">Cambiar a...</option>
                                    <?php foreach($estados as $est): ?>
                                        <option value="<?php echo $est; ?>" <?php echo ($p['estado'] == $est) ? 'disabled' : ''; ?>>
                                            <?php echo $est; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="actualizar_estado" value="1">
                            </form>
                            <a href="facturar.php?id=<?php echo $p['id']; ?>" title="Facturar" style="color:#4a5568;"><i class="fas fa-file-invoice fa-lg"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script src="../js/admin.js"></script>
</body>
</html>
