<?php
session_start();

function verificarSesion() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function verificarRol($roles_permitidos = ['admin', 'editor']) {
    if (!in_array($_SESSION['admin_rol'], $roles_permitidos)) {
        header('Location: index.php?error=permiso');
        exit;
    }
}

function tienePermiso($rol_requerido = 'editor') {
    $roles_jerarquia = [
        'visitante' => 1,
        'editor' => 2,
        'admin' => 3
    ];
    
    $usuario_rol = $_SESSION['admin_rol'] ?? 'visitante';
    
    return $roles_jerarquia[$usuario_rol] >= $roles_jerarquia[$rol_requerido];
}
?>