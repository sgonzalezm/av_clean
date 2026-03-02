<?php
session_start();
include '../includes/conexion.php'; 

// Capturamos el ID de la orden que viene de procesar_pedido.php
$orden_id = $_GET['orden_ok'] ?? null;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Carrito - AHD Clean</title>
    <link rel="stylesheet" href="../css/store.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        .carrito-seccion { max-width: 900px; margin: 40px auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); font-family: 'Inter', sans-serif; }
        .tabla-carrito { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .tabla-carrito th { text-align: left; padding: 15px; border-bottom: 2px solid #edf2f7; color: #4a5568; }
        .tabla-carrito td { padding: 20px 15px; border-bottom: 1px solid #edf2f7; }
        
        .controles-cantidad { display: flex; align-items: center; gap: 10px; }
        .btn-qty { text-decoration: none; background: #edf2f7; color: #1a365d; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 5px; font-weight: bold; }
        .btn-qty:hover { background: #cbd5e0; }
        
        .btn-eliminar { color: #e53e3e; text-decoration: none; font-size: 1.2rem; }
        .producto-img-mini { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; }
        .total-contenedor { margin-top: 30px; text-align: right; border-top: 2px solid #1a365d; padding-top: 20px; }
        .total-monto { font-size: 2rem; font-weight: 700; color: #1a365d; }

        /* --- GUÍAS VISUALES --- */
        .order-success-card {
            background: #f0fff4; border: 2px solid #68d391; padding: 20px;
            border-radius: 12px; text-align: center; margin-bottom: 25px;
            animation: fadeInDown 0.6s ease-out;
        }

        .btn-pdf { background-color: #e53e3e; color: white; padding: 15px 25px; border-radius: 10px; text-decoration: none; font-weight: bold; display: inline-flex; align-items: center; gap: 8px; }
        .btn-pdf:hover { background-color: #c53030; }

        .btn-disabled { background-color: #cbd5e0 !important; cursor: not-allowed; pointer-events: none; opacity: 0.6; }
        
        .pulse-ws { animation: pulse-green 2s infinite; display: inline-flex; align-items: center; gap: 8px; }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translate3d(0, -20px, 0); }
            to { opacity: 1; transform: translate3d(0, 0, 0); }
        }

        @keyframes pulse-green {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.7); }
            70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(37, 211, 102, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(37, 211, 102, 0); }
        }
    </style>
</head>
<body>
    <div class="nav">
        <div class="container">
            <a href="index.php">← Volver al Catálogo</a>
            <span>Mi Carrito</span>
        </div>
    </div>

    <div class="container">
        <div class="carrito-seccion">

            <?php if ($orden_id): ?>
                <div class="order-success-card">
                    <i class="fas fa-check-circle" style="color: #38a169; font-size: 2.5rem;"></i>
                    <h2 style="color: #22543d; margin: 10px 0;">¡Pedido Guardado #<?php echo $orden_id; ?>!</h2>
                    <p style="color: #276749;">Se ha generado tu ficha de pago. Descarga el PDF o finaliza por WhatsApp.</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['carrito']) && !empty($_SESSION['carrito'])): ?>
                
                <table class="tabla-carrito">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_general = 0;
                        foreach ($_SESSION['carrito'] as $id => $cantidad): 
                            $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = :id");
                            $stmt->execute(['id' => $id]);
                            $p = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($p):
                                $subtotal = $p['precio'] * $cantidad;
                                $total_general += $subtotal;
                        ?>
                            <tr>
                                <td>
                                    <div style="display:flex; gap:15px; align-items:center;">
                                        <img src="<?php echo $p['imagen_url']; ?>" class="producto-img-mini">
                                        <div>
                                            <strong style="display:block;"><?php echo htmlspecialchars($p['nombre']); ?></strong>
                                            <small>$<?php echo number_format($p['precio'], 2); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="controles-cantidad">
                                        <?php if (!$orden_id): ?>
                                            <a href="actualizar_carrito.php?accion=cantidad&id=<?php echo $id; ?>&meta=menos" class="btn-qty">-</a>
                                            <strong><?php echo $cantidad; ?></strong>
                                            <a href="actualizar_carrito.php?accion=cantidad&id=<?php echo $id; ?>&meta=mas" class="btn-qty">+</a>
                                        <?php else: ?>
                                            <strong><?php echo $cantidad; ?></strong>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="font-weight: 600;">$<?php echo number_format($subtotal, 2); ?></td>
                                <td>
                                    <?php if (!$orden_id): ?>
                                        <a href="actualizar_carrito.php?accion=eliminar&id=<?php echo $id; ?>" class="btn-eliminar">&times;</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; endforeach; ?>
                    </tbody>
                </table>

                <div class="total-contenedor">
                    <span style="color:#718096">Total estimado:</span><br>
                    <span class="total-monto">$<?php echo number_format($total_general, 2); ?></span>
                </div>

                <div class="acciones-finales" style="margin-top:30px; display:flex; justify-content: space-between; align-items:center; flex-wrap: wrap; gap:15px;">
                    <a href="index.php" style="color:#002bff; text-decoration:none; font-weight:600;">+ Agregar más</a>
                    
                    <div style="display:flex; gap:10px;">
                        
                        <?php if ($orden_id): ?>
                            <a href="generar_pdf.php?id=<?php echo $orden_id; ?>" target="_blank" class="btn-pdf">
                                <i class="fas fa-file-pdf"></i> Imprimir Pago (QR)
                            </a>
                        <?php endif; ?>

                        <a href="procesar_pedido.php" 
                           class="btn-pagar <?php echo $orden_id ? 'btn-disabled' : ''; ?>" 
                           style="background-color: #1a365d; color:white; padding:15px 25px; border-radius:10px; text-decoration:none; font-weight:bold; display: inline-flex; align-items: center; gap: 8px;">
                           <i class="fas fa-save"></i> <?php echo $orden_id ? 'Guardado' : '1. Confirmar'; ?>
                        </a>

                        <?php
                            $folio_ws = $orden_id ? " (Folio: #$orden_id)" : "";
                            $mensaje_ws = "Hola AHD Clean! 👋 Quisiera realizar el siguiente pedido$folio_ws:\n\n";
                            foreach ($_SESSION['carrito'] as $id => $cantidad) {
                                $stmt = $pdo->prepare("SELECT nombre FROM productos WHERE id = :id");
                                $stmt->execute(['id' => $id]);
                                $prod = $stmt->fetch(PDO::FETCH_ASSOC);
                                if($prod) $mensaje_ws .= "- " . $prod['nombre'] . " (x" . $cantidad . ")\n";
                            }
                            $mensaje_ws .= "\n*Total: $" . number_format($total_general, 2) . "*";
                            $url_ws = "https://wa.me/5213335518435?text=" . urlencode($mensaje_ws);
                        ?>
                        <a href="<?php echo $url_ws; ?>" target="_blank" class="btn-pagar <?php echo $orden_id ? 'pulse-ws' : ''; ?>" 
                           style="background-color: #25D366; color:white; padding:15px 25px; border-radius:10px; text-decoration:none; font-weight:bold;">
                            <i class="fab fa-whatsapp"></i> Finalizar Pedido
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <div style="text-align:center; padding:50px;">
                    <p>Carrito vacío.</p>
                    <a href="index.php" style="color:#002bff;">Ir al catálogo</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>