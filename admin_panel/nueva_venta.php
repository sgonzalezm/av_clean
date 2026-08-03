<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$pedido_finalizado = false;
$nuevo_id = 0;
$error = null;

// --- 1. PROCESAMIENTO DEL PEDIDO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cliente_id'])) {
    try {
        $pdo->beginTransaction();

        $usuario_id = $_SESSION['admin_id']; 
        $cliente_id = $_POST['cliente_id'];
        $descuento_manual = floatval($_POST['descuento_manual'] ?? 0);
        $total_antes_descuento = 0;
        $requiere_produccion_global = false;

        $stmt_c = $pdo->prepare("SELECT c.nombre_completo, tc.descuento_porcentaje 
                                 FROM clientes c 
                                 INNER JOIN tipos_cliente tc ON c.tipo_cliente_id = tc.id 
                                 WHERE c.id = ?");
        $stmt_c->execute([$cliente_id]);
        $info_cliente = $stmt_c->fetch();

        if (!$info_cliente) throw new Exception("Cliente no encontrado.");
        
        $porcentaje_total_desc = ($info_cliente['descuento_porcentaje'] + $descuento_manual) / 100;

        $status_pago = 'Pendiente';
        $fecha_vencimiento = null;
        $monto_pagado_inicial = 0;

        $stmt = $pdo->prepare("INSERT INTO pedidos (usuario_id, cliente_id, nombre, total, status_pago, status_logistica, fecha_vencimiento_pago, fecha_pedido) VALUES (?, ?, ?, 0, ?, 'Por Surtir', ?, NOW())");
        $stmt->execute([$usuario_id, $cliente_id, $info_cliente['nombre_completo'], $status_pago, $fecha_vencimiento]);
        $pedido_id = $pdo->lastInsertId();

        $hay_productos = false;
        
        if(isset($_POST['productos'])) {
            foreach ($_POST['productos'] as $item) {
                $cant_vta = floatval($item['cantidad']);
                if ($cant_vta > 0) {
                    $hay_productos = true;
                    $p_id = $item['id'];
                    $st = $pdo->prepare("SELECT p.nombre, p.precio, f.stock_litros_disponibles as stock 
                                         FROM productos p 
                                         LEFT JOIN formulas_maestras f ON p.id_formula_maestra = f.id 
                                         WHERE p.id = ?");
                    $st->execute([$p_id]);
                    $prod = $st->fetch();

                    if ($prod) {
                        if ($prod['stock'] < $cant_vta) $requiere_produccion_global = true;
                        $total_antes_descuento += $prod['precio'] * $cant_vta;
                        $ins = $pdo->prepare("INSERT INTO detalle_pedido (pedido_id, producto_id, cantidad, producto_nombre, precio_unitario) VALUES (?, ?, ?, ?, ?)");
                        $ins->execute([$pedido_id, $p_id, $cant_vta, $prod['nombre'], $prod['precio']]);
                    }
                }
            }
        }

        if ($total_antes_descuento > 0 && $hay_productos) {
            $total_final = $total_antes_descuento * (1 - $porcentaje_total_desc);
            $obs = $requiere_produccion_global ? "⚠️ Requiere fabricar líquido." : "✅ Listo para surtir.";
            $pdo->prepare("UPDATE pedidos SET total = ?, monto_pagado = ?, observaciones = ? WHERE id = ?")->execute([$total_final, $monto_pagado_inicial, $obs, $pedido_id]);
            $pdo->commit();
            $pedido_finalizado = true;
            $nuevo_id = $pedido_id;
        } else {
            throw new Exception("Agrega productos.");
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// --- 2. CONSULTAS ---
$productos = $pdo->query("SELECT p.id, p.nombre, p.precio, f.stock_litros_disponibles as stock FROM productos p LEFT JOIN formulas_maestras f ON p.id_formula_maestra = f.id ORDER BY p.nombre ASC")->fetchAll();
$clientes = $pdo->query("SELECT c.id, c.nombre_completo, tc.descuento_porcentaje FROM clientes c INNER JOIN tipos_cliente tc ON c.tipo_cliente_id = tc.id WHERE c.estatus = 'Activo' ORDER BY c.nombre_completo ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>POS | AHD Clean</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        :root { 
            --accent: #10b981; 
            --dark: #1e293b; 
            --bg: #f8fafc;
            --radius: 12px;
            --header-height: 56px;
            --sidebar-width: 260px;
            --panel-max-height: 45vh;
        }
        
        * {
            -webkit-tap-highlight-color: transparent;
            box-sizing: border-box;
        }
        
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
        }
        
        body { 
            background: var(--bg); 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            height: 100vh;
            height: -webkit-fill-available;
            overflow: hidden;
        }
        
        /* ===== SIDEBAR ===== */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100%;
            background: #0f172a;
            color: white;
            z-index: 3000;
            overflow-y: auto;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            transform: translateX(0);
        }
        
        .sidebar .logo {
            padding: 20px;
            font-size: 1.2rem;
            font-weight: 700;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sidebar .logo i {
            color: var(--accent);
        }
        
        .sidebar .menu-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #94a3b8;
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        
        .sidebar .menu-item:hover,
        .sidebar .menu-item.active {
            background: rgba(255,255,255,0.05);
            color: white;
            border-left-color: var(--accent);
        }
        
        .sidebar .menu-item i {
            width: 20px;
            text-align: center;
        }
        
        /* ===== HEADER MÓVIL ===== */
        .header-mobile { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            right: 0; 
            height: var(--header-height); 
            background: var(--dark); 
            color: white; 
            align-items: center; 
            justify-content: space-between; 
            padding: 0 16px; 
            z-index: 2000; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            flex-shrink: 0;
        }
        
        .header-mobile .menu-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.3rem;
            padding: 8px;
            cursor: pointer;
        }
        
        .header-mobile .title {
            font-weight: 700;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
        }
        
        /* ===== OVERLAY ===== */
        .overlay { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.5); 
            z-index: 2500; 
        }
        .overlay.active { display: block; }
        
        /* ===== MAIN ===== */
        .main { 
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            margin-left: var(--sidebar-width);
            height: 100vh;
            height: -webkit-fill-available;
        }
        
        .pos-container { 
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
            padding: 8px 12px 0 12px;
        }
        
        /* ===== CATALOG PANEL ===== */
        .catalog-panel {
            flex: 1;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 8px;
            margin-bottom: 4px;
            min-height: 0;
        }
        
        .catalog-panel::-webkit-scrollbar {
            width: 3px;
        }
        
        .catalog-panel::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .catalog-panel::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        
        /* ===== SEARCH ===== */
        .search-pos { 
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--bg);
            padding: 4px 0 10px 0;
            margin-bottom: 6px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .search-pos i { 
            position: absolute; 
            left: 14px; 
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8; 
            font-size: 0.85rem;
            z-index: 1;
        }
        
        .search-pos input { 
            width: 100%; 
            padding: 10px 12px 10px 38px; 
            border-radius: 10px; 
            border: 1px solid #e2e8f0; 
            box-sizing: border-box;
            font-size: 0.95rem;
            background: white;
            transition: all 0.2s;
            height: 44px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        }
        
        .search-pos input:focus {
            border-color: var(--accent);
            outline: none;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        /* ===== PRODUCT GRID ===== */
        .product-grid {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding-bottom: 4px;
        }
        
        .product-row { 
            display: flex; 
            align-items: center; 
            background: white; 
            padding: 10px 12px; 
            border-radius: var(--radius); 
            border: 1px solid #e2e8f0; 
            transition: all 0.15s;
            min-height: 52px;
            gap: 6px;
        }
        
        .product-row:active {
            transform: scale(0.98);
            background: #f1f5f9;
        }
        
        .product-row .product-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 1px;
        }
        
        .product-row .product-name {
            font-size: 0.82rem;
            font-weight: 600;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.2;
        }
        
        .product-row .product-stock {
            font-size: 0.6rem;
            color: #64748b;
        }
        
        .product-row .product-price {
            font-weight: 700;
            color: var(--accent);
            font-size: 0.8rem;
            white-space: nowrap;
            min-width: 60px;
            text-align: right;
        }
        
        .product-row .qty-wrapper {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-shrink: 0;
        }
        
        .qty-input-pos { 
            width: 48px; 
            height: 34px; 
            text-align: center; 
            border: 2px solid #e2e8f0; 
            border-radius: 8px; 
            font-weight: 700;
            font-size: 0.9rem;
            background: white;
            transition: border-color 0.2s;
            padding: 0;
            -moz-appearance: textfield;
        }
        
        .qty-input-pos::-webkit-outer-spin-button,
        .qty-input-pos::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        
        .qty-input-pos:focus {
            border-color: var(--accent);
            outline: none;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.15);
        }
        
        /* ===== TICKET PANEL ===== */
        .ticket-panel { 
            background: #1e293b; 
            color: white;
            border-radius: 16px 16px 0 0;
            padding: 10px 12px 12px 12px;
            flex-shrink: 0;
            max-height: var(--panel-max-height);
            min-height: 150px;
            display: flex;
            flex-direction: column;
            transition: max-height 0.3s ease;
            position: relative;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        
        .ticket-panel .panel-handle {
            width: 36px;
            height: 4px;
            background: rgba(255,255,255,0.25);
            border-radius: 4px;
            margin: 0 auto 6px auto;
            cursor: pointer;
            flex-shrink: 0;
            display: none;
        }
        
        .ticket-panel .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
            flex-shrink: 0;
        }
        
        .ticket-panel .ticket-header h3 {
            margin: 0;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .ticket-panel .item-count {
            background: rgba(255,255,255,0.1);
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 500;
        }
        
        /* ===== CONFIG GRID ===== */
        .mobile-config-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 6px; 
            margin-bottom: 4px;
            flex-shrink: 0;
        }
        
        .mobile-config-grid label {
            font-size: 0.5rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            display: block;
        }
        
        .form-control-pos { 
            width: 100%; 
            height: 32px; 
            border-radius: 8px; 
            margin-top: 2px; 
            border: 1px solid rgba(255,255,255,0.12); 
            padding: 0 8px; 
            font-size: 0.75rem;
            background: rgba(255,255,255,0.06);
            color: white;
            transition: all 0.2s;
            -webkit-appearance: none;
            appearance: none;
        }
        
        .form-control-pos:focus {
            border-color: var(--accent);
            outline: none;
            background: rgba(255,255,255,0.1);
        }
        
        .form-control-pos option {
            background: #1e293b;
            color: white;
        }
        
        .discount-badge { 
            background: #0f172a; 
            border: 1px solid rgba(79, 209, 197, 0.3); 
            display: flex; 
            align-items: center; 
            border-radius: 8px; 
            padding: 2px 6px; 
            margin-top: 2px;
            height: 32px;
        }
        
        .input-descuento-manual { 
            background: transparent; 
            border: none; 
            color: #4fd1c5; 
            font-weight: 700; 
            text-align: center; 
            width: 32px; 
            outline: none; 
            font-size: 0.8rem;
            padding: 0;
        }
        
        .discount-badge span {
            color: #4fd1c5;
            font-weight: 700;
            font-size: 0.75rem;
        }
        
        /* ===== RESUMEN CARRITO ===== */
        .resumen-carrito-wrapper {
            flex: 1;
            min-height: 30px;
            max-height: 55px;
            overflow: hidden;
            margin-bottom: 3px;
            position: relative;
        }
        
        .resumen-carrito-wrapper::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 20px;
            background: linear-gradient(to top, #1e293b, transparent);
            pointer-events: none;
        }
        
        .resumen-carrito { 
            max-height: 55px;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding-right: 4px;
        }
        
        .resumen-carrito::-webkit-scrollbar {
            width: 3px;
        }
        
        .resumen-carrito::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.05);
            border-radius: 3px;
        }
        
        .resumen-carrito::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 3px;
        }
        
        .item-lista { 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            padding: 3px 4px; 
            border-bottom: 1px solid rgba(255,255,255,0.05); 
            font-size: 0.7rem; 
            color: #e2e8f0;
            gap: 4px;
        }
        
        .item-lista .item-left {
            display: flex;
            align-items: center;
            gap: 4px;
            flex: 1;
            min-width: 0;
        }
        
        .item-lista .item-quantity {
            background: rgba(255,255,255,0.08);
            padding: 0px 6px;
            border-radius: 10px;
            font-size: 0.6rem;
            font-weight: 600;
            color: #94a3b8;
            flex-shrink: 0;
        }
        
        .item-lista .item-name {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 500;
        }
        
        .item-lista .item-total {
            font-weight: 600;
            color: var(--accent);
            font-size: 0.65rem;
            flex-shrink: 0;
        }
        
        .item-lista .btn-eliminar {
            background: none;
            border: none;
            color: #ef4444;
            cursor: pointer;
            font-size: 0.7rem;
            padding: 2px 4px;
            border-radius: 4px;
            transition: all 0.2s;
            flex-shrink: 0;
            opacity: 0.6;
        }
        
        .item-lista .btn-eliminar:hover {
            opacity: 1;
            background: rgba(239, 68, 68, 0.1);
        }
        
        .item-lista .btn-eliminar:active {
            transform: scale(0.8);
        }
        
        .empty-cart-msg {
            padding: 6px;
            color: #94a3b8;
            text-align: center;
            font-size: 0.7rem;
        }
        
        /* ===== DESGLOSE ===== */
        .desglose-ticket { 
            font-size: 0.65rem; 
            margin-top: 3px; 
            border-top: 1px solid rgba(255,255,255,0.08); 
            padding-top: 4px;
            flex-shrink: 0;
        }
        
        .desglose-item { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 1px; 
            opacity: 0.8;
            font-size: 0.65rem;
        }
        
        .desglose-item.total-row {
            font-size: 0.9rem;
            font-weight: 700;
            margin-top: 2px;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 4px;
            opacity: 1;
        }
        
        .desglose-item.total-row span:last-child {
            color: var(--accent);
        }
        
        /* ===== BOTÓN FINALIZAR ===== */
        .btn-cobrar { 
            background: var(--accent); 
            color: white; 
            border: none; 
            width: 100%; 
            height: 40px; 
            border-radius: 10px; 
            font-weight: 700; 
            margin-top: 4px; 
            cursor: pointer; 
            font-size: 0.85rem;
            transition: all 0.2s;
            flex-shrink: 0;
            letter-spacing: 0.5px;
        }
        
        .btn-cobrar:active {
            transform: scale(0.97);
        }
        
        .btn-cobrar:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        /* ============================================= */
        /* ===== RESPONSIVE - MÓVIL ===== */
        /* ============================================= */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .header-mobile { 
                display: flex; 
            }
            
            .main { 
                margin-left: 0;
                padding-top: var(--header-height);
                height: 100vh;
                height: -webkit-fill-available;
            }
            
            .pos-container {
                padding: 0 8px 0 8px;
                height: 100%;
            }
            
            .catalog-panel {
                padding-bottom: 2px;
                flex: 1;
                min-height: 0;
            }
            
            .search-pos {
                padding: 4px 0 8px 0;
            }
            
            .search-pos input {
                height: 38px;
                font-size: 0.85rem;
                padding: 6px 10px 6px 34px;
            }
            
            .search-pos i {
                left: 10px;
                font-size: 0.75rem;
            }
            
            .product-row {
                padding: 6px 8px;
                min-height: 40px;
                gap: 4px;
            }
            
            .product-row .product-name {
                font-size: 0.72rem;
            }
            
            .product-row .product-stock {
                font-size: 0.5rem;
            }
            
            .product-row .product-price {
                font-size: 0.7rem;
                min-width: 45px;
            }
            
            .qty-input-pos {
                width: 38px;
                height: 28px;
                font-size: 0.75rem;
            }
            
            .ticket-panel {
                max-height: 40vh;
                min-height: 130px;
                padding: 8px 10px 10px 10px;
                border-radius: 16px 16px 0 0;
            }
            
            .ticket-panel .panel-handle {
                display: block;
                margin-bottom: 4px;
            }
            
            .ticket-panel .ticket-header h3 {
                font-size: 0.75rem;
            }
            
            .mobile-config-grid {
                gap: 4px;
            }
            
            .form-control-pos {
                height: 28px;
                font-size: 0.7rem;
                padding: 0 6px;
            }
            
            .discount-badge {
                height: 28px;
            }
            
            .input-descuento-manual {
                font-size: 0.7rem;
                width: 28px;
            }
            
            .resumen-carrito {
                max-height: 40px;
            }
            
            .resumen-carrito-wrapper {
                max-height: 40px;
            }
            
            .item-lista {
                padding: 2px 3px;
                font-size: 0.65rem;
            }
            
            .item-lista .btn-eliminar {
                font-size: 0.6rem;
                padding: 1px 3px;
            }
            
            .desglose-ticket {
                font-size: 0.6rem;
                padding-top: 3px;
                margin-top: 2px;
            }
            
            .desglose-item.total-row {
                font-size: 0.8rem;
                padding-top: 3px;
                margin-top: 1px;
            }
            
            .btn-cobrar {
                height: 36px;
                font-size: 0.8rem;
                margin-top: 3px;
            }
        }
        
        @media (max-width: 480px) {
            .ticket-panel {
                max-height: 38vh;
                min-height: 110px;
                padding: 6px 8px 8px 8px;
            }
            
            .mobile-config-grid {
                grid-template-columns: 1fr 1fr;
                gap: 3px;
            }
            
            .form-control-pos {
                height: 26px;
                font-size: 0.65rem;
                padding: 0 4px;
            }
            
            .discount-badge {
                height: 26px;
            }
            
            .resumen-carrito {
                max-height: 30px;
            }
            
            .resumen-carrito-wrapper {
                max-height: 30px;
            }
            
            .item-lista {
                padding: 1px 2px;
                font-size: 0.6rem;
            }
            
            .desglose-item {
                font-size: 0.55rem;
            }
            
            .desglose-item.total-row {
                font-size: 0.7rem;
            }
            
            .btn-cobrar {
                height: 32px;
                font-size: 0.7rem;
            }
        }
        
        /* ===== Ajuste específico para pantallas muy pequeñas ===== */
        @media (max-height: 700px) and (max-width: 480px) {
            .ticket-panel {
                max-height: 35vh;
                min-height: 100px;
            }
            
            .resumen-carrito {
                max-height: 25px;
            }
            
            .resumen-carrito-wrapper {
                max-height: 25px;
            }
            
            .btn-cobrar {
                height: 28px;
                font-size: 0.65rem;
            }
            
            .mobile-config-grid {
                gap: 2px;
            }
            
            .form-control-pos {
                height: 22px;
                font-size: 0.6rem;
            }
            
            .discount-badge {
                height: 22px;
            }
        }
    </style>
</head>
<body>
    <div class="overlay" id="overlay" onclick="toggleMenu()"></div>

    <!-- Header Móvil -->
    <div class="header-mobile">
        <button class="menu-btn" onclick="toggleMenu()"><i class="fas fa-bars"></i></button>
        <span class="title">AHD CLEAN POS</span>
        <span><i class="fas fa-cash-register"></i></span>
    </div>

    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- ===== FORMULARIO PRINCIPAL ===== -->
    <form method="POST" id="formVenta" class="main">
        <div class="pos-container">
            <div class="catalog-panel" id="catalogPanel">
                <!-- Buscador sticky dentro del scroll -->
                <div class="search-pos">
                    <i class="fas fa-search"></i>
                    <input type="text" id="buscador" placeholder="Buscar producto..." autocomplete="off">
                </div>
                <div class="product-grid" id="productGrid">
                    <?php foreach ($productos as $i => $p): ?>
                    <div class="product-row" data-nombre="<?php echo strtolower($p['nombre']); ?>" data-id="<?php echo $p['id']; ?>">
                        <div class="product-info">
                            <span class="product-name"><?php echo htmlspecialchars($p['nombre']); ?></span>
                            <span class="product-stock">Stock: <?php echo number_format($p['stock'], 3); ?>L</span>
                        </div>
                        <div style="display:flex; align-items:center; gap:4px; flex-shrink:0;">
                            <span class="product-price">$<?php echo number_format($p['precio'], 0); ?></span>
                            <input type="hidden" name="productos[<?php echo $i; ?>][id]" value="<?php echo $p['id']; ?>">
                            <input type="hidden" class="precio-unitario" value="<?php echo $p['precio']; ?>">
                            <div class="qty-wrapper">
                                <input type="number" name="productos[<?php echo $i; ?>][cantidad]" class="qty-input-pos input-cantidad" placeholder="0" step="any" inputmode="decimal" min="0">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="ticket-panel" id="ticketPanel">
                <div class="panel-handle" id="panelHandle"></div>
                
                <div class="ticket-header">
                    <h3><i class="fas fa-shopping-bag" style="font-size:0.7rem;"></i> Resumen</h3>
                    <span class="item-count" id="itemCount">0 productos</span>
                </div>
                
                <div class="mobile-config-grid">
                    <div>
                        <label>Cliente</label>
                        <select name="cliente_id" id="cliente_id" class="form-control-pos">
                            <?php foreach($clientes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" data-descuento="<?php echo $c['descuento_porcentaje']; ?>"><?php echo $c['nombre_completo']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Desc. Manual %</label>
                        <div class="discount-badge">
                            <i class="fas fa-tag" style="font-size:0.6rem; margin-right:3px; color: #4fd1c5;"></i>
                            <input type="number" name="descuento_manual" id="descuento_manual" class="input-descuento-manual" value="0" min="0" max="100">
                            <span>%</span>
                        </div>
                    </div>
                </div>

                <div class="resumen-carrito-wrapper">
                    <div class="resumen-carrito" id="lista-carrito">
                        <div class="empty-cart-msg">No hay productos agregados</div>
                    </div>
                </div>

                <div class="desglose-ticket">
                    <div class="desglose-item"><span>Subtotal:</span> <span id="sub_view">$0.00</span></div>
                    <div class="desglose-item" style="color:#4fd1c5;"><span>Ahorro:</span> <span id="ahorro_view">-$0.00</span></div>
                    <div class="desglose-item total-row">
                        <span>TOTAL</span>
                        <span id="total_view">$0.00</span>
                    </div>
                </div>

                <button type="submit" class="btn-cobrar" id="btnFinalizar">
                    <span id="btnText"><i class="fas fa-check-circle"></i> FINALIZAR VENTA</span>
                    <span id="btnLoading" style="display:none;"><i class="fas fa-spinner fa-spin"></i> Procesando...</span>
                </button>
            </div>
        </div>
    </form>

    <script>
        // ===== FUNCIONES PRINCIPALES =====
        
        function toggleMenu() {
            const sb = document.querySelector('.sidebar');
            const ov = document.getElementById('overlay');
            sb.classList.toggle('active');
            ov.classList.toggle('active');
        }

        // ===== CÁLCULOS =====
        const inputs = document.querySelectorAll('.input-cantidad');
        const selCli = document.getElementById('cliente_id');
        const inDesc = document.getElementById('descuento_manual');

        function calcular() {
            let sub = 0;
            const descCli = parseFloat(selCli.options[selCli.selectedIndex].getAttribute('data-descuento')) || 0;
            const descMan = parseFloat(inDesc.value) || 0;

            document.querySelectorAll('.product-row').forEach(row => {
                const p = parseFloat(row.querySelector('.precio-unitario').value);
                const c = parseFloat(row.querySelector('.input-cantidad').value) || 0;
                sub += p * c;
            });

            const porcTotal = (descCli + descMan) / 100;
            const ahorro = sub * porcTotal;
            const total = sub - ahorro;

            document.getElementById('sub_view').innerText = '$' + sub.toFixed(2);
            document.getElementById('ahorro_view').innerText = '-$' + ahorro.toFixed(2);
            document.getElementById('total_view').innerText = '$' + (total < 0 ? 0 : total).toFixed(2);
            
            actualizarContadorItems();
        }

        function actualizarContadorItems() {
            let total = 0;
            document.querySelectorAll('.product-row').forEach(row => {
                const c = parseFloat(row.querySelector('.input-cantidad').value) || 0;
                if (c > 0) total += c;
            });
            const countEl = document.getElementById('itemCount');
            if (countEl) {
                countEl.textContent = total + ' producto' + (total !== 1 ? 's' : '');
            }
        }

        // ===== BÚSQUEDA =====
        const searchInput = document.getElementById('buscador');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    const t = this.value.toLowerCase().trim();
                    document.querySelectorAll('.product-row').forEach(r => {
                        const name = r.getAttribute('data-nombre') || '';
                        const match = name.includes(t);
                        r.style.display = match ? "flex" : "none";
                    });
                }, 150);
            });
        }

        // ===== ACTUALIZAR RESUMEN CON BOTÓN ELIMINAR =====
        function actualizarResumen() {
            const contenedor = document.getElementById('lista-carrito');
            if (!contenedor) return;
            
            contenedor.innerHTML = '';
            let hayProductos = false;

            document.querySelectorAll('.product-row').forEach(row => {
                const input = row.querySelector('.input-cantidad');
                const cantidad = parseFloat(input?.value) || 0;
                if (cantidad > 0) {
                    hayProductos = true;
                    const nombre = row.querySelector('.product-name')?.textContent || '';
                    const precio = parseFloat(row.querySelector('.precio-unitario')?.value) || 0;
                    const productId = row.getAttribute('data-id') || '';
                    
                    contenedor.innerHTML += `
                        <div class="item-lista" data-id="${productId}">
                            <span class="item-left">
                                <span class="item-quantity">${cantidad}</span>
                                <span class="item-name">${nombre}</span>
                            </span>
                            <span class="item-total">$${(cantidad * precio).toFixed(2)}</span>
                            <button class="btn-eliminar" onclick="eliminarProducto('${productId}')" title="Eliminar producto">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>`;
                }
            });

            if (!hayProductos) {
                contenedor.innerHTML = '<div class="empty-cart-msg">No hay productos agregados</div>';
            }
        }

        // ===== ELIMINAR PRODUCTO =====
        function eliminarProducto(productId) {
            document.querySelectorAll('.product-row').forEach(row => {
                const id = row.getAttribute('data-id');
                if (id === productId) {
                    const input = row.querySelector('.input-cantidad');
                    if (input) {
                        input.value = '';
                        input.dispatchEvent(new Event('input'));
                    }
                }
            });
            
            if (navigator.vibrate) {
                navigator.vibrate(10);
            }
        }

        // ===== GUARDAR Y RECUPERAR CARRITO =====
        function guardarCarrito() {
            const items = [];
            document.querySelectorAll('.product-row').forEach(row => {
                const input = row.querySelector('.input-cantidad');
                const cantidad = parseFloat(input?.value) || 0;
                if (cantidad > 0) {
                    const id = row.querySelector('input[type="hidden"]')?.value;
                    const nombre = row.querySelector('.product-name')?.textContent || '';
                    const precio = parseFloat(row.querySelector('.precio-unitario')?.value) || 0;
                    items.push({ id, nombre, cantidad, precio });
                }
            });
            try {
                localStorage.setItem('carritoAHD', JSON.stringify(items));
            } catch(e) {}
        }

        function recuperarCarrito() {
            try {
                const saved = localStorage.getItem('carritoAHD');
                if (saved) {
                    const items = JSON.parse(saved);
                    items.forEach(item => {
                        document.querySelectorAll('.product-row').forEach(row => {
                            const hidden = row.querySelector('input[type="hidden"]');
                            if (hidden && parseInt(hidden.value) === parseInt(item.id)) {
                                const input = row.querySelector('.input-cantidad');
                                if (input) {
                                    input.value = item.cantidad;
                                }
                            }
                        });
                    });
                    calcular();
                    actualizarResumen();
                }
            } catch(e) {}
        }

        // ===== LIMPIAR CARRITO =====
        function limpiarCarrito() {
            document.querySelectorAll('.input-cantidad').forEach(input => {
                input.value = '';
            });
            try {
                localStorage.removeItem('carritoAHD');
            } catch(e) {}
            calcular();
            actualizarResumen();
        }

        // ===== CONTROL DEL PANEL =====
        let isExpanded = false;

        function togglePanel() {
            const panel = document.getElementById('ticketPanel');
            if (panel) {
                isExpanded = !isExpanded;
                panel.style.maxHeight = isExpanded ? '70vh' : 'var(--panel-max-height)';
            }
        }

        // ===== EVENT LISTENERS =====
        selCli.addEventListener('change', calcular);
        inDesc.addEventListener('input', calcular);

        inputs.forEach(i => {
            i.addEventListener('input', () => {
                calcular();
                actualizarResumen();
                guardarCarrito();
            });
        });

        // ===== PREVENIR DOBLE CLIC =====
        document.getElementById('formVenta')?.addEventListener('submit', function(e) {
            const btn = document.getElementById('btnFinalizar');
            const text = document.getElementById('btnText');
            const loading = document.getElementById('btnLoading');
            
            if (btn) {
                btn.disabled = true;
                text.style.display = 'none';
                loading.style.display = 'inline';
                btn.style.opacity = '0.6';
            }
        });

        // ===== PANEL HANDLE =====
        const handle = document.getElementById('panelHandle');
        if (handle) {
            handle.addEventListener('click', function(e) {
                e.stopPropagation();
                togglePanel();
            });
        }

        // ===== INICIALIZACIÓN =====
        document.addEventListener('DOMContentLoaded', function() {
            <?php if($pedido_finalizado && $nuevo_id > 0): ?>
                limpiarCarrito();
            <?php else: ?>
                recuperarCarrito();
            <?php endif; ?>
            
            const form = document.getElementById('formVenta');
            if (window.innerWidth <= 992) {
                form.style.marginLeft = '0';
            } else {
                form.style.marginLeft = 'var(--sidebar-width)';
            }
        });

        // ===== SWEETALERT PARA ÉXITO =====
        <?php if($pedido_finalizado && $nuevo_id > 0): ?>
            Swal.fire({
                icon: 'success',
                title: '¡Venta registrada!',
                text: 'Pedido #<?php echo $nuevo_id; ?> guardado correctamente',
                confirmButtonText: 'Ver Ticket',
                showCancelButton: true,
                cancelButtonText: 'Nueva Venta'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.open('imprimir_ticket.php?id=<?php echo $nuevo_id; ?>', '_blank');
                }
                window.location.href = 'nueva_venta.php';
            });
        <?php endif; ?>

        <?php if(isset($error) && $error): ?>
            Swal.fire('Error', '<?php echo $error; ?>', 'error');
        <?php endif; ?>

        // ===== RESPONSIVE =====
        window.addEventListener('resize', function() {
            const form = document.getElementById('formVenta');
            if (window.innerWidth <= 992) {
                form.style.marginLeft = '0';
            } else {
                form.style.marginLeft = 'var(--sidebar-width)';
            }
        });

        console.log('🚀 POS AHD Clean - Versión final estable');
    </script>
</body>
</html>