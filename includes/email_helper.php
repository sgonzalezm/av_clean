<?php
// Usamos los namespaces necesarios de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Cargamos los archivos indispensables que extrajiste de la carpeta src/
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

/**
 * Función modular para enviar notificaciones automáticas de ventas
 * * @param string $correoDestino   Email del cliente
 * @param string $nombreCliente   Nombre completo del cliente
 * @param array  $detallesVenta   Array con ID de pedido, productos, totales, etc.
 * @param bool   $esVentaInterna  Si es true, cambia los textos para avisar al administrador
 */
function enviarCorreoNotificacionVenta($correoDestino, $nombreCliente, $detallesVenta, $esVentaInterna = false) {
    $mail = new PHPMailer(true);

    try {
        // --- CONFIGURACIÓN DEL SERVIDOR SMTP ---
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Activa esto solo si necesitas depurar errores en pantalla
        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com';             // Servidor SMTP de tu hosting
        $mail->SMTPAuth   = true;
        $mail->Username   = 'no-reply@ahd-clean.com';         // Tu cuenta de envío automático
        $mail->Password   = '3Lk28$.n37';             // Cambia esto por la contraseña real de cPanel
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;      // Puerto seguro SSL standard
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';                          // Asegura que se lean acentos y la 'ñ'

        // --- DESTINATARIOS Y REMITENTE ---
        $mail->setFrom('no-reply@ahd-clean.com', 'AHD Clean - Notificaciones');
        
        if ($esVentaInterna) {
            // Si es interna (POS), se le manda una copia al correo maestro de la empresa
            $mail->addAddress('ventas@ahd-clean.com', 'Administración AHD Clean');
            $mail->Subject = "Alerta de Venta Interna POS - Folio #" . $detallesVenta['pedido_id'];
        } else {
            // Si es externa (Tienda Online), va directo al cliente y una copia oculta a ventas para que estén enterados
            $mail->addAddress($correoDestino, $nombreCliente);
            $mail->addBCC('ventas@ahd-clean.com', 'Monitoreo de Ventas');
            $mail->Subject = "Confirmación de Pedido #" . $detallesVenta['pedido_id'] . " | AHD Clean";
        }

        // --- CONSTRUCCIÓN DINÁMICA DE LA TABLA DE PRODUCTOS (HTML) ---
        $tablaProductosHtml = '';
        foreach ($detallesVenta['productos'] as $prod) {
            $tablaProductosHtml .= "
            <tr>
                <td style='padding: 12px; border-bottom: 1px solid #e2e8f0; color: #4a5568;'>{$prod['nombre']}</td>
                <td style='padding: 12px; border-bottom: 1px solid #e2e8f0; color: #4a5568; text-align: center;'>{$prod['cantidad']}</td>
                <td style='padding: 12px; border-bottom: 1px solid #e2e8f0; color: #1a202c; font-weight: bold; text-align: right;'>$" . number_format($prod['subtotal'], 2) . "</td>
            </tr>";
        }

        // --- CUERPO DEL CORREO CON DISEÑO CORPORATIVO ---
        $mail->isHTML(true);
        
        // Mensaje de saludo dinámico según el canal de venta
        $saludo = $esVentaInterna 
            ? "Se ha registrado un nuevo movimiento en el punto de venta (POS)." 
            : "¡Hola, <strong>" . htmlspecialchars($nombreCliente) . "</strong>! Muchas gracias por tu compra. Hemos recibido tu pedido correctamente y ya está en fila de procesamiento.";

        $mail->Body = "
        <div style='background-color: #f7fafc; padding: 40px 10px; font-family: sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden;'>
                
                <div style='background-color: #1a365d; padding: 30px; text-align: center;'>
                    <h1 style='color: #ffffff; margin: 0; font-size: 24px; letter-spacing: 1px;'>AHD CLEAN</h1>
                    <p style='color: #90cdf4; margin: 5px 0 0 0; font-size: 14px;'>Fábrica de Productos de Limpieza</p>
                </div>

                <div style='padding: 30px;'>
                    <h2 style='color: #2d3748; margin-top: 0;'>Detalles del Pedido #{$detallesVenta['pedido_id']}</h2>
                    <p style='color: #4a5568; line-height: 1.6; font-size: 15px;'>{$saludo}</p>
                    
                    <table style='width: 100%; border-collapse: collapse; margin-top: 25px;'>
                        <thead>
                            <tr style='background-color: #edf2f7;'>
                                <th style='padding: 12px; text-align: left; color: #4a5568; font-size: 14px;'>Producto</th>
                                <th style='padding: 12px; text-align: center; color: #4a5568; font-size: 14px;'>Cant.</th>
                                <th style='padding: 12px; text-align: right; color: #4a5568; font-size: 14px;'>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$tablaProductosHtml}
                        </tbody>
                    </table>

                    <div style='margin-top: 20px; border-top: 2px solid #edf2f7; padding-top: 15px; text-align: right;'>
                        <p style='margin: 5px 0; color: #718096; font-size: 14px;'>Subtotal bruto: <span style='color: #2d3748;'>$" . number_format($detallesVenta['subtotal'], 2) . "</span></p>
                        " . ($detallesVenta['descuento'] > 0 ? "<p style='margin: 5px 0; color: #38a169; font-size: 14px; font-weight: bold;'>Descuento aplicado: -$" . number_format($detallesVenta['descuento'], 2) . "</p>" : "") . "
                        <p style='margin: 5px 0; color: #1a365d; font-size: 20px; font-weight: 800;'>Total Final: $" . number_format($detallesVenta['total'], 2) . " M.N.</p>
                    </div>

                    <div style='margin-top: 30px; background-color: #ebf8ff; border-left: 4px solid #3182ce; padding: 15px; border-radius: 4px;'>
                        <p style='margin: 0; color: #2b6cb0; font-size: 14px;'><strong>Método de Control:</strong> {$detallesVenta['metodo_pago']}</p>
                    </div>
                </div>

                <div style='background-color: #f7fafc; padding: 20px; text-align: center; border-top: 1px solid #edf2f7;'>
                    <p style='margin: 0; color: #a0aec0; font-size: 12px;'>Este es un correo automático generado por el sistema de AHD Clean. Por favor no respondas directamente a este mensaje.</p>
                    <p style='margin: 5px 0 0 0; color: #718096; font-size: 12px;'> Guadalajara, Jalisco, México </p>
                </div>

            </div>
        </div>";

        // Intentamos realizar el envío real a través de los sockets SMTP del servidor
        $mail->send();
        return true;

    } catch (Exception $e) {
        // Para no romper la experiencia del cliente si falla el correo, lo registramos en el log del servidor
        error_log("Error enviando correo en AHD Clean (Pedido #{$detallesVenta['pedido_id']}): " . $mail->ErrorInfo);
        return false;
    }
}