<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$id_orden = $_GET['id'] ?? null;
$filtro_prov = $_GET['prov'] ?? null; // Filtro por proveedor específico

if (!$id_orden) die("ID de orden no especificado.");

// 1. OBTENER CABECERA
$stmt = $pdo->prepare("SELECT * FROM ordenes_produccion WHERE id = ?");
$stmt->execute([$id_orden]);
$orden = $stmt->fetch();

// 2. OBTENER INSUMOS FILTRADOS POR PROVEEDOR
$sql_insumos = "
    SELECT odi.*, i.nombre, i.unidad_medida, prov.nombre_empresa as proveedor 
    FROM orden_detalle_insumos odi 
    JOIN insumos i ON odi.id_insumo = i.id 
    LEFT JOIN proveedores prov ON i.id_proveedor = prov.id_proveedor
    WHERE odi.id_orden = ?
";

if ($filtro_prov) {
    $sql_insumos .= " AND (prov.nombre_empresa = ? OR (prov.nombre_empresa IS NULL AND ? = 'Sin Proveedor'))";
}

$sql_insumos .= " ORDER BY i.nombre ASC";
$stmt_i = $pdo->prepare($sql_insumos);

if ($filtro_prov) {
    $stmt_i->execute([$id_orden, $filtro_prov, $filtro_prov]);
} else {
    $stmt_i->execute([$id_orden]);
}
$insumos = $stmt_i->fetchAll();

ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>OC #<?php echo $id_orden; ?> - <?php echo htmlspecialchars($filtro_prov); ?></title>
    <style>
        body { font-family: 'Helvetica', Arial, sans-serif; padding: 40px; color: #333; }
        .header { text-align: center; border-bottom: 3px solid #2b6cb0; margin-bottom: 20px; padding-bottom: 10px; }
        .prov-box { background: #edf2f7; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 5px solid #2b6cb0; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; border: 1px solid #cbd5e0; padding: 10px; text-align: left; font-size: 12px; }
        td { border: 1px solid #cbd5e0; padding: 10px; font-size: 13px; }
        .footer { margin-top: 50px; text-align: center; font-size: 10px; color: #a0aec0; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="text-align:right;"><button onclick="window.print()">Imprimir / Guardar PDF</button></div>

    <div class="header">
        <div style="font-size: 24px; font-weight: bold; color: #2b6cb0;">AHD CLEAN</div>
        <div style="font-size: 14px; font-weight: bold;">ORDEN DE SURTIDO #<?php echo $id_orden; ?></div>
    </div>

    <div class="prov-box">
        <strong>PROVEEDOR:</strong> <?php echo htmlspecialchars($filtro_prov ?? 'GENERAL'); ?><br>
        <strong>FECHA:</strong> <?php echo date('d/m/Y', strtotime($orden['fecha_registro'])); ?>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 70%;">Descripción del Material</th>
                <th style="text-align: center;">Cantidad</th>
                <th style="text-align: center;">Unidad</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($insumos as $i): ?>
            <tr>
                <td><?php echo htmlspecialchars($i['nombre']); ?></td>
                <td style="text-align: center; font-weight: bold;"><?php echo number_format($i['cantidad_usada'], 3); ?></td>
                <td style="text-align: center;"><?php echo $i['unidad_medida']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top: 50px; display: flex; justify-content: space-around;">
        <div style="border-top: 1px solid #000; width: 200px; text-align: center; padding-top: 5px; font-size: 12px;">Autorizó (AHD Clean)</div>
        <div style="border-top: 1px solid #000; width: 200px; text-align: center; padding-top: 5px; font-size: 12px;">Recibió Vendedor</div>
    </div>

    <div class="footer">Este documento es confidencial y solo contiene materiales de surtido.</div>

    <script>window.print();</script>
</body>
</html>
<?php echo ob_get_clean(); ?>