<?php
//=========================================================================
// exportar_excel.php - REPORTE DE VENTAS EVOLUCIONADO PARA EXCEL NATIVO
//=========================================================================
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// 1. CONTROL DE FILTROS ACTIVOS (Mantiene la sincronización de tus pestañas)
$filtro = $_GET['filtro'] ?? 'todos';
$sql = "SELECT p.*, c.nombre_completo as cliente_nombre, c.telefono, u.nombre as vendedor_nombre 
        FROM pedidos p 
        LEFT JOIN clientes c ON p.cliente_id = c.id 
        LEFT JOIN usuarios_admin u ON p.usuario_id = u.id";

if ($filtro === 'por_surtir') {
    $sql .= " WHERE p.estatus = 'Por Surtir'";
} elseif ($filtro === 'completados') {
    $sql .= " WHERE p.estatus = 'Surtido' OR p.estatus = 'Terminado'";
}
$sql .= " ORDER BY p.fecha_pedido DESC";

$stmt = $pdo->query($sql);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. CONFIGURACIÓN DE CABECERAS DE DESCARGA PARA EXCEL NATIVO
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Reporte_Ventas_AHD_" . date('Y-m-data') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// 3. ESTRUCTURA CON ESTILOS VISUALES CORPORATIVOS (Formato Tabla Nativa)
?>
<meta charset="UTF-8">
<style>
    .excel-table {
        border-collapse: collapse;
        font-family: 'Segoe UI', Arial, sans-serif;
        font-size: 11pt;
    }
    .th-header {
        background-color: #1e293b; /* Color Slate oscuro de tu sistema */
        color: #ffffff;
        font-weight: bold;
        text-align: center;
        border: 1px solid #cbd5e1;
    }
    .td-datos {
        border: 1px solid #e2e8f0;
        text-align: left;
    }
    .td-numero {
        border: 1px solid #e2e8f0;
        text-align: right;
        /* Forzamos a Excel a aplicar formato de Moneda a la celda */
        mso-number-format: "\$\#,\#\#0\.00"; 
    }
    .td-fecha {
        border: 1px solid #e2e8f0;
        text-align: center;
        mso-number-format: "yyyy\-mm\-dd";
    }
    .badge-pago {
        color: #0f172a;
        font-weight: bold;
    }
</style>

<table class="excel-table">
    <thead>
        <tr>
            <th class="th-header" style="width: 80px;">Folio</th>
            <th class="th-header" style="width: 120px;">Fecha</th>
            <th class="th-header" style="width: 250px;">Cliente</th>
            <th class="th-header" style="width: 110px;">Teléfono</th>
            <th class="th-header" style="width: 150px;">Vendedor</th>
            <th class="th-header" style="width: 120px;">Método Pago</th>
            <th class="th-header" style="width: 110px;">Estatus Pago</th>
            <th class="th-header" style="width: 110px;">Estatus Surtido</th>
            <th class="th-header" style="width: 120px;">Total Venta</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($pedidos)): ?>
            <tr>
                <td colspan="9" style="text-align: center; border: 1px solid #e2e8f0; color: #64748b;">
                    No hay registros disponibles para mostrar.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($pedidos as $pedido): ?>
                <tr>
                    <td class="td-datos" style="text-align: center; font-weight: bold;">
                        #<?php echo $pedido['id']; ?>
                    </td>
                    <td class="td-fecha">
                        <?php echo date('Y-m-d', strtotime($pedido['fecha_pedido'])); ?>
                    </td>
                    <td class="td-datos">
                        <?php echo htmlspecialchars($pedido['cliente_nombre'] ?? 'Público General'); ?>
                    </td>
                    <td class="td-datos" style="text-align: center; mso-number-format: '\@';">
                        <?php echo htmlspecialchars($pedido['telefono'] ?? '-'); ?>
                    </td>
                    <td class="td-datos">
                        <?php echo htmlspecialchars($pedido['vendedor_nombre'] ?? 'Sistema'); ?>
                    </td>
                    <td class="td-datos" style="text-align: center;">
                        <?php echo htmlspecialchars($pedido['metodo_pago']); ?>
                    </td>
                    <td class="td-datos badge-pago" style="text-align: center;">
                        <?php echo htmlspecialchars($pedido['status_pago']); ?>
                    </td>
                    <td class="td-datos" style="text-align: center; color: <?php echo ($pedido['estatus'] === 'Por Surtir') ? '#b45309' : '#15803d'; ?>;">
                        <?php echo htmlspecialchars($pedido['estatus']); ?>
                    </td>
                    <td class="td-numero">
                        <?php echo (float)$pedido['total']; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>