<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// Guardar componente de fórmula
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['asignar_insumo'])) {
    $prod_id = $_POST['producto_id'];
    $ins_id = $_POST['insumo_id'];
    $cant = $_POST['cantidad']; // Cantidad por 1 Litro
    $stmt = $pdo->prepare("INSERT INTO formulas (producto_id, insumo_id, cantidad_por_litro) VALUES (?, ?, ?)");
    $stmt->execute([$prod_id, $ins_id, $cant]);
}

$productos = $pdo->query("SELECT id, nombre FROM productos ORDER BY nombre ASC")->fetchAll();
$insumos_lista = $pdo->query("SELECT id, nombre, unidad_medida FROM insumos ORDER BY nombre ASC")->fetchAll();

// Ver fórmulas actuales
$formulas = $pdo->query("SELECT f.*, p.nombre as producto, i.nombre as insumo, i.unidad_medida 
                         FROM formulas f 
                         JOIN productos p ON f.producto_id = p.id 
                         JOIN insumos i ON f.insumo_id = i.id 
                         ORDER BY p.nombre ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Fórmulas de Producción - AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <div class="header">
            <h1><i class="fas fa-microscope"></i> Recetario / Fórmulas</h1>
        </div>

        <div class="card" style="margin-bottom:20px; padding:20px;">
            <h3><i class="fas fa-plus"></i> Agregar Insumo a una Fórmula</h3>
            <form method="POST" style="display:grid; grid-template-columns: 1fr 1fr 1fr auto; gap:10px; align-items:end;">
                <div>
                    <label>Producto Final:</label>
                    <select name="producto_id" class="form-control" required>
                        <?php foreach($productos as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo $p['nombre']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Insumo:</label>
                    <select name="insumo_id" class="form-control" required>
                        <?php foreach($insumos_lista as $il): ?>
                            <option value="<?php echo $il['id']; ?>"><?php echo $il['nombre']; ?> (<?php echo $il['unidad_medida']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Cantidad x 1 Litro:</label>
                    <input type="number" name="cantidad" step="0.0001" class="form-control" required placeholder="0.0000">
                </div>
                <button type="submit" name="asignar_insumo" class="btn">Asignar</button>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Insumo Requerido</th>
                    <th>Cant. por Litro</th>
                    <th>Para 20 Lts</th>
                    <th>Para 100 Lts</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($formulas as $f): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($f['producto']); ?></strong></td>
                    <td><?php echo htmlspecialchars($f['insumo']); ?></td>
                    <td><?php echo $f['cantidad_por_litro'] . ' ' . $f['unidad_medida']; ?></td>
                    <td><?php echo ($f['cantidad_por_litro'] * 20) . ' ' . $f['unidad_medida']; ?></td>
                    <td><?php echo ($f['cantidad_por_litro'] * 100) . ' ' . $f['unidad_medida']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>