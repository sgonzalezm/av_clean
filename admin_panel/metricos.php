<?php

// Metricos
// Agregar una sección para visualizar métricas clave como ventas diarias, productos más vendidos, tráfico del sitio, etc.
// Opción para generar gráficos y reportes visuales de estas métricas para facilitar la interpretación
// Opción para exportar estas métricas a CSV o Excel para análisis externo.
// Opción para exportar a PDF para imprimir reportes o resúmenes de métricas.
// Resumen de impacto: Termometro de meta, dias restantes, comision acumulada
// Niveles, recompensas, etc. para incentivar a los vendedores a alcanzar sus objetivos de ventas. Bronce, Plata, Oro. 
// Analiticos de cartera y productos: Mezcla de productos vendidos, clientes en riesgo, clientes frecuentes, proximos a reabastecer
// Calculadora de comisiones: Permitir a los vendedores calcular sus comisiones basadas en las ventas realizadas, con diferentes tasas para distintos productos o niveles de ventas.



?>
<!DOCTYPE html>
<html>
<head>
    <title>Pedidos</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <!-- Botón toggle para móvil -->
    <button class="menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar (menú lateral) -->
    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <div class="header">
            <div>
                <h1>Pedidos</h1>
            </div>
        </div>
    </div>
            
</body>
</html>
