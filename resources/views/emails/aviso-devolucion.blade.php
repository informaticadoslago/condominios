{{--
    Aviso al propietario de que el banco le ha devuelto uno o varios recibos.

    Puede llevar más de una línea (varios inmuebles del mismo destinatario devueltos en
    la misma tanda). El aspecto está en aviso-devolucion.css.
--}}
<x-emails.layout plantilla="aviso-devolucion" :titulo="__('Aviso de devolución')">

    <p class="saludo">{{ __('Hola, :nombre:', ['nombre' => $nombre]) }}</p>

    <p>{{ __('Con fecha :fecha el banco nos ha devuelto los siguientes recibos de :comunidad:', ['fecha' => $fecha, 'comunidad' => $comunidad]) }}</p>

    <table role="presentation" class="datos" cellpadding="0" cellspacing="0">
        @foreach ($lineas as $linea)
            <tr>
                <th>{{ $linea['concepto'] }}</th>
                <td class="importe">{{ $linea['importe'] }}</td>
            </tr>
        @endforeach
    </table>

    <p>
        {{ __('Por un importe total de :importe.', ['importe' => $importeTotal]) }}
    </p>

    <p class="aviso">
        {{ __('Esta devolución puede conllevar gastos bancarios adicionales.') }}
    </p>

    @if ($iban)
        <div class="destacado">
            {{ __('Rogamos hagan una transferencia a la cuenta de la comunidad a la mayor brevedad:') }}<br>
            <span class="iban">{{ $iban }}</span>
        </div>
    @endif

    <p class="aviso">{{ __('Un saludo.') }}</p>

    <x-slot name="pie">
        {{ $comunidad }}
    </x-slot>
</x-emails.layout>
