<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();


// --- 1. LÓGICA DE ACTUALIZACIÓN DE ABONOS ---
if (isset($_POST['registrar_abono'])) {
    $id_pedido = $_POST['id_pedido'];
    $monto_abono = (float)$_POST['monto_abono'];
    $metodo = $_POST['metodo_pago'];

    try {
        $pdo->beginTransaction();

        // Actualizamos el monto pagado y el estatus si se liquida
        $stmt = $pdo->prepare("
            UPDATE pedidos 
            SET monto_pagado = monto_pagado + ?,
                status_pago = IF(monto_pagado + ? >= total, 'Pagado', 'Pendiente')
            WHERE id = ?
        ");
        $stmt->execute([$monto_abono, $monto_abono, $id_pedido]);

        $pdo->commit();
        header("Location: cuentas_cobrar.php?msj=Abono registrado correctamente");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header("Location: cuentas_cobrar.php?error=" . urlencode($e->getMessage()));
        exit;
    }
}

// --- 2. CONSULTA DE CARTERA (Pedidos con saldo pendiente) ---
$sql = "SELECT 
            p.id as pedido_id, p.fecha_pedido, p.total, p.monto_pagado, p.status_pago,
            c.nombre_completo as cliente_nombre, c.id as cliente_id, c.dias_credito,
            (p.total - p.monto_pagado) as saldo_pendiente,
            DATE_ADD(p.fecha_pedido, INTERVAL c.dias_credito DAY) as fecha_vencimiento
        FROM pedidos p
        INNER JOIN clientes c ON p.cliente_id = c.id
        WHERE (p.total - p.monto_pagado) > 0.01 
        AND p.status_pago != 'Pagado'
        ORDER BY fecha_vencimiento ASC";

$cartera = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Cobranza | AHD Clean</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        :root { --vencido: #ef4444; --hoy: #f59e0b; --corriente: #3b82f6; --accent: #10b981; --dark: #1e293b; }
        body { background: #f8fafc; margin: 0; font-family: sans-serif; }

        .header-mobile { display: none; position: fixed; top: 0; left: 0; right: 0; height: 60px; background: var(--dark); color: white; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 2000; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .main { padding: 20px; transition: 0.3s; }

        @media (max-width: 992px) {
            .header-mobile { display: flex; }
            .main { margin-left: 0 !important; padding: 75px 15px 120px 15px !important; }
            .sidebar { position: fixed; left: -260px; z-index: 3000; }
            .sidebar.active { left: 0; }
        }

        /* Card Style */
        .cuenta-item { background: #fff; border-radius: 15px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-left: 6px solid #cbd5e1; overflow: hidden; }
        .vencido { border-left-color: var(--vencido); }
        .vence-hoy { border-left-color: var(--hoy); }
        .en-tiempo { border-left-color: var(--corriente); }

        .item-header { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: flex-start; }
        .item-body { padding: 15px 20px; }

        .data-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 0.85rem; }
        .data-mini strong { display: block; color: #94a3b8; font-size: 0.65rem; text-transform: uppercase; }

        .saldo-total { font-size: 1.2rem; font-weight: 800; color: var(--vencido); }
        .btn-abono { background: var(--dark); color: white; padding: 10px; border-radius: 10px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 15px; width: 100%; border: none; font-weight: bold; }

        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2500; }
        .overlay.active { display: block; }
        
        /* Modal simple para abonos */
        .modal-custom { display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; z-index: 4000; padding: 25px; border-radius: 20px; width: 90%; max-width: 400px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .modal-custom.active { display: block; }
    </style>
</head>
<body>
    <div class="overlay" id="overlay" onclick="toggleMenu()"></div>

    <div class="header-mobile">
        <button onclick="toggleMenu()" style="background:none; border:none; color:white; font-size:1.5rem;"><i class="fas fa-bars"></i></button>
        <span style="font-weight: 900;">AHD COBRANZA</span>
        <i class="fas fa-money-bill-wave"></i>
    </div>

    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header hide-mobile" style="margin-bottom:25px;">
            <h1><i class="fas fa-wallet"></i> Cuentas por Cobrar</h1>
            <p>Seguimiento de saldos y vencimientos de clientes.</p>
        </div>

        <?php if(isset($_GET['msj'])): ?>
            <div style="background:#dcfce7; color:#15803d; padding:15px; border-radius:12px; margin-bottom:20px; border:1px solid #bbf7d0;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['msj']); ?>
            </div>
        <?php endif; ?>

        <?php 
        $hoy = date('Y-m-d');
        foreach ($cartera as $c): 
            $status_class = "en-tiempo";
            if ($c['fecha_vencimiento'] < $hoy) $status_class = "vencido";
            elseif ($c['fecha_vencimiento'] == $hoy) $status_class = "vence-hoy";
        ?>
            <div class="cuenta-item <?php echo $status_class; ?>">
                <div class="item-header">
                    <div>
                        <small style="color:#64748b; font-weight:bold;">PEDIDO #<?php echo $c['pedido_id']; ?></small>
                        <h4 style="margin:0; color:var(--dark);"><?php echo htmlspecialchars($c['cliente_nombre']); ?></h4>
                    </div>
                    <span style="font-size: 0.7rem; font-weight: 900; color: <?php echo ($status_class == 'vencido') ? 'var(--vencido)' : '#64748b'; ?>">
                        VENCE: <?php echo date('d/m/y', strtotime($c['fecha_vencimiento'])); ?>
                    </span>
                </div>
                <div class="item-body">
                    <div class="data-grid">
                        <div class="data-mini"><strong>Total Pedido</strong>$<?php echo number_format($c['total'], 2); ?></div>
                        <div class="data-mini"><strong>Días Crédito</strong><?php echo $c['dias_credito']; ?> días</div>
                        <div class="data-mini"><strong>Abonado</strong>$<?php echo number_format($c['monto_pagado'], 2); ?></div>
                        <div class="data-mini">
                            <strong>Saldo Pendiente</strong>
                            <span class="saldo-total">$<?php echo number_format($c['saldo_pendiente'], 2); ?></span>
                        </div>
                    </div>
                    
                    <button class="btn-abono" onclick="abrirAbono(<?php echo $c['pedido_id']; ?>, '<?php echo $c['cliente_nombre']; ?>', <?php echo $c['saldo_pendiente']; ?>)">
                        <i class="fas fa-plus-circle"></i> REGISTRAR ABONO
                    </button>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if(empty($cartera)): ?>
            <div style="text-align:center; padding:100px 20px; color:#94a3b8;">
                <i class="fas fa-hand-holding-usd" style="font-size:3rem; margin-bottom:15px;"></i>
                <h2>Cartera limpia</h2>
                <p>No hay saldos pendientes por cobrar.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal-custom" id="modalAbono">
        <h3 style="margin-top:0;">Registrar Abono</h3>
        <p id="txtCliente" style="font-weight: bold; color: var(--corriente); mb-2"></p>
        <form action="" method="POST">
            <input type="hidden" name="id_pedido" id="inputPedido">
            <div style="margin-bottom: 15px;">
                <label style="font-size: 0.7rem; font-weight: bold; color: #64748b;">MONTO DEL ABONO</label>
                <input type="number" name="monto_abono" id="inputMonto" step="0.01" class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0;" required>
            </div>
            <div style="margin-bottom: 20px;">
                <label style="font-size: 0.7rem; font-weight: bold; color: #64748b;">MÉTODO</label>
                <select name="metodo_pago" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <option value="Efectivo">Efectivo</option>
                    <option value="Transferencia">Transferencia</option>
                    <option value="Tarjeta">Tarjeta</option>
                </select>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" onclick="cerrarModal()" style="flex: 1; padding: 10px; border-radius: 10px; border: 1px solid #cbd5e1; background: #f8fafc;">Cancelar</button>
                <button type="submit" name="registrar_abono" style="flex: 1; padding: 10px; border-radius: 10px; border: none; background: var(--accent); color: white; font-weight: bold;">Guardar</button>
            </div>
        </form>
    </div>

    <script>
        function toggleMenu() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('active');
        }

        function abrirAbono(id, cliente, saldo) {
            document.getElementById('inputPedido').value = id;
            document.getElementById('txtCliente').innerText = cliente;
            document.getElementById('inputMonto').max = saldo;
            document.getElementById('inputMonto').value = saldo;
            document.getElementById('modalAbono').classList.add('active');
            document.getElementById('overlay').classList.add('active');
        }

        function cerrarModal() {
            document.getElementById('modalAbono').classList.remove('active');
            if(!document.querySelector('.sidebar.active')) {
                document.getElementById('overlay').classList.remove('active');
            }
        }
    </script>
</body>
</html>