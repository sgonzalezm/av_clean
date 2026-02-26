<?php
session_start();
include '../includes/conexion.php'; // Ajusta la ruta a tu archivo

// Lógica de conexión PDO (como la tienes en tu catálogo)
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
        /* ESTILOS ESPECÍFICOS PARA LA TABLA DEL CARRITO */
        
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
                <table class="tabla-carrito">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Subtotal</th>
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
                                    <div class="producto-info">
                                        <img src="<?php echo $p['imagen_url']; ?>" class="producto-img-mini">
                                        <div>
                                            <strong style="display:block;"><?php echo htmlspecialchars($p['nombre']); ?></strong>
                                            <small style="color:#718096;">$<?php echo number_format($p['precio'], 2); ?> c/u</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span style="background: #edf2f7; padding: 5px 12px; border-radius: 20px; font-weight: 600;">
                                        <?php echo $cantidad; ?>
                                    </span>
                                </td>
                                <td style="font-weight: 600; color: #1a365d;">
                                    $<?php echo number_format($subtotal, 2); ?>
                                </td>
                            </tr>
                        <?php endif; endforeach; ?>
                    </tbody>
                </table>

                <div class="total-contenedor">
                    <span class="total-label">Total estimado:</span>
                    <span class="total-monto">$<?php echo number_format($total_general, 2); ?></span>
                </div>

                <div class="acciones-finales">
                    <a href="index.php" class="btn-continuar"> + Agregar más productos</a>
                    <!--<a href="procesar_pedido.php" class="btn-pagar">Confirmar Pedido →</a>-->
                    <?php
                        // Preparamos el mensaje para WhatsApp
                        $mensaje_ws = "Hola AHD Clean! 👋 Quisiera realizar el siguiente pedido:\n\n";
                        $total_ws = 0;

                        foreach ($_SESSION['carrito'] as $id => $cantidad) {
                            $stmt = $pdo->prepare("SELECT nombre, precio FROM productos WHERE id = :id");
                            $stmt->execute(['id' => $id]);
                            $p = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($p) {
                                $subtotal = $p['precio'] * $cantidad;
                                $total_ws += $subtotal;
                                $mensaje_ws .= "- " . $p['nombre'] . " (x" . $cantidad . "): $" . number_format($subtotal, 2) . "\n";
                            }
                        }

                        $mensaje_ws .= "\n*Total a pagar: $" . number_format($total_ws, 2) . "*";
                        $mensaje_ws .= "\n\n¿Me podrían indicar los pasos para el pago y entrega?";

                        // Codificamos el mensaje para que sea válido en una URL
                        $url_mensaje = urlencode($mensaje_ws);
                        $telefono = "5213335518435"; // Pon tu número aquí (con código de país, sin el +)
                        $enlace_ws = "https://wa.me/" . $telefono . "?text=" . $url_mensaje;
                        ?>

                        <div class="acciones-finales">
                            <a href="<?php echo $enlace_ws; ?>" target="_blank" class="btn-pagar" style="background-color: #25D366; border-color: #25D366;">
                                Enviar Pedido por WhatsApp 📱
                            </a>
                        </div>
                </div>

            <?php else: ?>
                <div class="carrito-vacio">
                    <p style="font-size: 1.2rem; color: #718096;">Tu carrito está vacío actualmente.</p>
                    <a href="index.php" class="btn-pagar" style="display:inline-block; margin-top:20px;">Ir a comprar ahora</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer style="background: #1a365d; color: white; padding: 30px 0; margin-top: 60px; text-align: center;">
        <p>© 2026 AHD Clean - Todos los derechos reservados</p>
    </footer>
</body>
</html>