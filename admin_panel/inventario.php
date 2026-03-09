<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// 1. Recibir filtros
$sucursal_filtro = isset($_GET['sucursal_id']) ? $_GET['sucursal_id'] : '';
$categoria_filtro = isset($_GET['categoria_id']) ? $_GET['categoria_id'] : '';

// 2. Construir la consulta principal (Se usará tanto para la tabla como para el Excel)
$query = "SELECT p.id as producto_id, p.nombre as producto_nombre, p.categoria, 
                 s.nombre as sucursal_nombre, i.stock, i.sucursal_id
          FROM inventario i
          JOIN productos p ON i.producto_id = p.id
          JOIN sucursales s ON i.sucursal_id = s.id
          WHERE 1=1";

$params = [];

if (!empty($sucursal_filtro)) {
    $query .= " AND i.sucursal_id = :sid";
    $params['sid'] = $sucursal_filtro;
}

if (!empty($categoria_filtro)) {
    $query .= " AND p.categoria = :cat";
    $params['cat'] = $categoria_filtro;
}

$query .= " ORDER BY p.nombre ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$inventario = $stmt->fetchAll();

// ============================================================
// LÓGICA DE EXPORTACIÓN A EXCEL (CSV)
// ============================================================
if (isset($_GET['exportar']) && $_GET['exportar'] == 'csv') {
    $filename = "inventario_ahd_" . date('d-m-Y_His') . ".csv";
    
    // Forzar la descarga del archivo
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Abrir el flujo de salida
    $output = fopen('php://output', 'w');
    
    // BOM para que Excel en Windows reconozca eñes y tildes
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Encabezados del CSV
    fputcsv($output, ['Producto', 'Categoría', 'Sucursal', 'Stock', 'Estado']);
    
    // Llenar el CSV con los mismos datos de la tabla filtrada
    foreach ($inventario as $row) {
        $estado = ($row['stock'] <= 0) ? 'Agotado' : (($row['stock'] < 10) ? 'Bajo Stock' : 'Disponible');
        
        fputcsv($output, [
            $row['producto_nombre'],
            $row['categoria'],
            $row['sucursal_nombre'],
            $row['stock'],
            $estado
        ]);
    }
    
    fclose($output);
    exit; // Importante: detiene el renderizado del HTML
}

// 3. Datos para los selectores del formulario (solo si no se exportó)
$sucursales_list = $pdo->query("SELECT id, nombre FROM sucursales ORDER BY nombre")->fetchAll();
$categorias_list = $pdo->query("SELECT DISTINCT categoria FROM productos WHERE categoria IS NOT NULL ORDER BY categoria")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Inventario Filtrado - AHD Clean</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .filtros-barra {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: flex-end;
            border: 1px solid #e2e8f0;
            flex-wrap: wrap;
        }
        .filtro-grupo { display: flex; flex-direction: column; gap: 5px; }
        .filtro-grupo label { font-size: 0.8rem; font-weight: bold; color: #64748b; }
        .filtro-grupo select { padding: 8px; border-radius: 5px; border: 1px solid #cbd5e1; min-width: 150px; }
        .btn-filtrar { background: #1a365d; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-excel { background: #2d6a4f; } /* Verde tipo Excel */
        .btn-limpiar { background: #e2e8f0; color: #475569; text-decoration: none; padding: 10px 20px; border-radius: 5px; font-size: 0.9rem; }
        .acciones-tabla { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header">
            <h1>Gestión de Inventario</h1>
        </div>

        <form method="GET" class="filtros-barra">
            <div class="filtro-grupo">
                <label>Sucursal</label>
                <select name="sucursal_id">
                    <option value="">Todas las sucursales</option>
                    <?php foreach($sucursales_list as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $sucursal_filtro == $s['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filtro-grupo">
                <label>Categoría</label>
                <select name="categoria_id">
                    <option value="">Todas las categorías</option>
                    <?php foreach($categorias_list as $c): ?>
                        <option value="<?php echo $c['categoria']; ?>" <?php echo $categoria_filtro == $c['categoria'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['categoria']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn-filtrar"><i class="fas fa-filter"></i> Filtrar</button>
            <a href="inventario.php" class="btn-limpiar">Limpiar</a>
        </form>

        <div class="acciones-tabla">
            <p style="color: #64748b;">Mostrando <strong><?php echo count($inventario); ?></strong> productos en inventario.</p>
            
            <?php 
                $query_string = $_GET; 
                $query_string['exportar'] = 'csv'; 
                $enlace_excel = "?" . http_build_query($query_string);
            ?>
            <a href="<?php echo $enlace_excel; ?>" class="btn-filtrar btn-excel">
                <i class="fas fa-file-excel"></i> Exportar a Excel
            </a>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Sucursal</th>
                    <th>Stock</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($inventario) > 0): ?>
                    <?php foreach ($inventario as $item): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($item['producto_nombre']); ?></strong></td>
                        <td><?php echo htmlspecialchars($item['sucursal_nombre']); ?></td>
                        <td><?php echo $item['stock']; ?></td>
                        <td>
                            <?php if($item['stock'] <= 0): ?>
                                <span class="badge-rol" style="background:#feb2b2; color:#c53030;">Agotado</span>
                            <?php elseif($item['stock'] < 10): ?>
                                <span class="badge-rol" style="background:#fef3c7; color:#92400e;">Bajo Stock</span>
                            <?php else: ?>
                                <span class="badge-rol" style="background:#c6f6d5; color:#22543d;">Disponible</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="inventario_editar.php?p_id=<?php echo $item['producto_id']; ?>&s_id=<?php echo $item['sucursal_id']; ?>" class="btn-small">✏️ Ajustar</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding:30px; color:#64748b;">No se encontraron productos con los filtros seleccionados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <script src="../js/admin.js"></script>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }
    </script>
</body>
</html>