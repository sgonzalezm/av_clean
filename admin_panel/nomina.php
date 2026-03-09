<?php
require_once '../includes/session.php';
require_once '../includes/conexion.php';
verificarSesion();

// 1. Obtener resumen rápido para los KPI
$totalEmpleados = $pdo->query("SELECT COUNT(*) FROM empleados WHERE estatus = 'Activo'")->fetchColumn();
$gastoMensualEst = $pdo->query("SELECT SUM(sueldo_diario * 30) FROM empleados WHERE estatus = 'Activo'")->fetchColumn();

// 2. Obtener lista de empleados para la tabla/modal
$stmt = $pdo->query("SELECT * FROM empleados ORDER BY nombre ASC");
$empleados = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Gestión de Nómina | AHD Clean</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .nomina-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(3px); }
        .modal-content { background: white; width: 450px; margin: 5% auto; padding: 30px; border-radius: 15px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .close-modal { float: right; cursor: pointer; font-size: 1.5rem; color: #a0aec0; }
        .status-pill { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; }
        .status-activo { background: #c6f6d5; color: #22543d; }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="header" style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h1><i class="fas fa-users-cog"></i> Panel de Nómina</h1>
                <p style="color: #718096;">Administración de personal, sueldos y periodos de pago.</p>
            </div>
            <button class="btn-guardar" onclick="openModal('modalNuevoEmpleado')">
                <i class="fas fa-user-plus"></i> Nuevo Empleado
            </button>
        </div>

        <div class="nomina-grid">
            <div class="card" style="border-left: 5px solid #4299e1;">
                <small style="color:#718096; font-weight:bold; text-transform:uppercase;">Plantilla Activa</small>
                <h2 style="margin:10px 0; font-size:2rem;"><?php echo $totalEmpleados; ?> Colaboradores</h2>
            </div>
            <div class="card" style="border-left: 5px solid #48bb78;">
                <small style="color:#718096; font-weight:bold; text-transform:uppercase;">Costo Mensual Estimado</small>
                <h2 style="margin:10px 0; font-size:2rem;">$<?php echo number_format($gastoMensualEst, 2); ?></h2>
            </div>
            <a href="periodos_pago.php" style="text-decoration:none;">
                <div class="card clickable" style="border-left: 5px solid #ed8936; background:#fffaf0;">
                    <small style="color:#dd6b20; font-weight:bold;">ACCESO RÁPIDO</small>
                    <h3 style="margin:10px 0; color:#2d3748;">Generar Dispersión <i class="fas fa-chevron-right"></i></h3>
                </div>
            </a>
        </div>

        <div class="card" style="background:white; border-radius:12px; padding:20px;">
            <h3>Directorio de Personal</h3>
            <table style="width:100%; border-collapse: collapse; margin-top:15px;">
                <thead>
                    <tr style="text-align:left; color:#a0aec0; font-size:0.85rem; border-bottom:1px solid #edf2f7;">
                        <th style="padding:12px;">Nombre</th>
                        <th>Puesto</th>
                        <th>Sueldo Diario</th>
                        <th>Estatus</th>
                        <th style="text-align:right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($empleados as $e): ?>
                    <tr style="border-bottom:1px solid #f7fafc;">
                        <td style="padding:15px;"><strong><?php echo $e['nombre']; ?></strong></td>
                        <td><?php echo $e['puesto']; ?></td>
                        <td>$<?php echo number_format($e['sueldo_diario'], 2); ?></td>
                        <td><span class="status-pill status-activo"><?php echo $e['estatus']; ?></span></td>
                        <td style="text-align:right;">
                            <button class="btn-small" onclick="editEmpleado(<?php echo htmlspecialchars(json_encode($e)); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="modalNuevoEmpleado" class="modal">
        <div class="modal-content slide-in">
            <span class="close-modal" onclick="closeModal('modalNuevoEmpleado')">&times;</span>
            <h3 id="modalTitle"><i class="fas fa-user-circle"></i> Gestionar Empleado</h3>
            <form action="guardar_empleado.php" method="POST" style="margin-top:20px;">
                <input type="hidden" name="id" id="emp_id">
                
                <div class="form-group">
                    <label>Nombre Completo</label>
                    <input type="text" name="nombre" id="emp_nombre" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Puesto / Cargo</label>
                    <input type="text" name="puesto" id="emp_puesto" class="form-control" placeholder="Ej. Operador de Envasado" required>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Sueldo Diario ($)</label>
                        <input type="number" step="0.01" name="sueldo_diario" id="emp_sueldo" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Fecha de Ingreso</label>
                        <input type="date" name="fecha_ingreso" id="emp_fecha" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>CLABE Interbancaria (18 dígitos)</label>
                    <input type="text" name="clabe" id="emp_clabe" class="form-control" maxlength="18">
                </div>

                <button type="submit" class="btn-guardar" style="width:100%; margin-top:10px;">
                    <i class="fas fa-save"></i> Guardar Colaborador
                </button>
            </form>
        </div>
    </div>

    <script src="../js/admin.js"></script>
    <script>
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

        function editEmpleado(data) {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Editar Empleado';
            document.getElementById('emp_id').value = data.id;
            document.getElementById('emp_nombre').value = data.nombre;
            document.getElementById('emp_puesto').value = data.puesto;
            document.getElementById('emp_sueldo').value = data.sueldo_diario;
            document.getElementById('emp_fecha').value = data.fecha_ingreso;
            document.getElementById('emp_clabe').value = data.clabe_interbancaria || '';
            openModal('modalNuevoEmpleado');
        }

        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>