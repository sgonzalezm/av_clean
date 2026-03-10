<?php
session_start();

// Destruimos solo las variables del cliente para no borrar el carrito por error 
// (a menos que quieras que el carrito también se limpie al salir)
unset($_SESSION['cliente_id']);
unset($_SESSION['cliente_nombre']);
unset($_SESSION['cliente_email']);
unset($_SESSION['tipo_cliente']);

// Si quieres cerrar todo por completo, usa session_destroy();
// session_destroy();

header("Location: index.php");
exit;