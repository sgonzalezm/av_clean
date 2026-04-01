<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$mensaje = "";
$alertas_produccion = [];

// 1. PROCESAR VENTA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_venta_mayoreo'])) {
    $id_cliente = $_POST['id_cliente'];
    $productos_pedidos = $_POST['productos']; 

    try {
        $pdo->beginTransaction();

        // Buscamos al cliente y su porcentaje usando tu columna 'tipo_cliente_id'
        $stmt_c = $pdo->prepare("
            SELECT c.nombre_completo, n.descuento_porcentaje 
            FROM clientes c 
            JOIN niveles_descuento n ON c.tipo_cliente_id = n.id 
            WHERE c.id = ?
        ");
        $stmt_c->execute([$id_cliente]);
        $cliente = $stmt_c->fetch();

        foreach ($productos_pedidos as $id_prod => $cantidad) {
            if ($cantidad <= 0) continue;

            $stmt_p = $pdo->prepare("SELECT nombre, stock_actual, precio_unitario, id_formula_maestra FROM productos WHERE id = ?");
            $stmt_p->execute([$id_prod]);
            $p = $stmt_p->fetch();

            if ($cantidad > $p['stock_actual']) {
                $faltante = $cantidad - $p['stock_actual'];
                
                // Explosión de insumos
                $stmt_f = $pdo->prepare("
                    SELECT i.id as id_insumo, i.nombre as insumo, (f.cantidad_por_litro * ?) as requerido, i.stock_actual as disponible
                    FROM formulas f
                    JOIN insumos i ON f.insumo_id = i.id
                    WHERE f.id_formula_maestra = ?
                ");
                $stmt_f->execute([$faltante, $p['id_formula_maestra']]);
                $insumos_necesarios = $stmt_f->fetchAll();

                // Guardamos en alertas para mostrar en pantalla
                $alertas_produccion[] = [
                    'producto' => $p['nombre'],
                    'faltante' => $faltante,
                    'insumos' => $insumos_necesarios
                ];

                // OPCIONAL: Registrar automáticamente lo que falta comprar
                $stmt_compra = $pdo->prepare("INSERT INTO insumos_pendientes (id_insumo, cantidad_requerida, motivo) VALUES (?, ?, ?)");
                foreach($insumos_necesarios as $ins) {
                    if($ins['disponible'] < $ins['requerido']) {
                        $diff = $ins['requerido'] - $ins['disponible'];
                        $stmt_compra->execute([$ins['id_insumo'], $diff, "Pedido faltante Orden Mayoreo"]);
                    }
                }
            } else {
                // Si hay suficiente, descontamos de una vez
                $upd = $pdo->prepare("UPDATE productos SET stock_actual = stock_actual - ? WHERE id = ?");
                $upd->execute([$cantidad, $id_prod]);
            }
        }

        $pdo->commit();
        if(empty($alertas_produccion)) $mensaje = "<div class='alert exito'>Venta completada. Stock actualizado.</div>";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $mensaje = "<div class='alert error'>Error: " . $e->getMessage() . "</div>";
    }
}

// 2. CONSULTAS PARA LA VISTA
// Ajustado a tus columnas: 'nombre_completo' y 'tipo_cliente_id'
$clientes = $pdo->query("
    SELECT c.id, c.nombre_completo, n.nombre as nivel_nombre, n.descuento_porcentaje 
    FROM clientes c 
    JOIN niveles_descuento n ON c.tipo_cliente_id = n.id 
    WHERE c.estatus = 'Activo'
    ORDER BY c.nombre_completo ASC
")->fetchAll();

$productos = $pdo->query("SELECT * FROM productos ORDER BY nombre ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ventas Mayoreo | AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .main { padding: 20px; }
        .grid-mayoreo { display: grid; grid-template-columns: 1fr 380px; gap: 20px; }
        .card { background: white; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0; }
        .alerta-insumos { background: #fff5f5; border: 1px solid #feb2b2; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .critico { color: #c53030; font-weight: bold; }
        .precio-tag { color: #2f855a; font-weight: bold; background: #f0fff4; padding: 3px 6px; border-radius: 4px; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <h1><i class="fas fa-shipping-fast"></i> Ventas Mayoreo</h1>
        <?php echo $mensaje; ?>

        <?php if (!empty($alertas_produccion)): ?>
        <div class="alerta-insumos">
            <h3 style="color:#c53030;"><i class="fas fa-exclamation-triangle"></i> Faltante de Stock Detectado</h3>
            <?php foreach($alertas_produccion as $a): ?>
                <div style="margin-bottom:15px; border-bottom:1px solid #feb2b2; padding-bottom:10px;">
                    <strong><?php echo $a['producto']; ?>: Faltan <?php echo number_format($a['faltante'], 2); ?> Lts</strong>
                    <ul style="font-size:0.85rem; margin-top:5px;">
                        <?php foreach($a['insumos'] as $i): 
                            $falta = ($i['disponible'] < $i['requerido']); ?>
                            <li class="<?php echo $falta ? 'critico' : ''; ?>">
                                <?php echo $i['insumo']; ?>: Req. <?php echo number_format($i['requerido'], 3); ?> 
                                (Disp: <?php echo number_format($i['disponible'], 3); ?>)
                                <?php if($falta) echo " - ⚠️ COMPRAR"; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
            <p style="font-size:0.8rem; color:#718096;">* Se han enviado las necesidades de compra al módulo de Insumos Pendientes.</p>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="grid-mayoreo">
                <div class="card">
                    <h3>Productos</h3>
                    <table style="width:100%;">
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th>Stock</th>
                                <th>Precio Neto</th>
                                <th>Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($productos as $p): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['nombre']); ?></td>
                                <td><?php echo number_format($p['stock_actual'], 1); ?></td>
                                <td><span class="precio-tag" id="p_<?php echo $p['id']; ?>">$---</span></td>
                                <td>
                                    <input type="number" name="productos[<?php echo $p['id']; ?>]" 
                                           class="inp-cant" data-base="<?php echo $p['precio_unitario']; ?>" data-id="<?php echo $p['id']; ?>"
                                           step="0.1" min="0" style="width:70px; padding:5px;">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card" style="height:fit-content; position:sticky; top:20px;">
                    <h3>Datos de Venta</h3>
                    <label>Cliente:</label>
                    <select name="id_cliente" id="cSel" required onchange="calc()" style="width:100%; padding:10px; margin-top:10px;">
                        <option value="" data-desc="0">-- Seleccionar Cliente --</option>
                        <?php foreach($clientes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" data-desc="<?php echo $c['descuento_porcentaje']; ?>">
                                <?php echo htmlspecialchars($c['nombre_completo']); ?> (<?php echo $c['nivel_nombre']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div style="margin-top:20px; background:#f8fafc; padding:15px; border-radius:8px;">
                        <p>Descuento: <span id="lblDesc">0%</span></p>
                        <h2 id="lblTotal">$0.00</h2>
                    </div>

                    <button type="submit" name="registrar_venta_mayoreo" class="btn" style="width:100%; background:#4c51bf; margin-top:15px; padding:15px;">
                        PROCESAR VENTA
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        function calc() {
            const sel = document.getElementById('cSel');
            const desc = parseFloat(sel.options[sel.selectedIndex].getAttribute('data-desc')) || 0;
            document.getElementById('lblDesc').innerText = desc + '%';

            let total = 0;
            document.querySelectorAll('.inp-cant').forEach(i => {
                const base = parseFloat(i.getAttribute('data-base'));
                const cant = parseFloat(i.value) || 0;
                const id = i.getAttribute('data-id');

                const precioFinal = base * (1 - (desc / 100));
                document.getElementById('p_' + id).innerText = '$' + precioFinal.toFixed(2);
                total += (precioFinal * cant);
            });
            document.getElementById('lblTotal').innerText = '$' + total.toLocaleString('es-MX', {minimumFractionDigits: 2});
        }

        document.querySelectorAll('.inp-cant').forEach(i => i.addEventListener('input', calc));
    </script>
</body>
</html>