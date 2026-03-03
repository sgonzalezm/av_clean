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
                                        <a href="actualizar_carrito.php?accion=cantidad&id=<?php echo $id; ?>&meta=menos" class="btn-qty">-</a>
                                        <strong><?php echo $cantidad; ?></strong>
                                        <a href="actualizar_carrito.php?accion=cantidad&id=<?php echo $id; ?>&meta=mas" class="btn-qty">+</a>
                                    </div>
                                </td>
                                <td style="font-weight: 600;">$<?php echo number_format($subtotal, 2); ?></td>
                                <td>
                                    <a href="actualizar_carrito.php?accion=eliminar&id=<?php echo $id; ?>" class="btn-eliminar">&times;</a>
                                </td>
                            </tr>
                        <?php endif; endforeach; ?>
                    </tbody>
                </table>

                <div class="total-contenedor">
                    <span style="color:#718096">Total estimado:</span><br>
                    <span class="total-monto">$<?php echo number_format($total_general, 2); ?></span>
                </div>

            <?php elseif (!$orden_id): ?>
                <div style="text-align:center; padding:50px;">
                    <p>Carrito vacío.</p>
                    <a href="index.php" style="color:#002bff;">Ir al catálogo</a>
                </div>
            <?php endif; ?>

            <div class="acciones-finales" style="margin-top:30px; display:flex; justify-content: space-between; align-items:center; flex-wrap: wrap; gap:15px;">
                
                <a href="index.php" style="color:#002bff; text-decoration:none; font-weight:600;">+ Agregar más productos</a>
                
                <div style="display:flex; gap:10px; align-items:center;">
                    
                    <?php if ($orden_id): ?>
                        <a href="generar_pdf.php?id=<?php echo $orden_id; ?>" target="_blank" class="btn-pdf">
                            <i class="fas fa-file-pdf"></i> Imprimir Pago (QR)
                        </a>

                        <a href="https://wa.me/5213335518435?text=<?php echo urlencode("Hola AHD Clean! 👋 Acabo de generar mi pedido con el Folio: #$orden_id. ¿Me podrían confirmar la recepción?"); ?>" 
                           target="_blank" class="btn-pagar pulse-ws" 
                           style="background-color: #25D366; color:white; padding:15px 25px; border-radius:10px; text-decoration:none; font-weight:bold; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fab fa-whatsapp"></i> Finalizar por WhatsApp
                        </a>

                    <?php elseif (!empty($_SESSION['carrito'])): ?>
                        <button type="button" onclick="abrirModal()" class="btn-pagar" 
                                style="background-color: #1a365d; color:white; padding:15px 25px; border-radius:10px; border:none; cursor:pointer; font-weight:bold; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fas fa-save"></i> 1. Confirmar Pedido
                        </button>
                        
                        <?php
                            $msj_lista = "Hola AHD Clean! 👋 Quisiera cotizar este pedido:\n";
                            foreach ($_SESSION['carrito'] as $id_w => $cant_w) {
                                $st_w = $pdo->prepare("SELECT nombre FROM productos WHERE id = ?");
                                $st_w->execute([$id_w]);
                                $p_w = $st_w->fetch();
                                if($p_w) $msj_lista .= "- " . $p_w['nombre'] . " (x$cant_w)\n";
                            }
                        ?>
                        <a href="https://wa.me/5213335518435?text=<?php echo urlencode($msj_lista); ?>" target="_blank" 
                           style="background-color: #25D366; color:white; padding:15px 25px; border-radius:10px; text-decoration:none; font-weight:bold; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fab fa-whatsapp"></i> Consultar
                        </a>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>

    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal-content { background: white; width: 90%; max-width: 450px; margin: 10% auto; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .campo-modal { margin-bottom: 15px; }
        .campo-modal label { display: block; margin-bottom: 5px; font-weight: 600; color: #4a5568; }
        .campo-modal input, .campo-modal textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 8px; box-sizing: border-box; }
        .btn-enviar-modal { background: #1a365d; color: white; border: none; padding: 12px; width: 100%; border-radius: 8px; cursor: pointer; font-weight: bold; margin-top: 10px; }
    </style>

    <div id="modalEnvio" class="modal">
        <div class="modal-content">
            <h3 style="margin-top:0;">📦 Datos de Entrega</h3>
            <form action="procesar_pedido.php" method="POST">
                <div class="campo-modal">
                    <label>Correo Electrónico</label>
                    <input type="email" name="email" required placeholder="ejemplo@correo.com">
                </div>
                <div class="campo-modal">
                    <label>Teléfono (WhatsApp)</label>
                    <input type="tel" name="telefono" required placeholder="33 1234 5678">
                </div>
                <div class="campo-modal">
                    <label>Domicilio Completo</label>
                    <textarea name="domicilio" required placeholder="Calle, Número, Colonia, CP" rows="3"></textarea>
                </div>
                <button type="submit" class="btn-enviar-modal">Confirmar y Generar Orden</button>
                <button type="button" onclick="cerrarModal()" style="background:none; border:none; color:#e53e3e; cursor:pointer; width:100%; margin-top:10px;">Cancelar</button>
            </form>
        </div>
    </div>

    <script>
        function abrirModal() { document.getElementById('modalEnvio').style.display = 'block'; }
        function cerrarModal() { document.getElementById('modalEnvio').style.display = 'none'; }
        window.onclick = function(event) {
            if (event.target == document.getElementById('modalEnvio')) cerrarModal();
        }
    </script>
</body>
</html>