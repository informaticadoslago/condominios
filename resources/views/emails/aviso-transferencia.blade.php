{{--
    Aviso al propietario que paga por transferencia de que le toca ingresar.

    A diferencia del de remesa, aquí SÍ tiene que hacer algo, así que los datos para
    transferir van destacados. El aspecto está en aviso-transferencia.css.
--}}
<x-emails.layout plantilla="aviso-transferencia" :titulo="__('Aviso de pago')">

    <p class="saludo">{{ __('Hola, :nombre:', ['nombre' => $nombre]) }}</p>

    <p>{{ __('Te recordamos que tienes pendiente el siguiente recibo de :comunidad. Como tu forma de pago es transferencia, hay que hacer el ingreso.', ['comunidad' => $comunidad]) }}</p>

    <table role="presentation" class="datos" cellpadding="0" cellspacing="0">
        <tr>
            <th>{{ __('Importe a ingresar') }}</th>
            <td class="importe">{{ $importe }}</td>
        </tr>
        <tr>
            <th>{{ __('Fecha límite') }}</th>
            <td>{{ $vencimiento }}</td>
        </tr>
        <tr>
            <th>{{ __('Inmueble') }}</th>
            <td>{{ $inmueble }}</td>
        </tr>
    </table>

    @if ($iban)
        <div class="destacado">
            {{ __('Cuenta de la comunidad:') }}<br>
            <span class="iban">{{ $iban }}</span><br>
            {{ __('Concepto:') }} <span class="concepto">{{ $concepto }}</span>
        </div>

        <p class="aviso">
            {{ __('Pon ese concepto en la transferencia: es lo que permite reconocer tu pago.') }}
        </p>
    @endif

    <p class="aviso">
        {{ __('Si ya lo has pagado, no hagas caso de este mensaje.') }}
    </p>

    <x-slot name="pie">
        {{ $comunidad }}
    </x-slot>
</x-emails.layout>
