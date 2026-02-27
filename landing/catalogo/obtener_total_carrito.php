<?php
session_start();
echo isset($_SESSION['carrito']) ? array_sum($_SESSION['carrito']) : 0;
?>