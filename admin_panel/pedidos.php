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
        
        // 🔒 BLOQUEO: Impedir cambios a "Surtido" o "Entregado"
        $estados_bloqueados = ['Surtido', 'Entregado'];
        if (in_array($nuevo_estado, $estados_bloqueados)) {
            header("Location: pedidos.php?estado=" . ($_GET['estado'] ?? 'Por Surtir') . "&msg=error_bloqueado");
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE pedidos SET status_logistica = ? WHERE id = ?");
        $stmt->execute([$nuevo_estado, $id]);
    }
    
    if(isset($_POST['nuevo_estado_pago'])) {
        $nuevo_pago = $_POST['nuevo_estado_pago'];
        $stmt = $pdo->prepare("UPDATE pedidos SET status_pago = ? WHERE id = ?");
        $stmt->execute([$nuevo_pago, $id]);
    }

    $msg = isset($_GET['msg']) ? $_GET['msg'] : 'actualizado';
    header("Location: pedidos.php?estado=" . ($_GET['estado'] ?? 'Por Surtir') . "&msg=" . $msg);
    exit;
}

// NUEVO: Lógica para Editar Pedido
if(isset($_POST['editar_pedido'])) {
    $id = intval($_POST['pedido_id_editar']);
    $cliente_id = intval($_POST['cliente_id']);
    $telefono = trim($_POST['telefono']);
    $total = floatval($_POST['total']);
    $usuario_id = $_SESSION['admin_id'] ?? 0;

    try {
        $pdo->beginTransaction();

        // Actualizar cliente
        $stmt_cliente = $pdo->prepare("UPDATE clientes SET telefono = ? WHERE id = ?");
        $stmt_cliente->execute([$telefono, $cliente_id]);

        // Actualizar pedido
        $stmt_pedido = $pdo->prepare("UPDATE pedidos SET total = ? WHERE id = ?");
        $stmt_pedido->execute([$total, $id]);

        // Registrar en log
        $stmt_log = $pdo->prepare("INSERT INTO logs_auditoria (usuario_id, accion, tabla_afectada, registro_id, motivo) VALUES (?, 'UPDATE', 'pedidos', ?, 'Edición de pedido')");
        $stmt_log->execute([$usuario_id, $id]);

        $pdo->commit();
        header("Location: pedidos.php?estado=" . ($_GET['estado'] ?? 'Por Surtir') . "&msg=editado");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error al editar el pedido: " . $e->getMessage();
    }
}

// NUEVO: Lógica para Eliminar Pedido con Audit Log
if (isset($_POST['eliminar_pedido'])) {
    $id_pedido = intval($_POST['pedido_id_eliminar']);
    $motivo = trim($_POST['motivo_eliminacion'] ?? 'No especificado');
    $usuario_id = $_SESSION['admin_id'] ?? 0;

    try {
        $pdo->beginTransaction();

        $stmt_log = $pdo->prepare("INSERT INTO logs_auditoria (usuario_id, accion, tabla_afectada, registro_id, motivo) VALUES (?, 'DELETE', 'pedidos', ?, ?)");
        $stmt_log->execute([$usuario_id, $id_pedido, $motivo]);

        $stmt_detalles = $pdo->prepare("DELETE FROM detalle_pedido WHERE pedido_id = ?");
        $stmt_detalles->execute([$id_pedido]);

        $stmt_pedido = $pdo->prepare("DELETE FROM pedidos WHERE id = ?");
        $stmt_pedido->execute([$id_pedido]);

        $pdo->commit();
        header("Location: pedidos.php?estado=" . ($_GET['estado'] ?? 'Por Surtir') . "&msg=eliminado");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error al eliminar el pedido: " . $e->getMessage();
    }
}

// 2. Filtros mejorados
$filtro = $_GET['estado'] ?? 'todos';
$filtro_pago = $_GET['pago'] ?? 'todos';

// 3. Consulta de pedidos con filtros mejorados
$sql = "SELECT p.*, c.nombre_completo as cliente_nombre, c.telefono 
        FROM pedidos p 
        LEFT JOIN clientes c ON p.cliente_id = c.id 
        WHERE 1=1";

$params = [];

if ($filtro !== 'todos') {
    $sql .= " AND p.status_logistica = ?";
    $params[] = $filtro;
}

if ($filtro_pago !== 'todos') {
    $sql .= " AND p.status_pago = ?";
    $params[] = $filtro_pago;
}

$sql .= " ORDER BY p.fecha_pedido DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener clientes para el modal de edición
$clientes = $pdo->query("SELECT id, nombre_completo FROM clientes ORDER BY nombre_completo")->fetchAll(PDO::FETCH_ASSOC);
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
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; overflow-x: auto; flex-wrap: wrap; }
        .tab { padding: 10px 20px; text-decoration: none; color: #64748b; border-radius: 8px; font-weight: 600; white-space: nowrap; transition: 0.3s; }
        .tab.active { background: #1e293b; color: white; }
        .tab-todos.active { background: #475569; color: white; }
        .tab-Por-Surtir.active { background: #f59e0b; color: white; }
        .tab-Surtido.active { background: #3b82f6; color: white; }
        .tab-Entregado.active { background: #10b981; color: white; }

        .tabla-pedidos { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .tabla-pedidos th { background: #f8fafc; padding: 15px; text-align: left; color: #475569; font-size: 0.85rem; text-transform: uppercase; font-weight: 700; }
        .tabla-pedidos td { padding: 15px; border-top: 1px solid #f1f5f9; vertical-align: middle; }
        .tabla-pedidos tr:hover { background: #f8fafc; }
        
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .badge-pago-Pagado { background: #dcfce7; color: #15803d; }
        .badge-pago-Pendiente { background: #fef3c7; color: #92400e; }
        .badge-pago-Crédito { background: #e0f2fe; color: #0369a1; }
        
        .badge-logistica-Por-Surtir { background: #fef3c7; color: #92400e; }
        .badge-logistica-Surtido { background: #dbeafe; color: #1e40af; }
        .badge-logistica-Entregado { background: #dcfce7; color: #15803d; }
        
        .select-status { padding: 6px 10px; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 0.8rem; background: #fff; cursor: pointer; }
        .btn-accion { color: #64748b; font-size: 1.1rem; transition: 0.2s; padding: 5px; }
        .btn-accion:hover { transform: scale(1.2); }
        .btn-accion-pdf { color: #ef4444; }
        .btn-accion-editar { color: #3b82f6; }
        .btn-accion-eliminar { color: #ef4444; background: none; border: none; cursor: pointer; padding: 5px; font-size: 1.1rem; }

        .filtros-extra { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
        .filtros-extra select { padding: 8px 15px; border-radius: 6px; border: 1px solid #e2e8f0; background: white; }
        .filtros-extra label { font-weight: 600; color: #475569; }

        /* Modal de Edición */
        .modal-edit { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 3000; justify-content: center; align-items: center; }
        .modal-content-edit { background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .modal-content-edit h3 { margin-top: 0; color: #1e293b; display: flex; align-items: center; gap: 10px; }
        .modal-content-edit .form-group { margin-bottom: 15px; }
        .modal-content-edit label { display: block; font-weight: 600; color: #475569; margin-bottom: 5px; font-size: 0.9rem; }
        .modal-content-edit input, .modal-content-edit select { width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.95rem; box-sizing: border-box; }
        .modal-content-edit .btn-guardar { background: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .modal-content-edit .btn-cancelar { background: #94a3b8; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .modal-content-edit .botones { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }

        /* Modal de Eliminación */
        .modal-audit { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 3000; justify-content: center; align-items: center; }
        .modal-content-audit { background: white; padding: 25px; border-radius: 12px; width: 90%; max-width: 420px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .text-area-audit { width: 100%; height: 80px; margin: 12px 0; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px; font-family: sans-serif; resize: none; box-sizing: border-box; }
        .btn-cancelar { background: #94a3b8; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn-confirmar-eliminar { background: #ef4444; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-weight: 600; }

        .alert-bloqueo { background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 15px; font-weight: 600; display: flex; align-items: center; gap: 10px; border-left: 4px solid #ef4444; }
        .alert-success { background: #dcfce7; color: #15803d; padding: 12px 16px; border-radius: 8px; margin-bottom: 15px; font-weight: 600; display: flex; align-items: center; gap: 10px; border-left: 4px solid #10b981; }
        .alert-warning { background: #fef3c7; color: #92400e; padding: 12px 16px; border-radius: 8px; margin-bottom: 15px; font-weight: 600; display: flex; align-items: center; gap: 10px; border-left: 4px solid #f59e0b; }
        
        .stats { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
        .stat-card { background: white; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); flex: 1; min-width: 150px; }
        .stat-card .numero { font-size: 1.8rem; font-weight: 800; color: #1e293b; }
        .stat-card .label { font-size: 0.8rem; color: #64748b; text-transform: uppercase; }
        .stat-card.total { border-left: 4px solid #475569; }
        .stat-card.por-surtir { border-left: 4px solid #f59e0b; }
        .stat-card.surtido { border-left: 4px solid #3b82f6; }
        .stat-card.entregado { border-left: 4px solid #10b981; }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <div class="header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; flex-wrap:wrap; gap:15px;">
            <div>
                <h1>Gestión de Pedidos</h1>
                <p style="color: #64748b;">Monitorea el flujo de pagos y entregas.</p>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="exportar_excel_completo.php" class="btn-export" style="background:#10b981; color:white; padding:10px 15px; border-radius:8px; text-decoration:none; font-size:0.9rem; display:inline-flex; align-items:center; gap:8px;">
                    <i class="fas fa-file-excel"></i> Exportar Excel
                </a>
                <a href="nueva_venta.php" class="btn-export" style="background:#3b82f6; color:white; padding:10px 15px; border-radius:8px; text-decoration:none; font-size:0.9rem; display:inline-flex; align-items:center; gap:8px;">
                    <i class="fas fa-plus"></i> Nueva Venta
                </a>
            </div>
        </div>

        <?php if(isset($error)): ?>
            <div style="background:#fee2e2; color:#991b1b; padding:12px; border-radius:8px; margin-bottom:15px; font-weight:600;"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if(isset($_GET['msg'])): ?>
            <?php if($_GET['msg'] == 'error_bloqueado'): ?>
                <div class="alert-bloqueo">
                    <i class="fas fa-lock"></i>
                    No está permitido cambiar el estado a <strong>"Surtido"</strong> o <strong>"Entregado"</strong> desde esta sección. Use el módulo de <strong>Ordenes de Trabajo</strong> para realizar esos cambios.
                </div>
            <?php elseif($_GET['msg'] == 'eliminado'): ?>
                <div class="alert-success">
                    <i class="fas fa-check-circle"></i> Pedido eliminado correctamente.
                </div>
            <?php elseif($_GET['msg'] == 'editado'): ?>
                <div class="alert-success">
                    <i class="fas fa-check-circle"></i> Pedido actualizado correctamente.
                </div>
            <?php elseif($_GET['msg'] == 'actualizado'): ?>
                <div class="alert-success">
                    <i class="fas fa-check-circle"></i> Estado actualizado correctamente.
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Estadísticas rápidas -->
        <?php
        $stats = $pdo->query("SELECT status_logistica, COUNT(*) as total FROM pedidos GROUP BY status_logistica")->fetchAll(PDO::FETCH_ASSOC);
        $stats_array = ['Por Surtir' => 0, 'Surtido' => 0, 'Entregado' => 0];
        foreach($stats as $s) {
            $stats_array[$s['status_logistica']] = $s['total'];
        }
        $total_pedidos = array_sum($stats_array);
        ?>
        <div class="stats">
            <div class="stat-card total">
                <div class="numero"><?php echo $total_pedidos; ?></div>
                <div class="label">Total Pedidos</div>
            </div>
            <div class="stat-card por-surtir">
                <div class="numero"><?php echo $stats_array['Por Surtir']; ?></div>
                <div class="label">Por Surtir</div>
            </div>
            <div class="stat-card surtido">
                <div class="numero"><?php echo $stats_array['Surtido']; ?></div>
                <div class="label">Surtido</div>
            </div>
            <div class="stat-card entregado">
                <div class="numero"><?php echo $stats_array['Entregado']; ?></div>
                <div class="label">Entregado</div>
            </div>
        </div>

        <!-- Filtros mejorados -->
        <div class="filtros-extra">
            <label>Estado Logística:</label>
            <select onchange="window.location.href='?estado='+this.value+'&pago=<?php echo $filtro_pago; ?>'">
                <option value="todos" <?php echo $filtro == 'todos' ? 'selected' : ''; ?>>Todos</option>
                <option value="Por Surtir" <?php echo $filtro == 'Por Surtir' ? 'selected' : ''; ?>>Por Surtir</option>
                <option value="Surtido" <?php echo $filtro == 'Surtido' ? 'selected' : ''; ?>>Surtido</option>
                <option value="Entregado" <?php echo $filtro == 'Entregado' ? 'selected' : ''; ?>>Entregado</option>
            </select>

            <label>Estado Pago:</label>
            <select onchange="window.location.href='?estado=<?php echo $filtro; ?>&pago='+this.value">
                <option value="todos" <?php echo $filtro_pago == 'todos' ? 'selected' : ''; ?>>Todos</option>
                <option value="Pagado" <?php echo $filtro_pago == 'Pagado' ? 'selected' : ''; ?>>Pagado</option>
                <option value="Pendiente" <?php echo $filtro_pago == 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                <option value="Crédito" <?php echo $filtro_pago == 'Crédito' ? 'selected' : ''; ?>>Crédito</option>
            </select>

            <span style="color:#94a3b8; font-size:0.9rem;">
                <i class="fas fa-search"></i> Mostrando <?php echo count($pedidos); ?> pedidos
            </span>
        </div>

        <table class="tabla-pedidos">
            <thead>
                <tr>
                    <th>Folio / Fecha</th>
                    <th>Cliente / Contacto</th>
                    <th>Estado Logística</th>
                    <th>Estado Pago</th>
                    <th>Total</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($pedidos) > 0): ?>
                    <?php foreach($pedidos as $p): ?>
                    <tr>
                        <td>
                            <span style="font-weight: 800; color: #1e293b;">#<?php echo $p['id']; ?></span><br>
                            <small style="color: #64748b;"><?php echo date('d/m/y H:i', strtotime($p['fecha_pedido'])); ?></small>
                        </td>
                        <td>
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($p['cliente_nombre']); ?></div>
                            <div style="font-size: 0.75rem; color: #64748b;"><i class="fas fa-phone"></i> <?php echo $p['telefono']; ?></div>
                        </td>
                        <td>
                            <span class="badge badge-logistica-<?php echo str_replace(' ', '-', $p['status_logistica']); ?>">
                                <?php echo $p['status_logistica']; ?>
                            </span>
                            <form method="POST" style="display:inline-block; margin-left:5px;">
                                <input type="hidden" name="pedido_id" value="<?php echo $p['id']; ?>">
                                <input type="hidden" name="actualizar_pedido" value="1">
                                <select name="nuevo_estado_logistica" class="select-status" onchange="this.form.submit()">
                                    <?php 
                                    $estados_log = ['Por Surtir', 'Surtido', 'Entregado'];
                                    $estados_permitidos = array_filter($estados_log, function($el) {
                                        return !in_array($el, ['Surtido', 'Entregado']);
                                    });
                                    ?>
                                    <option value="">Cambiar...</option>
                                    <?php foreach($estados_permitidos as $el): ?>
                                        <option value="<?php echo $el; ?>" <?php echo ($p['status_logistica'] == $el) ? 'selected' : ''; ?>><?php echo $el; ?></option>
                                    <?php endforeach; ?>
                                    <?php if(in_array($p['status_logistica'], ['Surtido', 'Entregado'])): ?>
                                        <option value="<?php echo $p['status_logistica']; ?>" selected disabled style="color:#94a3b8;">🔒 <?php echo $p['status_logistica']; ?></option>
                                    <?php endif; ?>
                                </select>
                            </form>
                        </td>
                        <td>
                            <span class="badge badge-pago-<?php echo $p['status_pago']; ?>">
                                <?php echo $p['status_pago']; ?>
                            </span>
                            <form method="POST" style="display:inline-block; margin-left:5px;">
                                <input type="hidden" name="pedido_id" value="<?php echo $p['id']; ?>">
                                <input type="hidden" name="actualizar_pedido" value="1">
                                <select name="nuevo_estado_pago" class="select-status" onchange="this.form.submit()">
                                    <option value="">Cambiar...</option>
                                    <option value="Pagado" <?php echo $p['status_pago'] == 'Pagado' ? 'selected' : ''; ?>>Pagado</option>
                                    <option value="Pendiente" <?php echo $p['status_pago'] == 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                    <option value="Crédito" <?php echo $p['status_pago'] == 'Crédito' ? 'selected' : ''; ?>>Crédito</option>
                                </select>
                            </form>
                        </td>
                        <td style="font-weight: 800; color: #1e293b;">$<?php echo number_format($p['total'], 2); ?></td>
                        <td>
                            <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                                <a href="imprimir_ticket.php?id=<?php echo $p['id']; ?>" target="_blank" title="Ticket POS" class="btn-accion"><i class="fas fa-receipt"></i></a>
                                <a href="generar_pdf_pedido.php?id=<?php echo $p['id']; ?>" target="_blank" title="PDF Carta" class="btn-accion btn-accion-pdf"><i class="fas fa-file-pdf"></i></a>
                                <a href="facturar.php?id=<?php echo $p['id']; ?>" title="Facturar" class="btn-accion"><i class="fas fa-file-invoice"></i></a>
                                
                                <button type="button" title="Editar Pedido" class="btn-accion btn-accion-editar" onclick="abrirEditar(<?php echo $p['id']; ?>, <?php echo $p['cliente_id']; ?>, '<?php echo addslashes($p['telefono']); ?>', <?php echo $p['total']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <button type="button" title="Eliminar Pedido" class="btn-accion btn-accion-eliminar" onclick="confirmarEliminacion(<?php echo $p['id']; ?>)">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:40px; color:#94a3b8;">
                            <i class="fas fa-inbox" style="font-size:2rem; display:block; margin-bottom:10px;"></i>
                            No hay pedidos con los filtros seleccionados.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal de Edición -->
    <div id="modalEditar" class="modal-edit">
        <div class="modal-content-edit">
            <h3><i class="fas fa-edit" style="color:#3b82f6;"></i> Editar Pedido <span id="text-folio-edit"></span></h3>
            <form method="POST">
                <input type="hidden" name="pedido_id_editar" id="pedido_id_editar">
                <input type="hidden" name="editar_pedido" value="1">
                
                <div class="form-group">
                    <label>Cliente</label>
                    <select name="cliente_id" id="cliente_id_edit" required>
                        <?php foreach($clientes as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nombre_completo']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="telefono" id="telefono_edit" required>
                </div>
                
                <div class="form-group">
                    <label>Total</label>
                    <input type="number" name="total" id="total_edit" step="0.01" min="0" required>
                </div>
                
                <div class="botones">
                    <button type="button" class="btn-cancelar" onclick="cerrarEditar()">Cancelar</button>
                    <button type="submit" class="btn-guardar"><i class="fas fa-save"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Eliminación -->
    <div id="modalEliminar" class="modal-audit">
        <div class="modal-content-audit">
            <h3 style="margin-top:0; color:#1e293b;"><i class="fas fa-exclamation-triangle" style="color:#ef4444;"></i> Eliminar Pedido <span id="text-folio-elim"></span></h3>
            <p style="font-size:0.85rem; color:#64748b; margin-bottom:10px;">Esta acción borrará el pedido y sus desgloses de productos. Escribe el motivo de la cancelación para el log:</p>
            
            <form method="POST">
                <input type="hidden" name="pedido_id_eliminar" id="pedido_id_eliminar">
                <input type="hidden" name="eliminar_pedido" value="1">
                
                <textarea name="motivo_eliminacion" id="motivo_eliminacion" class="text-area-audit" placeholder="Ej. El cliente canceló el pedido / Error en captura..." required></textarea>
                
                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" class="btn-cancelar" onclick="cerrarModalEliminar()">Cancelar</button>
                    <button type="submit" class="btn-confirmar-eliminar"><i class="fas fa-trash"></i> Eliminar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/admin.js"></script>
    <script>
        // Funciones para el modal de Edición
        function abrirEditar(id, cliente_id, telefono, total) {
            document.getElementById('pedido_id_editar').value = id;
            document.getElementById('text-folio-edit').innerText = '#' + id;
            document.getElementById('cliente_id_edit').value = cliente_id;
            document.getElementById('telefono_edit').value = telefono;
            document.getElementById('total_edit').value = total;
            document.getElementById('modalEditar').style.display = 'flex';
        }

        function cerrarEditar() {
            document.getElementById('modalEditar').style.display = 'none';
        }

        // Funciones para el modal de Eliminación
        function confirmarEliminacion(id) {
            document.getElementById('pedido_id_eliminar').value = id;
            document.getElementById('text-folio-elim').innerText = '#' + id;
            document.getElementById('motivo_eliminacion').value = '';
            document.getElementById('modalEliminar').style.display = 'flex';
        }

        function cerrarModalEliminar() {
            document.getElementById('modalEliminar').style.display = 'none';
        }

        // Cerrar modales al dar clic fuera
        window.onclick = function(event) {
            const modalEdit = document.getElementById('modalEditar');
            const modalElim = document.getElementById('modalEliminar');
            if (event.target == modalEdit) {
                cerrarEditar();
            }
            if (event.target == modalElim) {
                cerrarModalEliminar();
            }
        }

        // Auto-cerrar mensajes después de 5 segundos
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-success, .alert-bloqueo, .alert-warning');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>