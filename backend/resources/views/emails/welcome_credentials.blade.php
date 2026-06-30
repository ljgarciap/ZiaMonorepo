<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bienvenido a ZIA Carbon Control</title>
</head>
<body style="font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0;">
    <div style="max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
        <div style="background: #004d40; padding: 32px 40px;">
            <h1 style="color: #fff; margin: 0; font-size: 22px; font-weight: 600;">ZIA Carbon Control</h1>
            <p style="color: rgba(255,255,255,0.7); margin: 4px 0 0 0; font-size: 13px;">Plataforma de Huella de Carbono Empresarial</p>
        </div>

        <div style="padding: 40px;">
            <p style="color: #333; font-size: 15px; margin-top: 0;">Hola <strong>{{ $user->name }}</strong>,</p>
            <p style="color: #555; font-size: 14px;">Tu cuenta ha sido creada exitosamente en ZIA Carbon Control. A continuación encuentras tus credenciales de acceso:</p>

            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 20px; margin: 24px 0;">
                <p style="margin: 0 0 12px 0; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #166534; font-weight: 700;">Credenciales de Acceso</p>
                <table style="border-collapse: collapse; width: 100%;">
                    <tr>
                        <td style="padding: 6px 0; font-size: 13px; color: #555; width: 100px;"><strong>Email:</strong></td>
                        <td style="padding: 6px 0; font-size: 13px; color: #333;">{{ $user->email }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; font-size: 13px; color: #555;"><strong>Contraseña:</strong></td>
                        <td style="padding: 6px 0;">
                            <code style="background: #dcfce7; color: #166534; padding: 3px 8px; border-radius: 4px; font-size: 14px; font-weight: 600; letter-spacing: 0.5px;">{{ $plainPassword }}</code>
                        </td>
                    </tr>
                </table>
            </div>

            <p style="color: #d97706; font-size: 13px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 12px 16px;">
                <strong>Importante:</strong> Por seguridad, te recomendamos cambiar tu contraseña al ingresar por primera vez al sistema.
            </p>

            <div style="text-align: center; margin-top: 32px;">
                <a href="{{ config('app.url') }}"
                   style="display: inline-block; background: #004d40; color: #fff; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px;">
                    Ingresar a ZIA Carbon Control
                </a>
            </div>
        </div>

        <div style="background: #f9fafb; border-top: 1px solid #e5e7eb; padding: 20px 40px;">
            <p style="color: #9ca3af; font-size: 11px; margin: 0; text-align: center;">
                Este correo fue enviado automáticamente por ZIA Carbon Control.<br>
                Si no esperabas este mensaje, por favor contacta a tu administrador.
            </p>
        </div>
    </div>
</body>
</html>
