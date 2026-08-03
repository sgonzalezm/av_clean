<?php
session_start();
include '../includes/conexion.php';
include '../includes/session.php';
verificarSesion();

// Configurar headers para descarga de Excel
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=pedidos_completos_" . date('Y-m-d') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// Consulta todos los pedidos sin filtrar
$sql = "SELECT 
            p.id as folio,
            DATE_FORMAT(p.fecha_pedido, '%d/%m/%Y %H:%i') as fecha,
            c.nombre_completo as cliente,
            c.telefono,
            p.status_logistica as estado_logistica,
            p.status_pago as estado_pago,
            p.total,
            (SELECT SUM(cantidad) FROM detalle_pedido WHERE pedido_id = p.id) as total_productos
        FROM pedidos p 
        LEFT JOIN clientes c ON p.cliente_id = c.id 
        ORDER BY p.fecha_pedido DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estilos para el Excel
echo "<html xmlns:o='urn:schemas-microsoft-com:office:office' 
          xmlns:x='urn:schemas-microsoft-com:office:excel' 
          xmlns='http://www.w3.org/TR/REC-html40'>";
echo "<head><meta charset='utf-8'><style>
    th { background-color: #1e293b; color: white; font-weight: bold; padding: 8px; border: 1px solid #ccc; }
    td { padding: 8px; border: 1px solid #ccc; }
    .total { font-weight: bold; }
    .por-surtir { background-color: #fef3c7; }
    .surtido { background-color: #dbeafe; }
    .entregado { background-color: #dcfce7; }
</style></head><body>";

echo "<h2>Reporte de Pedidos - " . date('d/m/Y H:i') . "</h2>";

// Tabla
echo "<table>";
echo "<tr>";
echo "<th>Folio</th>";
echo "<th>Fecha</th>";
echo "<th>Cliente</th>";
echo "<th>Teléfono</th>";
echo "<th>Estado Logística</th>";
echo "<th>Estado Pago</th>";
echo "<th>Total</th>";
echo "<th>Productos</th>";
echo "</tr>";

$total_general = 0;
foreach($pedidos as $p) {
    $clase = '';
    if($p['estado_logistica'] == 'Por Surtir') $clase = 'por-surtir';
    elseif($p['estado_logistica'] == 'Surtido') $clase = 'surtido';
    elseif($p['estado_logistica'] == 'Entregado') $clase = 'entregado';
    
    echo "<tr class='$clase'>";
    echo "<td>#" . $p['folio'] . "</td>";
    echo "<td>" . $p['fecha'] . "</td>";
    echo "<td>" . htmlspecialchars($p['cliente']) . "</td>";
    echo "<td>" . $p['telefono'] . "</td>";
    echo "<td>" . $p['estado_logistica'] . "</td>";
    echo "<td>" . $p['estado_pago'] . "</td>";
    echo "<td>$" . number_format($p['total'], 2) . "</td>";
    echo "<td>" . ($p['total_productos'] ?? 0) . "</td>";
    echo "</tr>";
    $total_general += $p['total'];
}

// Fila de totales
echo "<tr style='background-color: #f8fafc; font-weight: bold;'>";
echo "<td colspan='6' style='text-align:right;'>TOTAL GENERAL</td>";
echo "<td>$" . number_format($total_general, 2) . "</td>";
echo "<td>" . count($pedidos) . " pedidos</td>";
echo "</tr>";

echo "</table>";

// Resumen por estados
echo "<br><br>";
echo "<h3>Resumen por Estado Logística</h3>";
$resumen = $pdo->query("SELECT status_logistica, COUNT(*) as total, SUM(total) as suma FROM pedidos GROUP BY status_logistica")->fetchAll(PDO::FETCH_ASSOC);
echo "<table>";
echo "<tr><th>Estado</th><th>Cantidad</th><th>Total</th></tr>";
foreach($resumen as $r) {
    echo "<tr>";
    echo "<td>" . $r['status_logistica'] . "</td>";
    echo "<td>" . $r['total'] . "</td>";
    echo "<td>$" . number_format($r['suma'], 2) . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "</body></html>";
?>