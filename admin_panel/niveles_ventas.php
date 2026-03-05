<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        foreach ($_POST['niveles'] as $id => $datos) {
            $stmt = $pdo->prepare("UPDATE niveles_ventas SET meta_minima = ?, comision_porcentaje = ?, color_hex = ? WHERE id = ?");
            $stmt->execute([$datos['meta'], $datos['comision'], $datos['color'], $id]);
        }
        $mensaje_ok = "Niveles actualizados correctamente.";
    } catch (Exception $e) { $error = "Error: " . $e->getMessage(); }
}

$stmt = $pdo->query("SELECT * FROM niveles_ventas ORDER BY meta_minima ASC");
$niveles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Configuración de Niveles</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <!-- Botón toggle para móvil -->
    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <!-- Sidebar (menú lateral) -->
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header">
            <div class="header-left">
                <h1><i class="fas fa-trophy"></i> Metas de Ventas</h1>
            </div>
            <a href="configuracion.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver a Configuración
            </a>
        </div>

        <?php if (isset($mensaje_ok)): ?>
            <div class="mensaje success"><i class="fas fa-check-circle"></i> <?php echo $mensaje_ok; ?></div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" class="slide-in">
                <div class="niveles-stack" style="display: flex; flex-direction: column; gap: 15px;">
                    <?php foreach ($niveles as $n): ?>
                        <div class="card-nivel-edit" style="border-left: 10px solid <?php echo $n['color_hex']; ?>; padding: 20px; background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <h3 style="margin:0;"><i class="<?php echo $n['icono']; ?>"></i> Nivel <?php echo $n['nombre']; ?></h3>
                                <input type="color" name="niveles[<?php echo $n['id']; ?>][color]" value="<?php echo $n['color_hex']; ?>" style="border:none; width:40px; height:40px; cursor:pointer;">
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label>Venta Mínima ($)</label>
                                    <input type="number" step="0.01" name="niveles[<?php echo $n['id']; ?>][meta]" class="form-control" value="<?php echo $n['meta_minima']; ?>">
                                </div>
                                <div class="form-group">
                                    <label>Comisión (%)</label>
                                    <input type="number" step="0.01" name="niveles[<?php echo $n['id']; ?>][comision]" class="form-control" value="<?php echo $n['comision_porcentaje']; ?>">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="button-group" style="margin-top: 30px;">
                    <button type="submit" class="btn-guardar"><i class="fas fa-save"></i> Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
    <script src="../js/admin.js"></script>
</body>
</html>