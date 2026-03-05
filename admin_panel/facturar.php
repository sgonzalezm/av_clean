<?php
session_start();
include '../includes/conexion.php';

$pedido_id = $_GET['id'] ?? null;
if (!$pedido_id) die("ID de pedido no proporcionado.");

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    
    // Obtener datos del pedido
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verificar si ya existe factura
    $stmt_fac = $pdo->prepare("SELECT * FROM facturacion WHERE pedido_id = ?");
    $stmt_fac->execute([$pedido_id]);
    $factura_existente = $stmt_fac->fetch(PDO::FETCH_ASSOC);

} catch(PDOException $e) { die("Error: " . $e->getMessage()); }

// Procesar el guardado (Simulación de timbrado)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $stmt_save = $pdo->prepare("INSERT INTO facturacion (pedido_id, rfc, razon_social, regimen_fiscal, uso_cfdi, cp_fiscal) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt_save->execute([
        $pedido_id,
        $_POST['rfc'],
        $_POST['razon_social'],
        $_POST['regimen'],
        $_POST['uso'],
        $_POST['cp']
    ]);
    header("Location: facturar.php?id=$pedido_id&msg=success");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Facturación - Pedido #<?php echo $pedido_id; ?></title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .fac-container { max-width: 800px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .grid-form { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .campo { display: flex; flex-direction: column; }
        label { font-weight: bold; margin-bottom: 5px; color: #4a5568; }
        input, select { padding: 10px; border: 1px solid #cbd5e0; border-radius: 6px; }
        .resumen-pedido { background: #f7fafc; padding: 15px; border-radius: 8px; margin-bottom: 25px; border-left: 4px solid #1a365d; }
        .btn-facturar { background: #1a365d; color: white; padding: 15px; border: none; border-radius: 8px; cursor: pointer; width: 100%; font-weight: bold; margin-top: 20px; }
        .success-msg { background: #c6f6d5; color: #22543d; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="fac-container">
            <a href="pedidos.php">← Volver a Pedidos</a>
            <h1>Generar Factura (prueba)</h1>

            <?php if(isset($_GET['msg'])): ?>
                <div class="success-msg">✅ Datos fiscales guardados con éxito. Listo para generar XML/PDF.</div>
            <?php endif; ?>

            <div class="resumen-pedido">
                <strong>Resumen del Pedido #<?php echo $pedido_id; ?></strong><br>
                Monto a facturar: <span style="font-size: 1.2rem; color: #2d3748;">$<?php echo number_format($pedido['total'], 2); ?></span><br>
                Cliente: <?php echo $pedido['email']; ?>
            </div>

            <?php if ($factura_existente): ?>
                <div style="background: #ebf8ff; padding: 20px; border-radius: 8px; border: 1px solid #bee3f8;">
                    <h3>Factura Generada</h3>
                    <p><strong>RFC:</strong> <?php echo $factura_existente['rfc']; ?></p>
                    <p><strong>Razón Social:</strong> <?php echo $factura_existente['razon_social']; ?></p>
                    <button onclick="window.print()" class="btn-facturar" style="background: #4a5568;">Reimprimir Copia</button>
                </div>
            <?php else: ?>
                <form method="POST">
                    <div class="grid-form">
                        <div class="campo">
                            <label>RFC</label>
                            <input type="text" name="rfc" required placeholder="XAXX010101000">
                        </div>
                        <div class="campo">
                            <label>Razón Social</label>
                            <input type="text" name="razon_social" required placeholder="Nombre completo o Empresa">
                        </div>
                        <div class="campo">
                            <label>Régimen Fiscal</label>
                            <select name="regimen">
                                <option value="601">General de Ley Personas Morales</option>
                                <option value="612">Personas Físicas con Actividades Empresariales</option>
                                <option value="626">Régimen Simplificado de Confianza (RESICO)</option>
                                <option value="605">Sueldos y Salarios</option>
                            </select>
                        </div>
                        <div class="campo">
                            <label>Uso de CFDI</label>
                            <select name="uso">
                                <option value="G03">G03 - Gastos en general</option>
                                <option value="P01">P01 - Por definir</option>
                                <option value="S01">S01 - Sin efectos fiscales</option>
                            </select>
                        </div>
                        <div class="campo">
                            <label>Código Postal Fiscal</label>
                            <input type="text" name="cp" required placeholder="44100">
                        </div>
                    </div>
                    <button type="submit" class="btn-facturar">Generar Comprobante de Facturación</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <script src="../js/admin.js"></script>
</body>
</html>