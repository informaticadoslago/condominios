{{--
    Convocatoria de junta a un propietario, con la carta de su inmueble adjunta en PDF.

    El aspecto está en el fichero hermano carta-convocatoria.css; aquí solo va el texto.
    Todo pasa por __(), así que se traduce al idioma con el que se envíe (ver CartaConvocatoria).
--}}
<x-emails.layout plantilla="carta-convocatoria" :titulo="__('Convocatoria de junta')">

    <p class="saludo">{{ $nombre ? __('Hola :nombre:', ['nombre' => $nombre]) : __('Hola:') }}</p>

    <p>{{ __('Te enviamos la convocatoria de la Junta General :tipo de :comunidad. Va adjunta la carta con el orden del día y la autorización para delegar el voto si no puedes asistir.', ['tipo' => $tipo, 'comunidad' => $comunidad]) }}</p>

    <table role="presentation" class="datos" cellpadding="0" cellspacing="0">
        <tr>
            <th>{{ __('Fecha') }}</th>
            <td>{{ $fecha }}</td>
        </tr>
        <tr>
            <th>{{ __('Primera convocatoria') }}</th>
            <td>{{ $horaPrimera }}</td>
        </tr>
        <tr>
            <th>{{ __('Segunda convocatoria') }}</th>
            <td>{{ $horaSegunda }}</td>
        </tr>
        <tr>
            <th>{{ __('Inmueble') }}</th>
            <td>{{ $inmueble }}</td>
        </tr>
    </table>

    <p class="aviso">{{ __('El lugar de la reunión y el orden del día están en la carta adjunta.') }}</p>

    <x-slot name="pie">
        {{ $comunidad }}<br><br>
        {{ __('Este es un correo automático: por favor no respondas a esta dirección.') }}
        @if ($correoContacto)
            {{ __('Para cualquier consulta, escribe a :correo.', ['correo' => $correoContacto]) }}
        @endif
    </x-slot>
</x-emails.layout>
