<?php

namespace App\Infrastructure\Mail;

/**
 * Plantillas HTML corporativas para correos transaccionales de UbikGo
 * utilizando la paleta oficial de marca:
 * - Púrpura UbikGo: #4E4293
 * - Naranja UbikGo: #F38218
 * - Gris Oscuro:    #1E1E26
 */
class UbikEmailTemplates
{
    private const PURPLE = '#4E4293';
    private const ORANGE = '#F38218';
    private const DARK   = '#1E1E26';

    private static function resolveLogoUrl(): string
    {
        $appUrl = rtrim((string)($_ENV['APP_URL'] ?? 'http://localhost:8088'), '/');
        return $appUrl . '/assets/contracts/ubikgo-logo.png';
    }

    /**
     * Plantilla de Bienvenida cuando una Vendedora registra un nuevo Cliente
     * con su correo y clave generada aleatoriamente para iniciar sesión en la App.
     */
    public static function sellerClientWelcome(
        string $fullName,
        string $email,
        string $plainPassword,
        string $sellerName = 'Equipo Comercial UbikGo'
    ): string {
        $safeName   = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
        $safeEmail  = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safePass   = htmlspecialchars($plainPassword, ENT_QUOTES, 'UTF-8');
        $safeSeller = htmlspecialchars($sellerName, ENT_QUOTES, 'UTF-8');
        $logoUrl    = htmlspecialchars(self::resolveLogoUrl(), ENT_QUOTES, 'UTF-8');
        $year       = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bienvenido a UbikGo</title>
</head>
<body style="margin:0; padding:0; background-color:#F4F3F9; font-family:'Helvetica Neue', Arial, sans-serif; color:#1E1E26;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F4F3F9; padding:32px 12px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px; background-color:#FFFFFF; border-radius:14px; overflow:hidden; box-shadow:0 6px 24px rgba(78,66,147,0.12); border:2px solid #4E4293;">
                    <!-- Cabecera con Logo y Franja Naranja -->
                    <tr>
                        <td align="center" style="background-color:#FFFFFF; padding:26px 24px 18px 24px; border-bottom:4px solid #F38218;">
                            <img src="{$logoUrl}" alt="UbikGo Asistencia Vial" style="max-width:190px; height:auto; display:block; margin:0 auto 10px auto;" />
                            <span style="display:inline-block; background-color:#4E4293; color:#FFFFFF; font-size:11px; font-weight:bold; letter-spacing:1.2px; padding:4px 12px; border-radius:20px;">
                                @UBIKGO &bull; #SomosUBIK
                            </span>
                        </td>
                    </tr>

                    <!-- Cuerpo Principal -->
                    <tr>
                        <td style="padding:30px 28px 24px 28px;">
                            <h2 style="margin:0 0 12px 0; color:#4E4293; font-size:22px; font-weight:800;">
                                ¡Bienvenido a UbikGo, {$safeName}!
                            </h2>
                            <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6; color:#333340;">
                                Tu cuenta de cliente ha sido creada exitosamente por nuestro asesor comercial (<strong>{$safeSeller}</strong>). Ya puedes iniciar sesión en la aplicación móvil y plataforma web de <strong>UbikGo</strong> para consultar tus contratos de grúa, descargar tu póliza digital y solicitar asistencia vial.
                            </p>

                            <!-- Tarjeta de Credenciales -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F8F7FC; border-left:5px solid #F38218; border-radius:8px; margin:22px 0;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <p style="margin:0 0 8px 0; font-size:12px; font-weight:bold; text-transform:uppercase; letter-spacing:0.8px; color:#4E4293;">
                                            Tus credenciales de acceso
                                        </p>
                                        <p style="margin:6px 0; font-size:15px; color:#1E1E26;">
                                            <strong>Correo electrónico:</strong> <span style="color:#4E4293;">{$safeEmail}</span>
                                        </p>
                                        <p style="margin:6px 0 0 0; font-size:15px; color:#1E1E26;">
                                            <strong>Contraseña temporal:</strong>
                                            <span style="display:inline-block; background-color:#4E4293; color:#FFFFFF; font-family:monospace; font-size:16px; font-weight:bold; padding:4px 10px; border-radius:5px; letter-spacing:1px;">{$safePass}</span>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 12px 0; font-size:13.5px; line-height:1.5; color:#555566;">
                                Por tu seguridad, te recomendamos cambiar esta contraseña desde la opción <em>Perfil / Recuperar Contraseña</em> una vez ingreses a la aplicación.
                            </p>
                        </td>
                    </tr>

                    <!-- Pie de Página -->
                    <tr>
                        <td align="center" style="background-color:#4E4293; padding:16px 24px; color:#FFFFFF; font-size:12px;">
                            <strong>UBIK SERVICE C.A. &bull; Plan de Grúa UbikGo</strong><br/>
                            <span style="color:#E0DCF5;">&copy; {$year} UbikGo. Todos los derechos reservados.</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Plantilla rediseñada para el Código OTP de Recuperación de Contraseña
     * con los colores y el logo oficial de UbikGo.
     */
    public static function passwordResetOtp(string $fullName, string $otp): string
    {
        $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
        $safeOtp  = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
        $logoUrl  = htmlspecialchars(self::resolveLogoUrl(), ENT_QUOTES, 'UTF-8');
        $year     = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Código de Recuperación - UbikGo</title>
</head>
<body style="margin:0; padding:0; background-color:#F4F3F9; font-family:'Helvetica Neue', Arial, sans-serif; color:#1E1E26;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F4F3F9; padding:32px 12px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px; background-color:#FFFFFF; border-radius:14px; overflow:hidden; box-shadow:0 6px 24px rgba(78,66,147,0.12); border:2px solid #4E4293;">
                    <!-- Cabecera con Logo y Franja Naranja -->
                    <tr>
                        <td align="center" style="background-color:#FFFFFF; padding:24px 24px 16px 24px; border-bottom:4px solid #F38218;">
                            <img src="{$logoUrl}" alt="UbikGo Asistencia Vial" style="max-width:180px; height:auto; display:block; margin:0 auto 10px auto;" />
                            <span style="display:inline-block; background-color:#4E4293; color:#FFFFFF; font-size:11px; font-weight:bold; letter-spacing:1.2px; padding:4px 12px; border-radius:20px;">
                                @UBIKGO &bull; #SomosUBIK
                            </span>
                        </td>
                    </tr>

                    <!-- Cuerpo Principal -->
                    <tr>
                        <td style="padding:28px 28px 22px 28px;">
                            <h2 style="margin:0 0 10px 0; color:#4E4293; font-size:20px; font-weight:800;">
                                Recuperación de Contraseña
                            </h2>
                            <p style="margin:0 0 14px 0; font-size:15px; line-height:1.5; color:#333340;">
                                Hola, <strong>{$safeName}</strong>:
                            </p>
                            <p style="margin:0 0 18px 0; font-size:14.5px; line-height:1.5; color:#333340;">
                                Recibimos una solicitud para restablecer la contraseña de tu cuenta en <strong>UbikGo</strong>. Ingresa el siguiente código de verificación en la aplicación:
                            </p>

                            <!-- Caja del Código OTP -->
                            <div style="background-color:#F8F7FC; border:2px dashed #F38218; text-align:center; padding:18px 14px; border-radius:10px; margin:20px 0;">
                                <span style="font-size:34px; font-weight:800; letter-spacing:8px; color:#4E4293; font-family:monospace;">{$safeOtp}</span>
                            </div>

                            <p style="margin:0; font-size:13px; line-height:1.5; color:#666677;">
                                Este código expirará en <strong>15 minutos</strong>. Si no realizaste esta solicitud, puedes ignorar este mensaje de forma segura.
                            </p>
                        </td>
                    </tr>

                    <!-- Pie de Página -->
                    <tr>
                        <td align="center" style="background-color:#4E4293; padding:14px 24px; color:#FFFFFF; font-size:12px;">
                            <strong>UBIK SERVICE C.A. &bull; Asistencia Vial</strong><br/>
                            <span style="color:#E0DCF5;">&copy; {$year} UbikGo. Todos los derechos reservados.</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
