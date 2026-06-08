<?php
session_start();
include '../includes/conexion.php';

$pedido_id = $_GET['id'] ?? null;
if (!$pedido_id) die("ID de pedido no proporcionado.");

try {
    // Usamos de forma directa la variable de conexión $pdo heredada de conexion.php
    // Se elimina el bloque redundante 'new PDO' para evitar conflictos con los parámetros del servidor.
    
    // Obtener datos del pedido y unir dinámicamente el nombre o correo del cliente
    $stmt = $pdo->prepare("
        SELECT p.*, c.nombre_completo AS cliente_nombre 
        FROM pedidos p 
        LEFT JOIN clientes c ON p.cliente_id = c.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) die("Pedido no encontrado.");

    // Verificar si ya existe un registro de facturación para este pedido
    $stmt_fac = $pdo->prepare("SELECT * FROM facturacion WHERE pedido_id = ?");
    $stmt_fac->execute([$pedido_id]);
    $factura_existente = $stmt_fac->fetch(PDO::FETCH_ASSOC);

} catch(PDOException $e) { 
    die("Error en la base de datos: " . $e->getMessage()); 
}

// Procesar el guardado de datos fiscales validados en tu tabla real
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Limpieza de inputs y estandarización a mayúsculas para el RFC
    $rfc_input = strtoupper(trim($_POST['rfc']));
    $razon_social_input = trim($_POST['razon_social']);
    $regimen_input = $_POST['regimen'];
    $uso_input = $_POST['uso'];
    $cp_input = trim($_POST['cp']);

    try {
        // Query alineado exactamente a la estructura física de tu tabla facturacion
        $stmt_save = $pdo->prepare("
            INSERT INTO facturacion (pedido_id, rfc, razon_social, regimen_fiscal, uso_cfdi, cp_fiscal, monto_total) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt_save->execute([
            $pedido_id,
            $rfc_input,
            $razon_social_input,
            $regimen_input,
            $uso_input,
            $cp_input,
            $pedido['total'] // Asegúrate de que el campo 'total' exista en tu tabla de pedidos
        ]);
        
        // Redirección limpia para refrescar la vista y evitar reenvíos de formulario
        header("Location: facturar.php?id=$pedido_id&msg=success");
        exit;
        
    } catch(PDOException $e) {
        die("Error al insertar los datos en la tabla facturacion: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facturación - Pedido #<?php echo $pedido_id; ?></title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f8fafc; font-family: sans-serif; margin: 0; }
        .fac-container { max-width: 800px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .grid-form { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .grid-form { grid-template-columns: 1fr; } }
        .campo { display: flex; flex-direction: column; }
        label { font-weight: bold; margin-bottom: 6px; color: #4a5568; font-size: 0.9rem; }
        input, select { padding: 11px; border: 1px solid #cbd5e0; border-radius: 8px; font-size: 0.95rem; transition: border 0.2s; }
        input:focus, select:focus { border-color: #1a365d; outline: none; background: #f7fafc; }
        .resumen-pedido { background: #f8fafc; padding: 20px; border-radius: 10px; margin-bottom: 25px; border-left: 5px solid #1a365d; }
        .btn-facturar { background: #1a365d; color: white; padding: 14px; border: none; border-radius: 8px; cursor: pointer; width: 100%; font-weight: bold; font-size: 1rem; margin-top: 20px; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.2s; }
        .btn-facturar:hover { background: #2a4365; }
        .success-msg { background: #c6f6d5; color: #22543d; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; border: 1px solid #98dbad; }
        .volver-btn { display: inline-flex; align-items: center; gap: 5px; color: #4a5568; text-decoration: none; margin-bottom: 20px; font-weight: 500; }
        .volver-btn:hover { color: #1a365d; }
    </style>
</head>
<body>

    <button class="menu-toggle" onclick="toggleMenu()"><i class="fas fa-bars"></i></button>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="fac-container">
            <a href="pedidos.php" class="volver-btn"><i class="fas fa-arrow-left"></i> Volver a Pedidos</a>
            <h1 style="color: #1a365d; margin-top: 0; font-size: 1.75rem;"><i class="fas fa-file-invoice-dollar"></i> Módulo de Facturación</h1>

            <?php if(isset($_GET['msg'])): ?>
                <div class="success-msg">
                    <i class="fas fa-check-circle"></i> Datos fiscales guardados con éxito en la tabla. Listo para timbrado XML/PDF.
                </div>
            <?php endif; ?>

            <div class="resumen-pedido">
                <strong style="color: #1a365d;">Resumen del Pedido #<?php echo $pedido_id; ?></strong><br><br>
                <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <span style="color: #718096; font-size: 0.9rem;">Cliente:</span><br>
                        <strong><?php echo htmlspecialchars($pedido['cliente_nombre'] ?? $pedido['email'] ?? 'Público en General'); ?></strong>
                    </div>
                    <div>
                        <span style="color: #718096; font-size: 0.9rem;">Monto a Facturar:</span><br>
                        <span style="font-size: 1.3rem; font-weight: bold; color: #2d3748;">$<?php echo number_format($pedido['total'], 2); ?></span>
                    </div>
                </div>
            </div>

            <?php if ($factura_existente): ?>
                <div style="background: #edf2f7; padding: 25px; border-radius: 10px; border: 1px solid #cbd5e0;">
                    <h3 style="margin-top: 0; color: #2d3748;"><i class="fas fa-receipt text-success"></i> Datos de Facturación Registrados</h3>
                    <hr style="border: 0; border-top: 1px solid #cbd5e0; margin: 15px 0;">
                    <p style="margin: 8px 0;"><strong>RFC:</strong> <?php echo htmlspecialchars($factura_existente['rfc']); ?></p>
                    <p style="margin: 8px 0;"><strong>Razón Social:</strong> <?php echo htmlspecialchars($factura_existente['razon_social']); ?></p>
                    <p style="margin: 8px 0;"><strong>Régimen Fiscal:</strong> <?php echo htmlspecialchars($factura_existente['regimen_fiscal']); ?></p>
                    <p style="margin: 8px 0;"><strong>Uso de CFDI:</strong> <?php echo htmlspecialchars($factura_existente['uso_cfdi']); ?></p>
                    <p style="margin: 8px 0;"><strong>Código Postal Fiscal:</strong> <?php echo htmlspecialchars($factura_existente['cp_fiscal']); ?></p>
                    
                    <button onclick="window.print()" class="btn-facturar" style="background: #4a5568;">
                        <i class="fas fa-print"></i> Imprimir Constancia de Datos
                    </button>
                </div>
            <?php else: ?>
                <form method="POST">
                    <div class="grid-form">
                        <div class="campo">
                            <label>RFC</label>
                            <input type="text" name="rfc" required placeholder="XAXX010101000" maxlength="13" style="text-transform: uppercase;">
                        </div>
                        <div class="campo">
                            <label>Razón Social</label>
                            <input type="text" name="razon_social" required placeholder="Nombre completo o Empresa">
                        </div>
                        <div class="campo">
                            <label>Régimen Fiscal</label>
                            <select name="regimen" required>
                                <option value="601">601 - General de Ley Personas Morales</option>
                                <option value="612">612 - Personas Físicas con Actividades Empresariales</option>
                                <option value="626">626 - Régimen Simplificado de Confianza (RESICO)</option>
                                <option value="605">605 - Sueldos y Salarios / Asimilados</option>
                                <option value="616">616 - Sin obligaciones fiscales</option>
                            </select>
                        </div>
                        <div class="campo">
                            <label>Uso de CFDI</label>
                            <select name="uso" required>
                                <option value="G03">G03 - Gastos en general</option>
                                <option value="CP01">CP01 - Pagos</option>
                                <option value="S01">S01 - Sin efectos fiscales</option>
                            </select>
                        </div>
                        <div class="campo">
                            <label>Código Postal Fiscal</label>
                            <input type="text" name="cp" required placeholder="44100" maxlength="5">
                        </div>
                    </div>
                    <button type="submit" class="btn-facturar">
                        <i class="fas fa-save"></i> Guardar y Vincular Datos Fiscales
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleMenu() {
            const sidebar = document.querySelector('.sidebar');
            if(sidebar) sidebar.classList.toggle('active');
        }
    </script>
</body>
</html>