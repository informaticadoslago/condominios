<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
</head>
<body style="margin:0; padding:0; background:#f3f4f6; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:500px; background:#ffffff; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="padding:24px; color:#111827; font-size:15px; line-height:1.5;">
                            <p>{{ __('Hola :nombre:', ['nombre' => $nombre]) }}</p>

                            <p>{{ __('Se ha dado de alta este correo como tuyo en :sistema. Para confirmar que es correcto, pulsa el botón de abajo.', ['sistema' => $sistema]) }}</p>

                            <p style="text-align:center; margin:32px 0;">
                                <a href="{{ $enlace }}"
                                    style="background:#16a34a; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:6px; display:inline-block; font-weight:bold;">
                                    {{ __('Confirmar mi correo') }}
                                </a>
                            </p>

                            <p style="color:#6b7280; font-size:13px;">
                                {{ __('Si el botón no funciona, copia y pega este enlace en tu navegador:') }}<br>
                                <a href="{{ $enlace }}" style="color:#2563eb; word-break:break-all;">{{ $enlace }}</a>
                            </p>

                            <p style="color:#6b7280; font-size:12px;">
                                {{ __('Este es un correo automático: por favor no respondas a esta dirección.') }}
                                {{ __('Para cualquier consulta, contacta con el administrador del sistema.') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
