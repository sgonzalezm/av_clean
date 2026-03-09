<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// 1. Cargar Configuración Fiscal
$conf = $pdo->query("SELECT * FROM config_fiscal LIMIT 1")->fetch();
$tasa_iva = $conf['iva_porcentaje'] ?? 16; // Por defecto 16%

// 2. Obtener Ingresos (Ventas del mes actual)
$stmt_ingresos = $pdo->query("SELECT SUM(total) as total_ventas FROM pedidos 
                               WHERE MONTH(fecha_pedido) = MONTH(CURRENT_DATE()) 
                               AND status != 'Cancelado'");
$total_ventas = $stmt_ingresos->fetch()['total_ventas'] ?? 0;
/*
// 3. Obtener Egresos (Compras del mes actual)
// Asumiendo que tienes una tabla 'compras' con columna 'total'
$stmt_egresos = $pdo->query("SELECT SUM(total) as total_compras FROM compras 
                              WHERE MONTH(fecha_compra) = MONTH(CURRENT_DATE())");
$total_compras = $stmt_egresos->fetch()['total_compras'] ?? 0;
*/

// 4. Cálculos Fiscales
$iva_cobrado = $total_ventas - ($total_ventas / (1 + ($tasa_iva/100)));
$iva_acreditable = $total_compras - ($total_compras / (1 + ($tasa_iva/100)));
$iva_a_pagar = max(0, $iva_cobrado - $iva_acreditable);
$utilidad_neta = $total_ventas - $total_compras - $iva_a_pagar;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Contabilidad y Reporte Fiscal | AHD Clean</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stats-contable { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .card-mini { background: white; padding: 20px; border-radius: 12px; border-left: 5px solid #cbd5e1; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .card-mini h4 { font-size: 0.8rem; color: #64748b; margin: 0; text-transform: uppercase; }
        .card-mini p { font-size: 1.5rem; font-weight: bold; margin: 10px 0 0; color: #1e293b; }
        
        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; width: 400px; margin: 10% auto; padding: 30px; border-radius: 15px; position: relative; }
        .close-modal { position: absolute; right: 20px; top: 15px; cursor: pointer; font-size: 1.5rem; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header" style="display:flex; justify-content:space-between;">
            <h1><i class="fas fa-balance-scale"></i> Reporte Fiscal Mensual</h1>
            <button class="btn" onclick="openModal()" style="background:#4a5568;">
                <i class="fas fa-cog"></i> Configurar Datos Fiscales
            </button>
        </div>

        <div class="stats-contable">
            <div class="card-mini" style="border-left-color: #48bb78;">
                <h4>Ventas Totales (Ingresos)</h4>
                <p>$<?php echo number_format($total_ventas, 2); ?></p>
            </div>
            <div class="card-mini" style="border-left-color: #f56565;">
                <h4>Compras Totales (Egresos)</h4>
                <p>$<?php echo number_format($total_compras, 2); ?></p>
            </div>
            <div class="card-mini" style="border-left-color: #4299e1;">
                <h4>IVA a Pagar (Estimado)</h4>
                <p>$<?php echo number_format($iva_a_pagar, 2); ?></p>
            </div>
            <div class="card-mini" style="border-left-color: #ecc94b;">
                <h4>Utilidad Real</h4>
                <p>$<?php echo number_format($utilidad_neta, 2); ?></p>
            </div>
        </div>

        <div class="card" style="background:white; padding:25px; border-radius:15px;">
            <h3><i class="fas fa-calculator"></i> Desglose de Impuestos (Tasa <?php echo $tasa_iva; ?>%)</h3>
            <hr style="margin:20px 0; border:0; border-top:1px solid #eee;">
            <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                <span>IVA Trasladado (Cobrado a clientes):</span>
                <strong>$<?php echo number_format($iva_cobrado, 2); ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                <span>IVA Acreditable (Pagado a proveedores):</span>
                <strong style="color: #c53030;">- $<?php echo number_format($iva_acreditable, 2); ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; padding-top:10px; border-top:2px solid #f7fafc; font-size:1.2rem;">
                <span>Diferencia de IVA:</span>
                <mark style="background:#c6f6d5; padding:0 10px;">$<?php echo number_format($iva_a_pagar, 2); ?></mark>
            </div>
        </div>
    </div>

    <div id="modalFiscal" class="modal">
        <div class="modal-content slide-in">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <h3><i class="fas fa-file-invoice"></i> Configuración Fiscal</h3>
            <form action="guardar_config_fiscal.php" method="POST" style="margin-top:20px;">
                <div class="form-group">
                    <label>RFC de la Empresa</label>
                    <input type="text" name="rfc" class="form-control" value="<?php echo $conf['rfc'] ?? ''; ?>" placeholder="ABCD000000XXX" required>
                </div>
                <div class="form-group">
                    <label>Razón Social</label>
                    <input type="text" name="razon_social" class="form-control" value="<?php echo $conf['razon_social'] ?? ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Tasa de IVA (%)</label>
                    <select name="iva_porcentaje" class="form-control">
                        <option value="16" <?php echo $tasa_iva==16?'selected':''; ?>>16% (General)</option>
                        <option value="8" <?php echo $tasa_iva==8?'selected':''; ?>>8% (Fronterizo)</option>
                        <option value="0" <?php echo $tasa_iva==0?'selected':''; ?>>0% (Exento)</option>
                    </select>
                </div>
                <button type="submit" class="btn-guardar" style="width:100%; margin-top:15px;">Actualizar Perfil</button>
            </form>
        </div>
    </div>

    <script>
        function openModal() { document.getElementById('modalFiscal').style.display = 'block'; }
        function closeModal() { document.getElementById('modalFiscal').style.display = 'none'; }
        // Cerrar si hace clic fuera del cuadro
        window.onclick = function(event) {
            if (event.target == document.getElementById('modalFiscal')) closeModal();
        }
    </script>
</body>
</html>