<?php
session_start();
include '../includes/conexion.php'; 

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .carrito-seccion { max-width: 900px; margin: 40px auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .tabla-carrito { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .tabla-carrito th { text-align: left; padding: 15px; border-bottom: 2px solid #edf2f7; color: #4a5568; }
        .tabla-carrito td { padding: 20px 15px; border-bottom: 1px solid #edf2f7; }
        
        /* Botones de cantidad */
        .controles-cantidad { display: flex; align-items: center; gap: 10px; }
        .btn-qty { 
            text-decoration: none; background: #edf2f7; color: #1a365d; 
            width: 28px; height: 28px; display: flex; align-items: center; 
            justify-content: center; border-radius: 5px; font-weight: bold; 
        }
        .btn-qty:hover { background: #cbd5e0; }
        
        .btn-eliminar { color: #e53e3e; text-decoration: none; font-size: 1.2rem; }
        .btn-vaciar { color: #e53e3e; text-decoration: none; font-weight: 600; font-size: 0.9rem; }
        
        .producto-img-mini { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; }
        .total-contenedor { margin-top: 30px; text-align: right; border-top: 2px solid #1a365d; padding-top: 20px; }
        .total-monto { font-size: 2rem; font-weight: 700; color: #1a365d; }
    </style>
</head>
<body>
    <div class="nav">
        <div class="container">
            <a href="index.php">← Volver al Catálogo</a>
            <span>Finalizar mi Pedido</span>
        </div>
    </div>

    <div class="container">
        <div class="carrito-seccion">
            <?php if (isset($_SESSION['carrito']) && !empty($_SESSION['carrito'])): ?>
                
                <div style="text-align: right; margin-bottom: 15px;">
                    <a href="actualizar_carrito.php?accion=vaciar" class="btn-vaciar" onclick="return confirm('¿Vaciar todo el carrito?')">🗑️ Vaciar Carrito</a>
                </div>

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
                                    <div class="producto-info" style="display:flex; gap:15px; align-items:center;">
                                        <img src="<?php echo $p['imagen_url']; ?>" class="producto-img-mini">
                                        <div>
                                            <strong style="display:block;"><?php echo htmlspecialchars($p['nombre']); ?></strong>
                                            <small>$<?php echo number_format($p['precio'], 2); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="controles-cantidad">
                                        <a href="actualizar_carrito.php?accion=cantidad&id=<?php echo $id; ?>&meta=menos" class="btn-qty">-</a>
                                        <strong><?php echo $cantidad; ?></strong>
                                        <a href="actualizar_carrito.php?accion=cantidad&id=<?php echo $id; ?>&meta=mas" class="btn-qty">+</a>
                                    </div>
                                </td>
                                <td style="font-weight: 600;">$<?php echo number_format($subtotal, 2); ?></td>
                                <td>
                                    <a href="actualizar_carrito.php?accion=eliminar&id=<?php echo $id; ?>" class="btn-eliminar" title="Eliminar">&times;</a>
                                </td>
                            </tr>
                        <?php endif; endforeach; ?>
                    </tbody>
                </table>

                <div class="total-contenedor">
                    <span style="color:#718096">Total estimado:</span><br>
                    <span class="total-monto">$<?php echo number_format($total_general, 2); ?></span>
                </div>

                <div class="acciones-finales" style="margin-top:30px; display:flex; justify-content: space-between; align-items:center;">
                    <a href="index.php" style="color:#002bff; text-decoration:none; font-weight:600;">+ Agregar más</a>
                    
                    <?php
                        // Generar mensaje de WhatsApp (Mismo código que ya tenías)
                        $mensaje_ws = "Hola AHD Clean! 👋 Quisiera realizar el siguiente pedido:\n\n";
                        foreach ($_SESSION['carrito'] as $id => $cantidad) {
                            $stmt = $pdo->prepare("SELECT nombre FROM productos WHERE id = :id");
                            $stmt->execute(['id' => $id]);
                            $prod = $stmt->fetch(PDO::FETCH_ASSOC);
                            if($prod) $mensaje_ws .= "- " . $prod['nombre'] . " (x" . $cantidad . ")\n";
                        }
                        $mensaje_ws .= "\n*Total: $" . number_format($total_general, 2) . "*";
                        $url_ws = "https://wa.me/5213335518435?text=" . urlencode($mensaje_ws);
                    ?>
                    
                    <a href="<?php echo $url_ws; ?>" target="_blank" class="btn-pagar" style="background-color: #25D366; color:white; padding:15px 25px; border-radius:10px; text-decoration:none; font-weight:bold;">
                        Enviar Pedido por WhatsApp 📱
                    </a>
                </div>

            <?php else: ?>
                <div style="text-align:center; padding:50px;">
                    <p>Tu carrito está vacío.</p>
                    <a href="index.php" class="btn-pagar" style="display:inline-block; background:#002bff; color:white; padding:10px 20px; border-radius:8px; text-decoration:none;">Ir al catálogo</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>