<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// 1. Obtener empleados activos
$stmt = $pdo->query("SELECT id, nombre, puesto, sueldo_diario FROM empleados WHERE estatus = 'Activo' ORDER BY nombre ASC");
$empleados = $stmt->fetchAll();

// Definir fechas por defecto (Semana actual)
$fecha_inicio = date('Y-m-d', strtotime('monday this week'));
$fecha_fin = date('Y-m-d', strtotime('sunday this week'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Generar Dispersión | AHD Clean</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .tabla-nomina input { width: 100px; text-align: center; border: 1px solid #e2e8f0; border-radius: 4px; padding: 5px; }
        .resumen-pago { background: #2d3748; color: white; padding: 20px; border-radius: 10px; margin-top: 20px; display: flex; justify-content: space-between; align-items: center; }
        .total-grande { font-size: 2rem; font-weight: 900; color: #68d391; }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header">
            <h1><i class="fas fa-money-check-alt"></i> Generar Dispersión de Nómina</h1>
            <a href="nomina.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Volver al Hub</a>
        </div>

        <form action="procesar_pago_nomina.php" method="POST" id="formNomina">
            <div class="card" style="display: flex; gap: 20px; align-items: flex-end; margin-bottom: 20px;">
                <div class="form-group">
                    <label>Inicio del Periodo</label>
                    <input type="date" name="periodo_inicio" class="form-control" value="<?php echo $fecha_inicio; ?>" required>
                </div>
                <div class="form-group">
                    <label>Fin del Periodo</label>
                    <input type="date" name="periodo_fin" class="form-control" value="<?php echo $fecha_fin; ?>" required>
                </div>
                <div class="form-group">
                    <label>Días a Pagar (Normalmente 7 o 15)</label>
                    <input type="number" id="dias_global" class="form-control" value="7" style="width:80px;">
                </div>
            </div>

            <div class="card" style="padding: 0; overflow: hidden;">
                <table class="tabla-nomina" style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #f7fafc; border-bottom: 2px solid #edf2f7;">
                        <tr>
                            <th style="padding: 15px; text-align: left;">Colaborador</th>
                            <th>Sueldo Diario</th>
                            <th>Días Lab.</th>
                            <th>Bonos/Comis. (+)</th>
                            <th>Descuentos (-)</th>
                            <th style="text-align: right; padding-right: 20px;">Neto a Pagar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($empleados as $index => $e): ?>
                        <tr class="fila-empleado" style="border-bottom: 1px solid #edf2f7;">
                            <td style="padding: 15px;">
                                <strong><?php echo $e['nombre']; ?></strong><br>
                                <small style="color:#a0aec0;"><?php echo $e['puesto']; ?></small>
                                <input type="hidden" name="pagos[<?php echo $index; ?>][empleado_id]" value="<?php echo $e['id']; ?>">
                            </td>
                            <td style="text-align: center;">
                                $<span class="val-diario"><?php echo number_format($e['sueldo_diario'], 2, '.', ''); ?></span>
                            </td>
                            <td style="text-align: center;">
                                <input type="number" name="pagos[<?php echo $index; ?>][dias]" class="in-dias" value="7" step="1" min="0">
                            </td>
                            <td style="text-align: center;">
                                <input type="number" name="pagos[<?php echo $index; ?>][bonos]" class="in-bonos" value="0" step="0.01">
                            </td>
                            <td style="text-align: center;">
                                <input type="number" name="pagos[<?php echo $index; ?>][descuentos]" class="in-desc" value="0" step="0.01">
                            </td>
                            <td style="text-align: right; padding-right: 20px; font-weight: bold; color: #2d3748;">
                                $<span class="val-neto">0.00</span>
                                <input type="hidden" name="pagos[<?php echo $index; ?>][total_final]" class="in-neto-hidden">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="resumen-pago">
                <div>
                    <i class="fas fa-info-circle"></i> Verifique los montos antes de dispersar.<br>
                    <small>Se generará un registro de egreso automático para contabilidad.</small>
                </div>
                <div style="text-align: right;">
                    <span>Total de la Nómina:</span><br>
                    <span class="total-grande" id="granTotalNomina">$0.00</span>
                </div>
            </div>

            <button type="submit" class="btn-guardar" style="width: 100%; height: 60px; margin-top: 20px; font-size: 1.2rem;">
                <i class="fas fa-file-signature"></i> Autorizar y Dispersar Nómina
            </button>
        </form>
    </div>

    <script>
        const form = document.getElementById('formNomina');
        const diasGlobalInput = document.getElementById('dias_global');

        function calcularNomina() {
            let totalGeneral = 0;
            document.querySelectorAll('.fila-empleado').forEach(fila => {
                const sueldoDiario = parseFloat(fila.querySelector('.val-diario').innerText);
                const dias = parseFloat(fila.querySelector('.in-dias').value) || 0;
                const bonos = parseFloat(fila.querySelector('.in-bonos').value) || 0;
                const desc = parseFloat(fila.querySelector('.in-desc').value) || 0;

                const neto = (sueldoDiario * dias) + bonos - desc;
                
                fila.querySelector('.val-neto').innerText = neto.toFixed(2);
                fila.querySelector('.in-neto-hidden').value = neto.toFixed(2);
                totalGeneral += neto;
            });
            document.getElementById('granTotalNomina').innerText = '$' + totalGeneral.toLocaleString('es-MX', {minimumFractionDigits: 2});
        }

        // Eventos para recalcular
        form.addEventListener('input', calcularNomina);
        
        // Sincronizar días globalmente si se cambia el input de arriba
        diasGlobalInput.addEventListener('input', () => {
            document.querySelectorAll('.in-dias').forEach(input => {
                input.value = diasGlobalInput.value;
            });
            calcularNomina();
        });

        // Cálculo inicial
        window.onload = calcularNomina;

        function toggleSidebar() { document.querySelector('.sidebar').classList.toggle('active'); }
    </script>
</body>
</html>