<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

$mensaje = "";
$error = "";

try {
    // 1. ACCIÓN: PREPARAR STOCK (Tanque -> Estante de Muestras)
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['preparar_muestras'])) {
        $id_formula = $_POST['id_formula'];
        $litros = floatval($_POST['litros_prep'] ?? 0);

        if ($id_formula && $litros > 0) {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT stock_litros_disponibles FROM formulas_maestras WHERE id = ?");
            $stmt->execute([$id_formula]);
            $stock_tq = $stmt->fetchColumn() ?: 0;

            if ($stock_tq >= $litros) {
                $upd = $pdo->prepare("UPDATE formulas_maestras SET stock_litros_disponibles = stock_litros_disponibles - ?, stock_muestras_preparadas = stock_muestras_preparadas + ? WHERE id = ?");
                $upd->execute([$litros, $litros, $id_formula]);
                $pdo->commit();
                $mensaje = "Inventario actualizado: $litros L pasaron al estante.";
            } else {
                $pdo->rollBack();
                $error = "Stock insuficiente en tanque ($stock_tq L disponibles).";
            }
        }
    }

    // 2. ACCIÓN: ENTREGAR MUESTRA (Estante -> Cliente)
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['entregar_muestra'])) {
        $id_f = $_POST['id_formula_entregar'];
        $litros_e = floatval($_POST['litros_entregar'] ?? 0);
        $destino = $_POST['destino'] ?? 'Cliente General';

        if ($id_f && $litros_e > 0) {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT stock_muestras_preparadas FROM formulas_maestras WHERE id = ?");
            $stmt->execute([$id_f]);
            $stock_estante = $stmt->fetchColumn() ?: 0;

            if ($stock_estante >= $litros_e) {
                // Cálculo de costo masivo
                $sql_c = "SELECT SUM(cantidad_por_litro * COALESCE((SELECT MIN(precio_presentacion / cantidad_capacidad) FROM insumo_presentaciones WHERE id_insumo = f.insumo_id), (SELECT precio_unitario FROM insumos WHERE id = f.insumo_id))) FROM formulas f WHERE f.id_formula_maestra = ?";
                $stmt_c = $pdo->prepare($sql_c);
                $stmt_c->execute([$id_f]);
                $costo_l = $stmt_c->fetchColumn() ?: 0;
                $inv_total = $litros_e * $costo_l;

                $pdo->prepare("UPDATE formulas_maestras SET stock_muestras_preparadas = stock_muestras_preparadas - ? WHERE id = ?")->execute([$litros_e, $id_f]);
                $pdo->prepare("INSERT INTO muestras_gratis (id_formula_maestra, litros, costo_total, destino_cliente) VALUES (?, ?, ?, ?)")->execute([$id_f, $litros_e, $inv_total, $destino]);

                $pdo->commit();
                $mensaje = "Entrega registrada a $destino.";
            } else {
                $pdo->rollBack();
                $error = "No hay suficientes muestras preparadas ($stock_estante L en estante).";
            }
        }
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $error = "Error: " . $e->getMessage();
}

// 3. CARGA DE DATOS (Columnas corregidas)
$tanques = $pdo->query("SELECT id, nombre_formula, stock_litros_disponibles, stock_muestras_preparadas FROM formulas_maestras ORDER BY nombre_formula ASC")->fetchAll();
$clientes = $pdo->query("SELECT nombre_completo FROM clientes ORDER BY nombre_completo ASC")->fetchAll();
$historial = $pdo->query("SELECT m.*, f.nombre_formula FROM muestras_gratis m JOIN formulas_maestras f ON m.id_formula_maestra = f.id ORDER BY m.fecha DESC LIMIT 10")->fetchAll();
$inv_acumulada = $pdo->query("SELECT SUM(costo_total) FROM muestras_gratis")->fetchColumn() ?: 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Muestras | AHD Clean</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        :root { --ahd-blue: #4c51bf; --ahd-dark: #1a202c; }
        body { background: #f7fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding-bottom: 50px; }
        .main-container { padding: 10px; margin-left: 260px; transition: 0.3s; }
        .banner { background: var(--ahd-dark); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .grid-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 15px; }
        .card { background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .search-box { width: 100%; padding: 12px; border: 1px solid #cbd5e0; border-radius: 8px 8px 0 0; font-size: 16px; box-sizing: border-box; background: #fff; }
        .list-box { width: 100%; border: 1px solid #cbd5e0; border-top: none; border-radius: 0 0 8px 8px; height: 160px; font-size: 14px; margin-bottom: 15px; }
        .input-qty { width: 100%; padding: 15px; border: 1px solid #cbd5e0; border-radius: 8px; font-size: 18px; margin-bottom: 15px; box-sizing: border-box; text-align: center; }
        .btn-ahd { width: 100%; padding: 18px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 16px; color: white; text-transform: uppercase; }
        .btn-prep { background: #4a5568; }
        .btn-give { background: var(--ahd-blue); }
        
        @media (max-width: 1024px) { .main-container { margin-left: 0; } }
        @media (max-width: 480px) { .banner { flex-direction: column; text-align: center; gap: 10px; } }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-container">
        <div class="banner">
            <div>
                <h1 style="margin:0; font-size: 1.5rem;"><i class="fas fa-flask"></i> Gestión de Muestras</h1>
                <span style="opacity: 0.7;">AHD Clean - Control de Cortesía</span>
            </div>
            <div style="text-align: right;">
                <small>INVERSIÓN</small><br>
                <span style="font-size: 1.8rem; font-weight: 800;">$<?php echo number_format($inv_acumulada, 2); ?></span>
            </div>
        </div>

        <?php if($mensaje): ?> <div style="background:#c6f6d5; color:#22543d; padding:15px; border-radius:10px; margin-bottom:15px; border:1px solid #9ae6b4;"><?php echo $mensaje; ?></div> <?php endif; ?>
        <?php if($error): ?> <div style="background:#fed7d7; color:#822727; padding:15px; border-radius:10px; margin-bottom:15px; border:1px solid #feb2b2;"><?php echo $error; ?></div> <?php endif; ?>

        <div class="grid-cards">
            <!-- 1. PREPARACIÓN -->
            <div class="card">
                <h3 style="margin:0 0 10px 0;"><i class="fas fa-box"></i> 1. Preparar Muestras</h3>
                <form method="POST">
                    <input type="text" class="search-box" placeholder="Buscar tanque..." onkeyup="filter(this, 's_prep')">
                    <select name="id_formula" id="s_prep" class="list-box" required size="5">
                        <?php foreach($tanques as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['nombre_formula']); ?> (Tq: <?php echo $t['stock_litros_disponibles']; ?>L)</option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="litros_prep" step="0.001" class="input-qty" placeholder="Litros a pasar" required>
                    <button type="submit" name="preparar_muestras" class="btn-ahd btn-prep">PASAR A ESTANTE</button>
                </form>
            </div>

            <!-- 2. ENTREGA -->
            <div class="card" style="border-top: 5px solid var(--ahd-blue);">
                <h3 style="margin:0 0 10px 0; color: var(--ahd-blue);"><i class="fas fa-gift"></i> 2. Regalar Muestra</h3>
                <form method="POST">
                    <input type="text" class="search-box" placeholder="¿Qué entregas?" onkeyup="filter(this, 's_give')">
                    <select name="id_formula_entregar" id="s_give" class="list-box" required size="5">
                        <?php foreach($tanques as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['nombre_formula']); ?> (Disp: <?php echo $t['stock_muestras_preparadas']; ?>L)</option>
                        <?php endforeach; ?>
                    </select>

                    <input type="text" class="search-box" placeholder="¿A quién?" onkeyup="filter(this, 's_cli')">
                    <select name="destino" id="s_cli" class="list-box" required size="5">
                        <?php foreach($clientes as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['nombre_completo']); ?>"><?php echo htmlspecialchars($c['nombre_completo']); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <input type="number" name="litros_entregar" step="0.001" class="input-qty" placeholder="Litros regalados" required>
                    <button type="submit" name="entregar_muestra" class="btn-ahd btn-give">REGISTRAR REGALO</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function filter(input, id) {
            var val = input.value.toLowerCase();
            var opts = document.getElementById(id).options;
            for (var i = 0; i < opts.length; i++) {
                opts[i].style.display = opts[i].text.toLowerCase().includes(val) ? "" : "none";
            }
        }
    </script>
</body>
</html> 