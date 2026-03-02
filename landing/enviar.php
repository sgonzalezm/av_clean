<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/Exception.php';
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mail = new PHPMailer(true);

    try {
        // --- Configuración del Servidor SMTP ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com'; // Usualmente mail.tudominio.com
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ventas@ahd-clean.com'; 
        $mail->Password   = '3Lk28$.n37'; // La clave de tu correo corporativo
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // O ENCRYPTION_STARTTLS
        $mail->Port       = 465; // O 587 si usas STARTTLS

        // --- Destinatarios ---
        $mail->setFrom('ventas@ahd-clean.com', 'Web AHD Clean');
        $mail->addAddress('ventas@ahd-clean.com'); // A dónde te llega el aviso
        $mail->addReplyTo($_POST['email'], $_POST['name']); // Para responderle al cliente directo

        // --- Contenido del correo ---
        $mail->isHTML(true);
        $mail->Subject = 'Nuevo contacto: ' . $_POST['interest'];
        
        $cuerpo = "<h3>Nuevo mensaje desde la Landing Page</h3>";
        $cuerpo .= "<p><b>Nombre:</b> " . $_POST['name'] . "</p>";
        $cuerpo .= "<p><b>Email:</b> " . $_POST['email'] . "</p>";
        $cuerpo .= "<p><b>Teléfono:</b> " . $_POST['phone'] . "</p>";
        $cuerpo .= "<p><b>Interés:</b> " . $_POST['interest'] . "</p>";
        $cuerpo .= "<p><b>Mensaje:</b><br>" . nl2br($_POST['message']) . "</p>";

        $mail->Body = $cuerpo;

        $mail->send();
        echo "éxito";
        
    } catch (Exception $e) {
        echo "Hubo un error al enviar: {$mail->ErrorInfo}";
    }
}