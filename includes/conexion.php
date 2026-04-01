<?php
$host = 'localhost';
$dbname = 'u918498641_catalogo_db';
$username = 'u918498641_sgonzalezm';
$password = '3lR10Quefluye$';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

function registrarAuditoria($pdo, $accion, $tabla, $id_reg, $detalle) {
    // Si no hay sesión (ej. error en login), el usuario es 'Sistema'
    $id_user = $_SESSION['id_usuario'] ?? 0;
    $nom_user = $_SESSION['usuario'] ?? 'Sistema';
    
    $sql = "INSERT INTO auditoria (id_usuario, usuario_nombre, accion, tabla_afectada, id_registro_afectado, descripcion) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_user, $nom_user, $accion, $tabla, $id_reg, $detalle]);
}

?>