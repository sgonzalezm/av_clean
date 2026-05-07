<?php
include 'includes/conexion.php';

try {
    // 1. Limpiamos cualquier vinculación previa para empezar de cero y evitar errores
    $pdo->query("UPDATE productos SET id_padre = NULL");

    // 2. Obtenemos todos los productos ordenados por nombre
    // Esto es vital para que procesemos primero los que podrían ser padres
    $stmt = $pdo->query("SELECT id, nombre FROM productos ORDER BY nombre ASC");
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $vinculados = 0;
    $padres_creados = 0;

    foreach ($productos as $p) {
        $nombre_completo = $p['nombre'];
        
        // Usamos una expresión regular para extraer el nombre base
        // Esto quita cualquier cosa que esté entre paréntesis al final
        // Ejemplo: "Detergente Azul (4L)" -> "Detergente Azul"
        $nombre_base = preg_replace('/\s*\([^)]*\)$/', '', $nombre_completo);
        $nombre_base = trim($nombre_base);

        // Si el nombre base es distinto al nombre completo, significa que es un HIJO
        if ($nombre_base !== $nombre_completo) {
            
            // Buscamos al PADRE (el que se llama exactamente como el nombre base)
            $stmt_padre = $pdo->prepare("SELECT id FROM productos WHERE nombre = ? AND id != ? LIMIT 1");
            $stmt_padre->execute([$nombre_base, $p['id']]);
            $padre = $stmt_padre->fetch();

            if ($padre) {
                // Si encontramos al padre, vinculamos al hijo
                $stmt_update = $pdo->prepare("UPDATE productos SET id_padre = ? WHERE id = ?");
                $stmt_update->execute([$padre['id'], $p['id']]);
                
                echo "✅ Vinculado: <b>$nombre_completo</b> -> es hijo de ID: {$padre['id']}<br>";
                $vinculados++;
            } else {
                echo "⚠️ No se encontró un producto padre para: <b>$nombre_completo</b> (Se buscó '$nombre_base')<br>";
            }
        } else {
            // Es un producto base (posible padre)
            echo "🏠 Identificado como base/padre: <b>$nombre_completo</b><br>";
            $padres_creados++;
        }
    }

    echo "<br>--- RESUMEN ---<br>";
    echo "Productos Base: $padres_creados<br>";
    echo "Hijos vinculados con éxito: $vinculados<br>";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>