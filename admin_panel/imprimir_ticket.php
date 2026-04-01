<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

if (!isset($_GET['id'])) {
    die("ID de pedido no especificado.");
}

$pedido_id = $_GET['id'];

// 1. Obtener datos de la cabecera del pedido
$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch();

if (!$pedido) die("El pedido no existe.");

// 2. Obtener los productos del pedido
$stmt_det = $pdo->prepare("SELECT * FROM detalle_pedido WHERE pedido_id = ?");
$stmt_det->execute([$pedido_id]);
$detalles = $stmt_det->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket #<?php echo $pedido_id; ?></title>
    <style>
        * { font-size: 12px; font-family: 'monospace'; }
        body { width: 80mm; margin: 0; padding: 10px; }
        .centrado { text-align: center; }
        .empresa { font-size: 16px; font-weight: bold; margin-bottom: 5px; }
        .separador { border-top: 1px dashed #000; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; }
        .text-right { text-align: right; }
        .total { font-size: 14px; font-weight: bold; }
        
        /* Botones para pantalla, se ocultan al imprimir */
        @media print {
            .no-print { display: none; }
        }
        .btn {
            padding: 10px; background: #10b981; color: white; 
            text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 2px;
        }
    </style>
</head>
<body>

    <div class="no-print">
        <a href="venta_nueva.php" class="btn" style="background:#64748b;">Nueva Venta</a>
        <button onclick="window.print();" class="btn">Imprimir Manualmente</button>
    </div>

    <div class="centrado">
        <div class="empresa">AHD CLEAN</div>
        <p>Expertos en Limpieza<br>
        ventas@ahd-clean.com<br>
        Fecha: <?php echo date("d/m/Y H:i", strtotime($pedido['fecha_pedido'])); ?></p>
    </div>

    <div class="separador"></div>

    <p>Ticket: #<?php echo $pedido_id; ?><br>
    Cliente: <?php echo htmlspecialchars($pedido['nombre']); ?><br>
    Vendedor: <?php echo $_SESSION['admin_nombre']; ?></p>

    <div class="separador"></div>

    <table>
        <thead>
            <tr>
                <th align="left">Cant.</th>
                <th align="left">Producto</th>
                <th align="right">Subt.</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($detalles as $d): ?>
            <tr>
                <td><?php echo $d['cantidad']; ?></td>
                <td><?php echo htmlspecialchars($d['producto_nombre']); ?></td>
                <td class="text-right">$<?php echo number_format($d['cantidad'] * $d['precio_unitario'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="separador"></div>

    <table class="total">
        <tr>
            <td class="text-right">TOTAL:</td>
            <td class="text-right">$<?php echo number_format($pedido['total'], 2); ?></td>
        </tr>
    </table>

    <div class="separador" style="margin-top:20px;"></div>
    <p class="centrado">¡Gracias por su preferencia!</p>

    <script>
        // Al cargar la página, se abre el diálogo de impresión automáticamente
        window.onload = function() {
            window.print();
        };
    </script>

</body>
</html>