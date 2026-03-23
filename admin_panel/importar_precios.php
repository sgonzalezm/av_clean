<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$mensaje = "";
$errores = [];
$procesados = 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['archivo_csv'])) {
    $file = $_FILES['archivo_csv']['tmp_name'];
    
    // Abrir archivo en modo lectura
    if (($handle = fopen($file, "r")) !== FALSE) {
        
        // Omitir la primera línea (encabezados)
        fgetcsv($handle, 1000, ","); 

        try {
            $pdo->beginTransaction();

            while (($datos = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Si la línea está vacía o no tiene el nombre del producto, saltar
                if (!isset($datos[1]) || empty(trim($datos[1]))) {
                    continue;
                }

                $nombre_insumo = strtoupper(trim($datos[1])); // Normalizamos a mayúsculas

                // 1. Intentar insertar el insumo si no existe
                // Usamos INSERT IGNORE para evitar errores de duplicidad por nombre
                $stmt = $pdo->prepare("INSERT IGNORE INTO insumos (nombre, unidad_medida, stock_actual, stock_minimo, cantidad_minima, precio_unitario) 
                                       VALUES (?, 'kg', 0, 0, 0, 0)");
                $stmt->execute([$nombre_insumo]);
                
                // 2. Obtener el ID real del insumo (sea nuevo o viejo)
                $stmt_id = $pdo->prepare("SELECT id FROM insumos WHERE nombre = ?");
                $stmt_id->execute([$nombre_insumo]);
                $insumo_id = $stmt_id->fetchColumn();

                // 3. Validación de integridad (Lo que causaba tu error anterior)
                if (!$insumo_id) {
                    $errores[] = "No se pudo encontrar/crear el ID para: $nombre_insumo";
                    continue;
                }

                // 4. Mapear Escalas del CSV (Columnas 1kg, 5kg, 20kg, 50kg, 200kg)
                // Ajusta los índices [2, 3, 4, 5, 6] si tu CSV tiene un orden distinto
                $escalas = [
                    1   => $datos[2] ?? 0,
                    5   => $datos[3] ?? 0,
                    20  => $datos[4] ?? 0,
                    50  => $datos[5] ?? 0,
                    200 => $datos[6] ?? 0
                ];

                // Limpiar escalas viejas para este insumo antes de insertar las nuevas
                $pdo->prepare("DELETE FROM precios_escalas WHERE insumo_id = ?")->execute([$insumo_id]);

                foreach ($escalas as $cant_escala => $precio_raw) {
                    // Limpieza: quitar $, comas y espacios
                    $precio_limpio = preg_replace('/[^0-9.]/', '', $precio_raw);
                    
                    if (is_numeric($precio_limpio) && $precio_limpio > 0) {
                        $stmtE = $pdo->prepare("INSERT INTO precios_escalas (insumo_id, cantidad_minima, precio) VALUES (?, ?, ?)");
                        $stmtE->execute([$insumo_id, $cant_escala, $precio_limpio]);
                        
                        // Opcional: El precio de 1kg lo guardamos como precio base en la tabla insumos
                        if($cant_escala == 1) {
                            $updBase = $pdo->prepare("UPDATE insumos SET precio_unitario = ? WHERE id = ?");
                            $updBase->execute([$precio_limpio, $insumo_id]);
                        }
                    }
                }
                $procesados++;
            }

            $pdo->commit();
            $mensaje = "success";
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = "error";
            $errores[] = "Error crítico de base de datos: " . $e->getMessage();
        }
        fclose($handle);
    } else {
        $mensaje = "error";
        $errores[] = "No se pudo abrir el archivo subido.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Importador Maestro | AHD Clean</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .container-import { max-width: 800px; margin: 40px auto; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid transparent; }
        .alert-success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .upload-area { border: 2px dashed #cbd5e0; padding: 40px; border-radius: 15px; text-align: center; background: #fff; cursor: pointer; transition: 0.3s; }
        .upload-area:hover { border-color: #4299e1; background: #ebf8ff; }
        .error-list { background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #f5c6cb; margin-top: 10px; font-size: 0.85rem; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="container-import">
            <div class="header">
                <h1><i class="fas fa-file-csv"></i> Importador de Precios por Volumen</h1>
                <a href="gestion_compras.php" class="btn-cancelar" style="text-decoration:none;">Volver</a>
            </div>

            <?php if ($mensaje == "success"): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <strong>¡Éxito!</strong> Se procesaron <b><?php echo $procesados; ?></b> insumos correctamente.
                </div>
            <?php endif; ?>

            <?php if (!empty($errores)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Hubo inconvenientes:</strong>
                    <div class="error-list">
                        <?php foreach($errores as $err): ?>
                            <li><?php echo $err; ?></li>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card">
                <h3>Instrucciones de formato</h3>
                <p style="font-size: 0.9rem; color: #4a5568;">
                    El archivo debe ser <b>.CSV</b> con este orden de columnas:<br>
                    <code>Proveedor, Producto, 1kg, 5kg, 20kg, 50kg, 200kg</code>
                </p>
                <br>
                <form method="POST" enctype="multipart/form-data">
                    <label for="file-upload" class="upload-area">
                        <i class="fas fa-cloud-upload-alt fa-3x" style="color: #4299e1;"></i>
                        <p style="margin-top:10px;">Haz clic para seleccionar el archivo CSV o arrástralo aquí</p>
                        <input id="file-upload" type="file" name="archivo_csv" accept=".csv" required style="display:none;">
                    </label>
                    <br><br>
                    <button type="submit" class="btn" style="width: 100%; padding: 15px; font-weight: bold;">
                        <i class="fas fa-sync-alt"></i> INICIAR IMPORTACIÓN MASIVA
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Cambiar texto al seleccionar archivo
        document.getElementById('file-upload').onchange = function() {
            this.parentElement.querySelector('p').innerHTML = "<b>Archivo seleccionado:</b> " + this.files[0].name;
        };
    </script>
</body>
</html>