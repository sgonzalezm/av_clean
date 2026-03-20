<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// Guardar Insumo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nuevo_insumo'])) {
    $nombre = $_POST['nombre'];
    $unidad = $_POST['unidad'];
    $precio = $_POST['precio'];
    $stmt = $pdo->prepare("INSERT INTO insumos (nombre, unidad_medida, precio_unidad) VALUES (?, ?, ?)");
    $stmt->execute([$nombre, $unidad, $precio]);
}

$insumos = $pdo->query("SELECT * FROM insumos ORDER BY nombre ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Materias Primas - AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <div class="header">
            <h1><i class="fas fa-flask"></i> Materias Primas / Insumos</h1>
            <button class="btn" onclick="document.getElementById('modalInsumo').style.display='block'">
                <i class="fas fa-plus"></i> Nuevo Insumo
            </button>
        </div>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Unidad</th>
                    <th>Precio Unit.</th>
                    <th>Stock Actual</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($insumos as $i): ?>
                <tr>
                    <td><?php echo $i['id']; ?></td>
                    <td><?php echo htmlspecialchars($i['nombre']); ?></td>
                    <td><?php echo $i['unidad_medida']; ?></td>
                    <td>$<?php echo number_format($i['precio_unidad'], 2); ?></td>
                    <td><strong><?php echo $i['stock_actual']; ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="modalInsumo" class="modal" style="display:none; position:fixed; background:rgba(0,0,0,0.5); width:100%; height:100%; top:0; left:0;">
        <div style="background:white; width:400px; margin:10% auto; padding:20px; border-radius:8px;">
            <h2>Nuevo Insumo</h2>
            <form method="POST">
                <input type="hidden" name="nuevo_insumo" value="1">
                <label>Nombre:</label>
                <input type="text" name="nombre" class="form-control" required style="width:100%; margin-bottom:10px;">
                <label>Unidad (kg, lt, ml):</label>
                <input type="text" name="unidad" class="form-control" required style="width:100%; margin-bottom:10px;">
                <label>Precio Unitario:</label>
                <input type="number" name="precio" step="0.01" class="form-control" required style="width:100%; margin-bottom:20px;">
                <button type="submit" class="btn" style="width:100%">Guardar</button>
                <button type="button" onclick="this.parentElement.parentElement.parentElement.style.display='none'" style="width:100%; margin-top:10px; background:none; border:none; color:red; cursor:pointer;">Cancelar</button>
            </form>
        </div>
    </div>
</body>
</html>