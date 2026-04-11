<?php
session_start();
include '../includes/conexion.php';
include '../includes/session.php';
verificarSesion();

// 1. Lógica para actualizar estados (Pago o Logística)
if(isset($_POST['actualizar_pedido'])) {
    $id = $_POST['pedido_id'];
    
    if(isset($_POST['nuevo_estado_logistica'])) {
        $nuevo_estado = $_POST['nuevo_estado_logistica'];
        $stmt = $pdo->prepare("UPDATE pedidos SET status_logistica = ? WHERE id = ?");
        $stmt->execute([$nuevo_estado, $id]);
    }
    
    if(isset($_POST['nuevo_estado_pago'])) {
        $nuevo_pago = $_POST['nuevo_estado_pago'];
        $stmt = $pdo->prepare("UPDATE pedidos SET status_pago = ? WHERE id = ?");
        $stmt->execute([$nuevo_pago, $id]);
    }

    header("Location: pedidos.php?estado=" . ($_GET['estado'] ?? 'Por Surtir') . "&msg=actualizado");
    exit;
}

// 2. Filtro de Logística por URL (Por Surtir, Surtido, Entregado)
$filtro = $_GET['estado'] ?? 'Por Surtir';

// 3. Consulta de pedidos con JOIN a clientes para obtener el nombre real
$sql = "SELECT p.*, c.nombre_completo as cliente_nombre, c.telefono 
        FROM pedidos p 
        LEFT JOIN clientes c ON p.cliente_id = c.id 
        WHERE p.status_logistica = ? 
        ORDER BY p.fecha_pedido DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$filtro]);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Gestión de Pedidos - AHD Clean</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; overflow-x: auto; }
        .tab { padding: 10px 20px; text-decoration: none; color: #64748b; border-radius: 8px; font-weight: 600; white-space: nowrap; transition: 0.3s; }
        .tab.active { background: #1e293b; color: white; }
        
        /* Colores dinámicos para los estados de logística */
        .tab-Por-Surtir.active { background: #f59e0b; color: white; }
        .tab-Surtido.active { background: #3b82f6; color: white; }
        .tab-Entregado.active { background: #10b981; color: white; }

        .tabla-pedidos { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .tabla-pedidos th { background: #f8fafc; padding: 15px; text-align: left; color: #475569; font-size: 0.85rem; text-transform: uppercase; }
        .tabla-pedidos td { padding: 15px; border-top: 1px solid #f1f5f9; vertical-align: middle; }
        
        .badge { padding: 4px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .badge-pago-Pagado { background: #dcfce7; color: #15803d; }
        .badge-pago-Pendiente { background: #fef3c7; color: #92400e; }
        .badge-pago-Crédito { background: #e0f2fe; color: #0369a1; }
        .badge-pago-Vencido { background: #fee2e2; color: #b91c1c; }

        .select-status { padding: 6px; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 0.8rem; background: #fff; cursor: pointer; }
        .obs-text { font-size: 0.75rem; color: #94a3b8; display: block; margin-top: 4px; font-style: italic; }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <div class="header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
            <div>
                <h1>Gestión de Pedidos</h1>
                <p style="color: #64748b;">Monitorea el flujo de pagos y entregas.</p>
            </div>
            <div style="display:flex; gap:10px;">
                <a href="exportar_csv.php" class="btn-export" style="background:#10b981; color:white; padding:10px 15px; border-radius:8px; text-decoration:none; font-size:0.9rem;"><i class="fas fa-file-excel"></i> CSV</a>
                <a href="nueva_venta.php" class="btn-export" style="background:#3b82f6; color:white; padding:10px 15px; border-radius:8px; text-decoration:none; font-size:0.9rem;"><i class="fas fa-plus"></i> Nueva Venta</a>
            </div>
        </div>

        <div class="tabs">
            <?php 
            $estados_log = ['Por Surtir', 'Surtido', 'Entregado'];
            foreach($estados_log as $e): 
                $slug = str_replace(' ', '-', $e);
                $active = ($filtro == $e) ? 'active tab-'.$slug : '';
            ?>
                <a href="?estado=<?php echo $e; ?>" class="tab <?php echo $active; ?>">
                    <?php echo $e; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <table class="tabla-pedidos">
            <thead>
                <tr>
                    <th>Folio / Fecha</th>
                    <th>Cliente / Contacto</th>
                    <th>Estado Pago</th>
                    <th>Total</th>
                    <th>Acciones Operativas</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($pedidos)): ?>
                    <tr><td colspan="5" style="text-align:center; padding:40px; color:#94a3b8;">No hay pedidos en etapa de <?php echo $filtro; ?>.</td></tr>
                <?php endif; ?>

                <?php foreach($pedidos as $p): ?>
                <tr>
                    <td>
                        <span style="font-weight: 800; color: #1e293b;">#<?php echo $p['id']; ?></span><br>
                        <small style="color: #64748b;"><?php echo date('d/m/Y H:i', strtotime($p['fecha_pedido'])); ?></small>
                    </td>
                    <td>
                        <div style="font-weight: 600; color: #334155;"><?php echo htmlspecialchars($p['cliente_nombre']); ?></div>
                        <div style="font-size: 0.75rem; color: #64748b;"><i class="fas fa-phone"></i> <?php echo $p['telefono']; ?></div>
                        <?php if(!empty($p['observaciones'])): ?>
                            <span class="obs-text"><i class="fas fa-comment-dots"></i> <?php echo htmlspecialchars($p['observaciones']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-pago-<?php echo $p['status_pago']; ?>">
                            <?php echo $p['status_pago']; ?>
                        </span>
                        <?php if($p['status_pago'] == 'Crédito'): ?>
                            <div style="font-size:0.65rem; color:#64748b; margin-top:3px;">Vence: <?php echo date('d/m/Y', strtotime($p['fecha_vencimiento_pago'])); ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="font-weight: 800; color: #1e293b;">$<?php echo number_format($p['total'], 2); ?></td>
                    <td>
                        <div style="display:flex; flex-direction:column; gap:8px;">
                            <form method="POST" style="display:flex; gap:5px;">
                                <input type="hidden" name="pedido_id" value="<?php echo $p['id']; ?>">
                                <input type="hidden" name="actualizar_pedido" value="1">
                                <select name="nuevo_estado_logistica" class="select-status" onchange="this.form.submit()">
                                    <option value="">Logística...</option>
                                    <?php foreach($estados_log as $el): ?>
                                        <option value="<?php echo $el; ?>" <?php echo ($p['status_logistica'] == $el) ? 'selected' : ''; ?>><?php echo $el; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>

                            <form method="POST" style="display:flex; gap:5px;">
                                <input type="hidden" name="pedido_id" value="<?php echo $p['id']; ?>">
                                <input type="hidden" name="actualizar_pedido" value="1">
                                <select name="nuevo_estado_pago" class="select-status" onchange="this.form.submit()" style="border-color: #cbd5e1;">
                                    <option value="">Pago...</option>
                                    <option value="Pagado" <?php echo ($p['status_pago'] == 'Pagado') ? 'selected' : ''; ?>>Pagado</option>
                                    <option value="Pendiente" <?php echo ($p['status_pago'] == 'Pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                                    <option value="Crédito" <?php echo ($p['status_pago'] == 'Crédito') ? 'selected' : ''; ?>>Crédito</option>
                                </select>
                            </form>
                            
                            <div style="display:flex; gap:10px; margin-top:5px;">
                                <a href="imprimir_ticket.php?id=<?php echo $p['id']; ?>" target="_blank" title="Imprimir" style="color:#64748b;"><i class="fas fa-print"></i></a>
                                <a href="facturar.php?id=<?php echo $p['id']; ?>" title="Facturar" style="color:#64748b;"><i class="fas fa-file-invoice"></i></a>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script src="../js/admin.js"></script>
</body>
</html>