@props(['plantilla', 'titulo' => null])

@php
    // El CSS de cada correo vive en un fichero hermano de su plantilla, con el mismo
    // nombre y extensión .css, y se incrusta aquí en un <style>. En un correo no se
    // puede enlazar una hoja de estilos: el cliente no la descargaría.
    $ficheroCss = resource_path('views/emails/'.$plantilla.'.css');
    $css        = is_file($ficheroCss) ? file_get_contents($ficheroCss) : '';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo ?? config('mail.from.name') }}</title>
    <style>
{!! $css !!}
    </style>
</head>
<body>
    <table role="presentation" class="marco" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table role="presentation" class="tarjeta" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="contenido">
                            {{ $slot }}
                        </td>
                    </tr>
                </table>

                @isset($pie)
                    <table role="presentation" class="tarjeta" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td class="pie">{{ $pie }}</td>
                        </tr>
                    </table>
                @endisset
            </td>
        </tr>
    </table>
</body>
</html>
