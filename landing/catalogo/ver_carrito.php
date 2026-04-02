<?php
session_start();
include '../includes/conexion.php'; 

$orden_id = $_GET['orden_ok'] ?? null;
$cliente_logueado = isset($_SESSION['cliente_id']) ? true : false;
$tipo_usuario = $_SESSION['tipo_cliente'] ?? 0;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// --- LÓGICA DE DESCUENTOS ---
$porcentaje_descuento = 0;
if ($cliente_logueado) {
    switch ($tipo_usuario) {
        case 1: $porcentaje_descuento = 0.05; break; 
        case 2: $porcentaje_descuento = 0.10; break; 
        case 3: $porcentaje_descuento = 0.20; break; 
        default: $porcentaje_descuento = 0;
    }
}

$datos_cliente = ['telefono' => '', 'direccion' => '', 'email' => ''];

if ($cliente_logueado) {
    $stmt_c = $pdo->prepare("SELECT email, telefono, direccion FROM clientes WHERE id = ?");
    $stmt_c->execute([$_SESSION['cliente_id']]);
    $res_c = $stmt_c->fetch(PDO::FETCH_ASSOC);
    if ($res_c) {
        $datos_cliente = $res_c;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Carrito | AHD Clean</title>
    <link rel="stylesheet" href="../css/store.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        .carrito-seccion { max-width: 900px; margin: 40px auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); font-family: 'Inter', sans-serif; }
        .tabla-carrito { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .tabla-carrito th { text-align: left; padding: 15px; border-bottom: 2px solid #edf2f7; color: #4a5568; }
        .tabla-carrito td { padding: 20px 15px; border-bottom: 1px solid #edf2f7; vertical-align: middle; }
        .controles-cantidad { display: flex; align-items: center; gap: 12px; }
        .btn-qty { text-decoration: none; background: #edf2f7; color: #1a365d; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 6px; font-weight: bold; }
        .total-monto { font-size: 2.2rem; font-weight: 800; color: #1a365d; }
        .banner-afiliado { background: #ebf8ff; border: 1px dashed #3182ce; padding: 15px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        
        /* Upsell / Presentaciones */
        .upsell-container { display: flex; gap: 8px; align-items: center; margin-top: 8px; flex-wrap: wrap; }
        .upsell-tag { 
            display: flex; align-items: center; gap: 6px; padding: 4px 8px; 
            background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 6px; 
            text-decoration: none; font-size: 0.75rem; color: #4a5568; transition: all 0.2s;
        }
        .upsell-tag:hover { border-color: #3182ce; background: #ebf8ff; transform: translateY(-1px); }
        .upsell-tag img { width: 18px; height: 18px; object-fit: contain; }
        .upsell-tag .precio-v { color: #38a169; font-weight: bold; margin-left: 3px; }

        /* Estilos de Confirmación */
        .order-success-card { background: #f0fff4; border: 2px solid #68d391; padding: 20px; border-radius: 12px; text-align: center; margin-bottom: 25px; animation: fadeInDown 0.6s ease-out; }
        .btn-pdf { background-color: #e53e3e; color: white; padding: 15px 25px; border-radius: 10px; text-decoration: none; font-weight: bold; display: inline-flex; align-items: center; gap: 8px; }
        .btn-whatsapp { background-color: #25D366; color: white; padding: 15px 25px; border-radius: 10px; text-decoration: none; font-weight: bold; display: inline-flex; align-items: center; gap: 8px; }
        
        @keyframes fadeInDown { from { opacity: 0; transform: translate3d(0, -20px, 0); } to { opacity: 1; transform: translate3d(0, 0, 0); } }

        /* Modales */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal-content { background: white; width: 90%; max-width: 450px; margin: 8% auto; padding: 30px; border-radius: 15px; position: relative; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .campo-modal { margin-bottom: 15px; }
        .campo-modal label { display: block; margin-bottom: 5px; font-weight: 600; color: #4a5568; }
        .campo-modal input, .campo-modal textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 8px; box-sizing: border-box; }
        .tab-btn { background: none; border: none; padding: 10px; cursor: pointer; font-weight: bold; color: #718096; font-size: 1rem; }
        .tab-btn.active { color: #3182ce; border-bottom: 2px solid #3182ce; }
        .btn-auth { background: #3182ce; color: white; border: none; padding: 12px; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; }
    </style>
</head>
<body>
    <div class="nav">
        <div class="container" style="display:flex; justify-content:space-between; align-items:center;">
            <a href="index.php" style="color:white; text-decoration:none;"><i class="fas fa-arrow-left"></i> Catálogo</a>
            <span style="font-weight:bold; color:white;">Mi Carrito</span>
        </div>
    </div>

    <div class="container">
        <div class="carrito-seccion">

            <?php if ($orden_id): ?>
                <div class="order-success-card">
                    <i class="fas fa-check-circle" style="color: #38a169; font-size: 2.5rem;"></i>
                    <h2 style="color: #22543d; margin: 10px 0;">¡Pedido Guardado #<?php echo $id_orden ?? $orden_id; ?>!</h2>
                    <p style="color: #276749; margin-bottom: 15px;">Se ha generado tu ficha de pago con éxito.</p>
                    <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
                        <a href="generar_pdf.php?id=<?php echo $orden_id; ?>" target="_blank" class="btn-pdf"><i class="fas fa-file-pdf"></i> Imprimir QR</a>
                        <a href="https://wa.me/5213335518435?text=Folio: #<?php echo $orden_id; ?>" target="_blank" class="btn-whatsapp"><i class="fab fa-whatsapp"></i> WhatsApp</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$orden_id): ?>
            <div class="banner-afiliado">
                <?php if ($cliente_logueado): ?>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color:#38a169;"></i> 
                        <span>Hola, <strong><?php echo explode(' ', $_SESSION['cliente_nombre'])[0]; ?></strong>. Nivel <?php echo $tipo_usuario; ?></span>
                    </div>
                    <a href="logout_cliente.php" style="color: #e53e3e; text-decoration: none; font-size: 0.8rem; font-weight: bold;">Salir</a>
                <?php else: ?>
                    <div><i class="fas fa-user-tag"></i> ¡Inicia sesión para obtener descuentos!</div>
                    <button onclick="abrirModalAuth()" style="background:#3182ce; color:white; border:none; padding:8px 15px; border-radius:8px; cursor:pointer;">Entrar</button>
                <?php endif; ?>
            </div>

            <?php if (isset($_SESSION['carrito']) && !empty($_SESSION['carrito'])): ?>
                <table class="tabla-carrito">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th style="text-align:center;">Cantidad</th>
                            <th>Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_bruto = 0;
                        foreach ($_SESSION['carrito'] as $id => $cantidad): 
                            $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
                            $stmt->execute([$id]);
                            $p = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($p):
                                $subtotal = $p['precio'] * $cantidad;
                                $total_bruto += $subtotal;
                                
                                $stmt_v = $pdo->prepare("SELECT id, precio, imagen_url, volumen_valor, volumen_unidad FROM productos WHERE id_formula_maestra = ? AND id != ? ORDER BY volumen_valor ASC");
                                $stmt_v->execute([$p['id_formula_maestra'], $id]);
                                $presentaciones = $stmt_v->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                            <tr>
                                <td>
                                    <div style="display:flex; gap:15px; align-items:center;">
                                        <img src="<?php echo $p['imagen_url']; ?>" style="width:50px; height:50px; object-fit:contain; border-radius:8px;">
                                        <div>
                                            <strong><?php echo htmlspecialchars($p['nombre']); ?></strong>
                                            <?php if ($presentaciones): ?>
                                            <div class="upsell-container">
                                                <?php foreach ($presentaciones as $pres): ?>
                                                    <a href="actualizar_carrito.php?accion=swap&id_actual=<?php echo $id; ?>&id_nuevo=<?php echo $pres['id']; ?>" class="upsell-tag">
                                                        <span><?php echo number_format($pres['volumen_valor'], 0) . $pres['volumen_unidad']; ?></span>
                                                        <span class="precio-v">$<?php echo number_format($pres['precio'], 2); ?></span>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align:center;">
                                    <div class="controles-cantidad">
                                        <a href="actualizar_carrito.php?accion=cantidad&id=<?php echo $id; ?>&meta=menos" class="btn-qty">-</a>
                                        <strong><?php echo $cantidad; ?></strong>
                                        <a href="actualizar_carrito.php?accion=cantidad&id=<?php echo $id; ?>&meta=mas" class="btn-qty">+</a>
                                    </div>
                                </td>
                                <td style="font-weight: 700;">$<?php echo number_format($subtotal, 2); ?></td>
                                <td style="text-align:right;"><a href="actualizar_carrito.php?accion=eliminar&id=<?php echo $id; ?>" style="color:#e53e3e;"><i class="fas fa-trash-alt"></i></a></td>
                            </tr>
                        <?php endif; endforeach; ?>
                    </tbody>
                </table>

                <div class="total-contenedor" style="text-align: right; margin-top: 20px;">
                    <?php if ($cliente_logueado && $porcentaje_descuento > 0): 
                        $ahorro = $total_bruto * $porcentaje_descuento;
                        $total_final = $total_bruto - $ahorro;
                    ?>
                        <span style="color:#718096">Subtotal: $<?php echo number_format($total_bruto, 2); ?></span><br>
                        <span style="color:#38a169; font-weight:bold;">Descuento: -$<?php echo number_format($ahorro, 2); ?></span><br>
                        <span class="total-monto">$<?php echo number_format($total_final, 2); ?></span>
                    <?php else: ?>
                        <span class="total-monto">$<?php echo number_format($total_bruto, 2); ?></span>
                    <?php endif; ?>
                </div>

                <div style="margin-top:30px; display:flex; justify-content:space-between;">
                    <a href="index.php" style="color:#3182ce; font-weight:bold;">+ Agregar más</a>
                    <button onclick="abrirModalEnvio()" style="background:#1a365d; color:white; border:none; padding:15px 30px; border-radius:12px; cursor:pointer; font-weight:bold;">
                        Confirmar Pedido <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            <?php else: ?>
                <div style="text-align:center; padding:50px;"><p>Tu carrito está vacío.</p><a href="index.php">Ir a la tienda</a></div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div id="modalAuth" class="modal">
        <div class="modal-content">
            <div style="display:flex; border-bottom:1px solid #eee; margin-bottom:20px;">
                <button id="tabLogin" class="tab-btn active" onclick="switchTab('login')">Entrar</button>
                <button id="tabRegistro" class="tab-btn" onclick="switchTab('registro')">Registrarme</button>
            </div>
            <form id="formLogin" action="auth_cliente.php?accion=login" method="POST">
                <div class="campo-modal"><label>Correo</label><input type="email" name="email" required></div>
                <div class="campo-modal"><label>Contraseña</label><input type="password" name="pass" required></div>
                <button type="submit" class="btn-auth">Iniciar Sesión</button>
            </form>
            <form id="formRegistro" action="auth_cliente.php?accion=registro" method="POST" style="display:none;">
                <div class="campo-modal"><label>Nombre</label><input type="text" name="nombre" required></div>
                <div class="campo-modal"><label>Correo</label><input type="email" name="email" required></div>
                <div class="campo-modal"><label>Contraseña</label><input type="password" name="pass" required></div>
                <button type="submit" class="btn-auth" style="background:#38a169;">Crear Cuenta</button>
            </form>
            <button onclick="cerrarModalAuth()" style="background:none; border:none; color:#e53e3e; width:100%; margin-top:15px; cursor:pointer;">Cerrar</button>
        </div>
    </div>

    <div id="modalEnvio" class="modal">
        <div class="modal-content">
            <h3 style="margin-top:0;"><i class="fas fa-shipping-fast"></i> Datos de Entrega</h3>
            <form action="procesar_pedido.php" method="POST">
                <div class="campo-modal">
                    <label>Correo Electrónico</label>
                    <input type="email" name="email" value="<?php echo $datos_cliente['email']; ?>" required>
                </div>
                <div class="campo-modal">
                    <label>Teléfono (WhatsApp)</label>
                    <input type="tel" name="telefono" value="<?php echo $datos_cliente['telefono']; ?>" required>
                </div>
                <div class="campo-modal">
                    <label>Dirección de Entrega</label>
                    <textarea name="domicilio" required rows="3"><?php echo $datos_cliente['direccion']; ?></textarea>
                </div>
                <button type="submit" class="btn-auth" style="background:#1a365d;">Finalizar y Pagar</button>
                <button type="button" onclick="cerrarModalEnvio()" style="background:none; border:none; color:#e53e3e; width:100%; margin-top:10px; cursor:pointer;">Cancelar</button>
            </form>
        </div>
    </div>

    <script>
        function abrirModalEnvio() { document.getElementById('modalEnvio').style.display = 'block'; }
        function cerrarModalEnvio() { document.getElementById('modalEnvio').style.display = 'none'; }
        function abrirModalAuth() { document.getElementById('modalAuth').style.display = 'block'; }
        function cerrarModalAuth() { document.getElementById('modalAuth').style.display = 'none'; }
        
        function switchTab(tab) {
            const isLogin = tab === 'login';
            document.getElementById('formLogin').style.display = isLogin ? 'block' : 'none';
            document.getElementById('formRegistro').style.display = isLogin ? 'none' : 'block';
            document.getElementById('tabLogin').classList.toggle('active', isLogin);
            document.getElementById('tabRegistro').classList.toggle('active', !isLogin);
        }

        window.onclick = function(event) {
            if (event.target.className === 'modal') { event.target.style.display = 'none'; }
        }
    </script>
</body>
</html>