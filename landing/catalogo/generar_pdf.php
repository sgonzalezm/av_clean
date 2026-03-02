<?php
session_start();
require_once '../includes/conexion.php';

$orden_id = $_GET['id'] ?? null;

if (!$orden_id) {
    die("Error: No se proporcionó un ID de pedido.");
}

try {
    // 1. Obtener datos del pedido
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
    $stmt->execute([$orden_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) die("Error: Pedido no encontrado.");

    // 2. Obtener detalles del pedido
    $stmt = $pdo->prepare("SELECT d.*, p.nombre FROM detalle_pedido d JOIN productos p ON d.producto_id = p.id WHERE d.pedido_id = ?");
    $stmt->execute([$orden_id]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error de base de datos: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Pago #<?php echo $orden_id; ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 0; padding: 40px; }
        .ticket { max-width: 800px; margin: auto; border: 1px solid #eee; padding: 30px; border-radius: 10px; }
        .header { text-align: center; border-bottom: 3px solid #1a365d; padding-bottom: 20px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: #1a365d; letter-spacing: 2px; }
        .info-meta { display: flex; justify-content: space-between; margin-bottom: 30px; font-size: 0.9rem; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th { background: #f8fafc; text-align: left; padding: 12px; border-bottom: 2px solid #1a365d; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        .total-section { text-align: right; font-size: 1.5rem; font-weight: bold; color: #1a365d; }
        
        .codigos-container { 
            margin-top: 50px; 
            padding: 20px; 
            background: #f1f5f9; 
            border-radius: 15px; 
            text-align: center; 
        }
        .qr-img { width: 180px; margin-bottom: 10px; }
        .barcode-img { width: 300px; height: 80px; margin-top: 15px; }
        
        .no-print { 
            background: #1a365d; color: white; padding: 12px 25px; 
            border: none; border-radius: 5px; cursor: pointer; 
            font-weight: bold; margin-bottom: 20px;
        }

        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            .ticket { border: none; }
        }
    </style>
</head>
<body>

    <div style="text-align: center;">
        <button class="no-print" onclick="window.print()">
            <i class="fas fa-print"></i> CLIC AQUÍ PARA IMPRIMIR O GUARDAR PDF
        </button>
    </div>

    <div class="ticket">
        <div class="header">
            <h1>AHD CLEAN</h1>
            <p>Soluciones de Limpieza Profesional</p>
        </div>

        <div class="info-meta">
            <div>
                <strong>ORDEN DE PAGO:</strong> #<?php echo $orden_id; ?><br>
                <strong>FECHA:</strong> <?php echo $pedido['fecha_pedido']; ?>
            </div>
            <div style="text-align: right;">
                <strong>ESTADO:</strong> PENDIENTE<br>
                <strong>CLIENTE:</strong> Público General
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Cant.</th>
                    <th>Precio</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $d): ?>
                <tr>
                    <td><?php echo htmlspecialchars($d['nombre']); ?></td>
                    <td><?php echo $d['cantidad']; ?></td>
                    <td>$<?php echo number_format($d['precio_unitario'], 2); ?></td>
                    <td>$<?php echo number_format($d['cantidad'] * $d['precio_unitario'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="total-section">
            TOTAL A PAGAR: $<?php echo number_format($pedido['total'], 2); ?> MXN
        </div>

        <div class="codigos-container">
            <h3>Ficha de Pago Presencial</h3>
            <p>Presenta estos códigos en caja para identificar tu pedido</p>
            
            <img class="qr-img" src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=AHD-PAY-<?php echo $orden_id; ?>" alt="QR Pago">
            <br>
            <img class="barcode-img" src="https://bwipjs-api.metafloor.com/?bcid=code128&text=AHD<?php echo str_pad($orden_id, 8, "0", STR_PAD_LEFT); ?>&scale=3&rotate=N&includetext" alt="Barras">
            
            <p style="margin-top:20px; font-size: 0.8rem; color: #64748b;">
                Este documento es una orden de compra provisoria.<br>
                Válido por 48 horas a partir de la fecha de emisión.
            </p>
        </div>
    </div>

    <script>
        // Opcional: Abrir el diálogo de impresión automáticamente al cargar
        window.onload = function() {
            // window.print(); 
        };
    </script>
</body>
</html>