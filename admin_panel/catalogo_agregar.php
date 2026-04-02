<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre_base = $_POST['nombre'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    $formula = $_POST['formula'] ?? 0;
    $presentaciones_elegidas = $_POST['presentaciones'] ?? []; 
    $precios_presentaciones = $_POST['precios'] ?? []; 

    $imagen_url = '';

    // --- TU LÓGICA DE SUBIDA DE IMAGEN ---
    if (isset($_FILES['imagen_archivo']) && $_FILES['imagen_archivo']['error'] === UPLOAD_ERR_OK) {
        $ruta_destino = '../img/';
        if (!is_dir($ruta_destino)) { mkdir($ruta_destino, 0777, true); }
        $nombre_archivo = time() . '_' . basename($_FILES['imagen_archivo']['name']);
        $archivo_final = $ruta_destino . $nombre_archivo;
        
        $tipo_archivo = strtolower(pathinfo($archivo_final, PATHINFO_EXTENSION));
        if (in_array($tipo_archivo, ["png", "jpg", "jpeg", "webp"])) {
            if (move_uploaded_file($_FILES['imagen_archivo']['tmp_name'], $archivo_final)) {
                $imagen_url = $archivo_final;
            }
        }
    }

    if (empty($presentaciones_elegidas)) {
        $error = "Debes seleccionar al menos una presentación (1L, 5L, etc.)";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Query ajustada a tus columnas reales
            $stmt = $pdo->prepare("INSERT INTO productos (nombre, descripcion, precio, imagen_url, categoria, id_formula_maestra, volumen_valor, volumen_unidad) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($presentaciones_elegidas as $pres_id) {
                // Consultamos los detalles de la presentación configurada
                $stmt_p = $pdo->prepare("SELECT valor, unidad FROM config_presentaciones WHERE id = ?");
                $stmt_p->execute([$pres_id]);
                $p_info = $stmt_p->fetch();

                $precio_especifico = $precios_presentaciones[$pres_id] ?? 0;
                
                // Nombre compuesto para que en el ticket salga claro: "Cloro (5L)"
                $nombre_final = $nombre_base . " (" . number_format($p_info['valor'], 0) . $p_info['unidad'] . ")";

                $stmt->execute([
                    $nombre_final, 
                    $descripcion, 
                    $precio_especifico, 
                    $imagen_url, 
                    $categoria, 
                    $formula,
                    $p_info['valor'],
                    $p_info['unidad']
                ]);
            }

            $pdo->commit();
            header('Location: catalogo_productos.php?msj=Creados');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al crear presentaciones: " . $e->getMessage();
        }
    }
}

// Consultas para llenar el formulario
$categorias_db = $pdo->query("SELECT * FROM categorias ORDER BY nombre ASC")->fetchAll();
$formulas_db = $pdo->query("SELECT id, nombre_formula FROM formulas_maestras ORDER BY nombre_formula ASC")->fetchAll();
$presentaciones_db = $pdo->query("SELECT * FROM config_presentaciones ORDER BY valor ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Nuevo Producto | AHD Clean</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .grid-pres { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-top: 10px; }
        .card-pres { background: #f8fafc; padding: 15px; border-radius: 10px; border: 1px solid #e2e8f0; }
        .card-pres label { font-weight: bold; color: #1e293b; cursor: pointer; display: block; }
        .precio-input { width: 100%; padding: 8px; margin-top: 10px; border: 1px solid #cbd5e0; border-radius: 6px; display: none; }
        /* Mostrar el input de precio solo si el checkbox está marcado */
        .check-pres:checked ~ .precio-input { display: block; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header">
            <h1><i class="fas fa-plus-circle"></i> Alta de Producto y Presentaciones</h1>
            <a href="catalogo_productos.php" class="btn-secondary" style="text-decoration:none; padding:8px 15px; background:#64748b; color:white; border-radius:6px;">Volver</a>
        </div>

        <?php if (isset($error)): ?>
            <div style="background:#fee2e2; color:#b91c1c; padding:15px; border-radius:8px; margin-bottom:20px;"><?= $error ?></div>
        <?php endif; ?>

        <div class="form-container" style="background:white; padding:25px; border-radius:15px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">
            <form method="POST" enctype="multipart/form-data">
                
                <div style="display:grid; grid-template-columns: 2fr 1fr; gap:20px;">
                    <div class="form-group">
                        <label>Nombre del Producto (Sin el tamaño)</label>
                        <input type="text" name="nombre" class="form-control" placeholder="Ej: Desengrasante Multiusos" required>
                    </div>
                    <div class="form-group">
                        <label>Categoría</label>
                        <select name="categoria" class="form-control" required>
                            <?php foreach($categorias_db as $c): ?>
                                <option value="<?= $c['nombre'] ?>"><?= $c['nombre'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Descripción General</label>
                    <textarea name="descripcion" class="form-control" rows="2"></textarea>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                    <div class="form-group">
                        <label>Fórmula Química Asociada</label>
                        <select name="formula" class="form-control" required>
                            <option value="">Seleccionar fórmula...</option>
                            <?php foreach($formulas_db as $f): ?>
                                <option value="<?= $f['id'] ?>"><?= $f['nombre_formula'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Imagen del Producto</label>
                        <input type="file" name="imagen_archivo" class="form-control">
                    </div>
                </div>

                <hr style="margin:30px 0; border:0; border-top:1px solid #eee;">

                <div class="form-group">
                    <label style="font-size:1.1rem; color:#1e293b;"><i class="fas fa-layer-group"></i> Selecciona las presentaciones a crear:</label>
                    <div class="grid-pres">
                        <?php foreach($presentaciones_db as $p): ?>
                        <div class="card-pres">
                            <label>
                                <input type="checkbox" name="presentaciones[]" value="<?= $p['id'] ?>" class="check-pres"> 
                                <?= $p['etiqueta'] ?>
                            </label>
                            <input type="number" step="0.01" name="precios[<?= $p['id'] ?>]" class="precio-input" placeholder="Precio para <?= $p['valor'].$p['unidad'] ?>">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="margin-top:30px; text-align:right;">
                    <button type="submit" class="btn-guardar" style="background:#059669; color:white; border:none; padding:12px 25px; border-radius:8px; font-weight:bold; cursor:pointer;">
                        <i class="fas fa-save"></i> Generar Productos
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>