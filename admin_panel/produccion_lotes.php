<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$mensaje_exito = "";
$error = "";
$reporte = [];

// 1. OBTENER PRODUCTOS PARA EL LISTADO
$productos = $pdo->query("SELECT id, nombre FROM productos ORDER BY nombre ASC")->fetchAll();

// 2. LÓGICA DE CÁLCULO (SIMULACIÓN)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['calcular'])) {
    $lotes = $_POST['lote']; // Array [id_producto => litros]
    $insumos_necesarios = [];

    foreach ($lotes as $prod_id => $litros) {
        if ($litros > 0) {
            // Buscamos la fórmula de este producto
            $stmt = $pdo->prepare("SELECT f.*, i.nombre as insumo, i.unidad_medida, i.precio_unitario, i.stock_actual 
                                   FROM formulas f 
                                   JOIN insumos i ON f.insumo_id = i.id 
                                   WHERE f.producto_id = ?");
            $stmt->execute([$prod_id]);
            $componentes = $stmt->fetchAll();

            foreach ($componentes as $c) {
                $total_item = $c['cantidad_por_litro'] * $litros;
                $id_insumo = $c['insumo_id'];

                if (!isset($insumos_necesarios[$id_insumo])) {
                    $insumos_necesarios[$id_insumo] = [
                        'nombre' => $c['insumo'],
                        'cantidad_requerida' => 0,
                        'unidad' => $c['unidad_medida'],
                        'stock_bodega' => $c['stock_actual'],
                        'costo_estimado' => 0,
                        'precio_u' => $c['precio_unitario']
                    ];
                }
                $insumos_necesarios[$id_insumo]['cantidad_requerida'] += $total_item;
                $insumos_necesarios[$id_insumo]['costo_estimado'] += ($total_item * $c['precio_unitario']);
            }
        }
    }
    $reporte = $insumos_necesarios;
    $_SESSION['ultimo_reporte_ahd'] = $reporte;
    $_SESSION['ultimo_calculo_lotes'] = $lotes;
}

// 3. LÓGICA DE CONFIRMACIÓN (DESCARGA DE INVENTARIO)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar_fabricacion'])) {
    $lotes_a_fabricar = $_SESSION['ultimo_calculo_lotes'] ?? [];
    
    try {
        $pdo->beginTransaction();

        foreach ($lotes_a_fabricar as $prod_id => $litros) {
            if ($litros <= 0) continue;

            // A. Descontar Insumos
            $stmt = $pdo->prepare("SELECT insumo_id, cantidad_por_litro FROM formulas WHERE producto_id = ?");
            $stmt->execute([$prod_id]);
            $receta = $stmt->fetchAll();

            foreach ($receta as $r) {
                $descuento = $r['cantidad_por_litro'] * $litros;
                $updIns = $pdo->prepare("UPDATE insumos SET stock_actual = stock_actual - ? WHERE id = ?");
                $updIns->execute([$descuento, $r['insumo_id']]);
            }

            // B. Sumar al stock de Producto Terminado (Tabla inventario)
            $updProd = $pdo->prepare("UPDATE inventario SET cantidad = cantidad + ? WHERE producto_id = ?");
            $updProd->execute([$litros, $prod_id]);
        }

        $pdo->commit();
        $mensaje_exito = "¡Producción procesada! Inventarios actualizados.";
        unset($_SESSION['ultimo_reporte_ahd']);
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Producción AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .lote-input-group { display: flex; align-items: center; gap: 10px; background: #fff; padding: 10px; border-bottom: 1px solid #edf2f7; }
        .insuficiente { color: #e53e3e; font-weight: bold; }
        .resumen-card { background: #fff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); padding: 20px; margin-top: 20px; border-left: 5px solid #2b6cb0; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <div class="header">
            <h1><i class="fas fa-industry"></i> Lotes de Fabricación</h1>
        </div>

        <?php if($mensaje_exito): ?>
            <div style="background:#c6f6d5; color:#22543d; padding:15px; border-radius:8px; margin-bottom:20px;"><?php echo $mensaje_exito; ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST">
                <h3>Configurar Lotes de Hoy</h3>
                <?php foreach($productos as $p): ?>
                <div class="lote-input-group">
                    <span style="flex:2"><?php echo htmlspecialchars($p['nombre']); ?></span>
                    <input type="number" name="lote[<?php echo $p['id']; ?>]" class="form-control" placeholder="0" min="0" style="width:100px;">
                    <span>Litros</span>
                </div>
                <?php endforeach; ?>
                <button type="submit" name="calcular" class="btn" style="width:100%; margin-top:15px;">
                    <i class="fas fa-calculator"></i> Calcular Necesidades
                </button>
            </form>
        </div>

        <?php if (!empty($reporte)): ?>
        <div class="resumen-card">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3>Consolidado de Materia Prima</h3>
                <div>
                    <a href="exportar_consolidado.php" class="btn-small" style="background:#27ae60; color:white; text-decoration:none;">
                        <i class="fas fa-file-excel"></i> Exportar
                    </a>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Insumo</th>
                        <th>Necesario</th>
                        <th>En Bodega</th>
                        <th>Costo Est.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_inversion = 0;
                    foreach($reporte as $item): 
                        $total_inversion += $item['costo_estimado'];
                        $falta = $item['stock_bodega'] < $item['cantidad_requerida'];
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                        <td><?php echo number_format($item['cantidad_requerida'], 2); ?> <?php echo $item['unidad']; ?></td>
                        <td class="<?php echo $falta ? 'insuficiente' : ''; ?>">
                            <?php echo $item['stock_bodega']; ?> <?php echo $item['unidad']; ?>
                            <?php if($falta) echo " <i class='fas fa-exclamation-triangle'></i>"; ?>
                        </td>
                        <td>$<?php echo number_format($item['costo_estimado'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="text-align:right; margin-top:15px; font-size:1.2rem;">
                <strong>Inversión Total: $<?php echo number_format($total_inversion, 2); ?></strong>
            </div>

            <form method="POST" style="margin-top:20px; border-top:1px solid #eee; padding-top:20px;">
                <button type="submit" name="confirmar_fabricacion" class="btn" style="background:#2b6cb0; width:100%; padding:15px;" 
                        onclick="return confirm('¿Confirmar fabricación? Se descontarán insumos y se sumará producto terminado.')">
                    <i class="fas fa-check-circle"></i> CONFIRMAR Y FINALIZAR PRODUCCIÓN
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>