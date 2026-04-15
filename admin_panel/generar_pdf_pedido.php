<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$id = $_GET['id'] ?? null;
if (!$id) die("Pedido no encontrado.");

// Consultar datos generales
$stmt = $pdo->prepare("SELECT p.*, c.nombre_completo, c.email, c.telefono, c.direccion 
                       FROM pedidos p 
                       JOIN clientes c ON p.cliente_id = c.id 
                       WHERE p.id = ?");
$stmt->execute([$id]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

// Consultar detalle
$stmt_det = $pdo->prepare("SELECT * FROM detalle_pedido WHERE pedido_id = ?");
$stmt_det->execute([$id]);
$productos = $stmt_det->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>AHD_Pedido_<?php echo $id; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @page { size: letter; margin: 1.5cm; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #1e293b; margin: 0; padding: 0; }
        
        .btn-imprimir { background: #1e293b; color: white; padding: 10px 25px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; margin-bottom: 20px; }
        
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #1e293b; padding-bottom: 20px; }
        .brand h1 { margin: 0; font-size: 32px; letter-spacing: 1px; }
        .brand p { margin: 5px 0; font-size: 14px; color: #64748b; }
        
        .folio-box { text-align: right; }
        .folio-box h2 { margin: 0; color: #ef4444; font-size: 24px; }
        
        .info-seccion { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 30px; }
        .info-card { background: #f8fafc; padding: 15px; border-radius: 10px; border: 1px solid #e2e8f0; }
        .info-card h4 { margin: 0 0 10px 0; font-size: 12px; text-transform: uppercase; color: #94a3b8; border-bottom: 1px solid #cbd5e1; padding-bottom: 5px; }
        .info-card p { margin: 3px 0; font-size: 14px; }

        table { width: 100%; border-collapse: collapse; margin-top: 30px; }
        th { background: #1e293b; color: white; padding: 12px; text-align: left; font-size: 13px; }
        td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        
        .total-container { margin-top: 30px; display: flex; justify-content: flex-end; }
        .total-table { width: 250px; }
        .total-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e2e8f0; }
        .grand-total { font-size: 20px; font-weight: 900; color: #1e293b; border-bottom: none; }

        .footer { margin-top: 60px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 20px; }

        @media print {
            .btn-imprimir { display: none; }
            body { background: white; }
            .info-card { background: #fff !important; }
        }
    </style>
</head>
<body onload="window.print()">

    <div style="text-align: center; padding: 20px;" class="btn-imprimir">
        <button class="btn-imprimir" onclick="window.print()"><i class="fas fa-print"></i> Guardar como PDF / Imprimir</button>
    </div>

    <div class="header">
        <div class="brand">
            <h1>AHD CLEAN</h1>
            <p><strong>Soluciones Químicas de Limpieza</strong><br>
            RFC: AHD123456XYZ<br>
            Guadalajara, Jalisco, México.</p>
        </div>
        <div class="folio-box">
            <h2>NOTA DE VENTA</h2>
            <p><strong>FOLIO: #<?php echo $id; ?></strong><br>
            Fecha: <?php echo date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])); ?></p>
        </div>
    </div>

    <div class="info-seccion">
        <div class="info-card">
            <h4>Datos del Cliente</h4>
            <p><strong><?php echo htmlspecialchars($pedido['nombre_completo']); ?></strong></p>
            <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($pedido['direccion']); ?></p>
            <p><i class="fas fa-phone"></i> <?php echo $pedido['telefono']; ?></p>
            <p><i class="fas fa-envelope"></i> <?php echo $pedido['email']; ?></p>
        </div>
        <div class="info-card">
            <h4>Resumen Financiero</h4>
            <p>Estatus de Pago: <strong><?php echo $pedido['status_pago']; ?></strong></p>
            <p>Estatus Logística: <strong><?php echo $pedido['status_logistica']; ?></strong></p>
            <?php if($pedido['status_pago'] == 'Crédito'): ?>
                <p>Fecha Vencimiento: <strong><?php echo date('d/m/Y', strtotime($pedido['fecha_vencimiento_pago'])); ?></strong></p>
            <?php endif; ?>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>DESCRIPCIÓN DEL PRODUCTO</th>
                <th style="text-align: center;">CANT.</th>
                <th style="text-align: right;">UNITARIO</th>
                <th style="text-align: right;">SUBTOTAL</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($productos as $prod): ?>
            <tr>
                <td><?php echo htmlspecialchars($prod['producto_nombre']); ?></td>
                <td style="text-align: center;"><?php echo $prod['cantidad']; ?></td>
                <td style="text-align: right;">$<?php echo number_format($prod['precio_unitario'], 2); ?></td>
                <td style="text-align: right;">$<?php echo number_format($prod['cantidad'] * $prod['precio_unitario'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="total-container">
        <div class="total-table">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>$<?php echo number_format($pedido['total'], 2); ?></span>
            </div>
            <div class="total-row grand-total">
                <span>TOTAL:</span>
                <span>$<?php echo number_format($pedido['total'], 2); ?></span>
            </div>
        </div>
    </div>

    <?php if(!empty($pedido['observaciones'])): ?>
    <div style="margin-top: 30px; font-size: 13px; color: #475569; border-left: 4px solid #cbd5e1; padding-left: 15px;">
        <strong>Observaciones:</strong><br>
        <?php echo nl2br(htmlspecialchars($pedido['observaciones'])); ?>
    </div>
    <?php endif; ?>

    <div class="footer">
        <p>Este documento no representa un comprobante fiscal (CFDI).<br>
        Gracias por su compra en <strong>AHD CLEAN</strong>. La limpieza que se nota.</p>
    </div>

</body>
</html>