<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$id_producto = $_GET['id'] ?? null;

if (!$id_producto) {
    header('Location: formulas.php');
    exit;
}

// 1. Obtener datos del producto
$stmt = $pdo->prepare("SELECT nombre FROM productos WHERE id = ?");
$stmt->execute([$id_producto]);
$producto = $stmt->fetch();

if (!$producto) {
    die("Producto no encontrado.");
}

// 2. Lógica para ELIMINAR un insumo de la fórmula
if (isset($_GET['eliminar'])) {
    $id_formula = $_GET['eliminar'];
    $stmt = $pdo->prepare("DELETE FROM formulas WHERE id = ? AND producto_id = ?");
    $stmt->execute([$id_formula, $id_producto]);
    header("Location: editar_formula.php?id=$id_producto&msg=deleted");
    exit;
}

// 3. Lógica para AGREGAR un insumo a la fórmula
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_insumo'])) {
    $insumo_id = $_POST['insumo_id'];
    $cantidad = $_POST['cantidad']; // Cantidad por 1 Litro

    $stmt = $pdo->prepare("INSERT INTO formulas (producto_id, insumo_id, cantidad_por_litro) VALUES (?, ?, ?)");
    $stmt->execute([$id_producto, $insumo_id, $cantidad]);
    header("Location: editar_formula.php?id=$id_producto&msg=added");
    exit;
}

// 4. Obtener la lista de insumos de este producto
$sql_formula = "SELECT f.id, f.cantidad_por_litro, i.nombre as insumo, i.unidad_medida 
                FROM formulas f 
                JOIN insumos i ON f.insumo_id = i.id 
                WHERE f.producto_id = ? 
                ORDER BY i.nombre ASC";
$stmt_f = $pdo->prepare($sql_formula);
$stmt_f->execute([$id_producto]);
$lista_formula = $stmt_f->fetchAll();

// 5. Obtener todos los insumos disponibles para el dropdown
$insumos_disponibles = $pdo->query("SELECT id, nombre, unidad_medida FROM insumos ORDER BY nombre ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Editar Fórmula - <?php echo htmlspecialchars($producto['nombre']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .container-formula { max-width: 900px; margin: 20px auto; }
        .grid-form { display: grid; grid-template-columns: 2fr 1fr auto; gap: 15px; align-items: end; background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .btn-delete { color: #e53e3e; cursor: pointer; border: none; background: none; font-size: 1.1rem; }
        .btn-delete:hover { color: #c53030; }
        .total-row { background: #edf2f7; font-weight: bold; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="container-formula">
            <div class="header">
                <div>
                    <h1><i class="fas fa-flask"></i> Receta: <?php echo htmlspecialchars($producto['nombre']); ?></h1>
                    <a href="formulas.php" style="color: #4299e1; text-decoration:none;"><i class="fas fa-arrow-left"></i> Volver al listado</a>
                </div>
            </div>

            <div class="card" style="margin-bottom: 30px;">
                <h3 style="margin-bottom: 15px;"><i class="fas fa-plus-circle"></i> Agregar Ingrediente</h3>
                <form method="POST" class="grid-form">
                    <div>
                        <label>Seleccionar Insumo:</label>
                        <select name="insumo_id" class="form-control" required>
                            <option value="">-- Buscar Insumo --</option>
                            <?php foreach($insumos_disponibles as $ins): ?>
                                <option value="<?php echo $ins['id']; ?>">
                                    <?php echo htmlspecialchars($ins['nombre']); ?> (<?php echo $ins['unidad_medida']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Cant. x 1 Litro/Kg:</label>
                        <input type="number" name="cantidad" step="0.00001" class="form-control" placeholder="0.000" required>
                    </div>
                    <button type="submit" name="agregar_insumo" class="btn">
                        <i class="fas fa-plus"></i> Añadir
                    </button>
                </form>
            </div>

            <div class="card" style="padding: 0; overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #2d3748; color: white; text-align: left;">
                            <th style="padding: 12px;">Ingrediente</th>
                            <th style="padding: 12px;">Por 1 L/Kg</th>
                            <th style="padding: 12px;">Para 20 L/Kg</th>
                            <th style="padding: 12px;">Para 100 L/Kg</th>
                            <th style="padding: 12px; text-align: center;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lista_formula)): ?>
                            <tr><td colspan="5" style="padding: 20px; text-align: center; color: #a0aec0;">Esta fórmula no tiene ingredientes registrados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lista_formula as $item): ?>
                            <tr style="border-bottom: 1px solid #edf2f7;">
                                <td style="padding: 12px;"><?php echo htmlspecialchars($item['insumo']); ?></td>
                                <td style="padding: 12px;"><?php echo number_format($item['cantidad_por_litro'], 4); ?> <small><?php echo $item['unidad_medida']; ?></small></td>
                                <td style="padding: 12px; color: #4a5568;"><?php echo number_format($item['cantidad_por_litro'] * 20, 3); ?></td>
                                <td style="padding: 12px; color: #4a5568;"><?php echo number_format($item['cantidad_por_litro'] * 100, 3); ?></td>
                                <td style="padding: 12px; text-align: center;">
                                    <a href="editar_formula.php?id=<?php echo $id_producto; ?>&eliminar=<?php echo $item['id']; ?>" 
                                       onclick="return confirm('¿Eliminar este ingrediente de la receta?')" 
                                       class="btn-delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="../js/admin.js"></script>
</body>
</html>