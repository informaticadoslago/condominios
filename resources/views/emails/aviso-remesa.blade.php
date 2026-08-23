{{--
    Aviso al propietario de que se le va a cargar un recibo domiciliado.

    El aspecto está en el fichero hermano aviso-remesa.css; aquí solo va el texto. Todo
    pasa por __(), así que se traduce al idioma con el que se envíe (ver AvisoRemesa).
--}}
<x-emails.layout plantilla="aviso-remesa" :titulo="__('Aviso de cargo')">

    <p class="saludo">{{ __('Hola :nombre:', ['nombre' => $nombre]) }}</p>

    <p>{{ __('Te avisamos de que :comunidad va a pasar al cobro el siguiente recibo. No tienes que hacer nada: se cargará en tu cuenta.', ['comunidad' => $comunidad]) }}</p>

    <table role="presentation" class="datos" cellpadding="0" cellspacing="0">
        <tr>
            <th>{{ __('Importe') }}</th>
            <td class="importe">{{ $importe }}</td>
        </tr>
        <tr>
            <th>{{ __('Fecha del cargo') }}</th>
            <td>{{ $fechaCargo }}</td>
        </tr>
        <tr>
            <th>{{ __('Cuenta') }}</th>
            <td class="cuenta">{{ $cuenta }}</td>
        </tr>
        <tr>
            <th>{{ __('Inmueble') }}</th>
            <td>{{ $inmueble }}</td>
        </tr>
        <tr>
            <th>{{ __('Concepto') }}</th>
            <td>{{ $concepto }}</td>
        </tr>
    </table>

    <p class="aviso">
        {{ __('Si los datos no son correctos o la cuenta ya no es la tuya, escribe o llama antes de la fecha del cargo.') }}
    </p>

    <x-slot name="pie">
        {{ $comunidad }}<br><br>
        {{ __('Este es un correo automático: por favor no respondas a esta dirección.') }}
        @if ($correoContacto)
            {{ __('Para cualquier consulta, escribe a :correo.', ['correo' => $correoContacto]) }}
        @endif
    </x-slot>
</x-emails.layout>
