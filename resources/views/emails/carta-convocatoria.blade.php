{{--
    Convocatoria de junta a un propietario: el saludo a su nombre, el texto escrito en el
    modal de envío ($texto: los párrafos van separados por una línea en blanco) y los datos
    de la junta y de su inmueble. Los adjuntos (la carta y los documentos) los pone CartaConvocatoria.

    El aspecto está en el fichero hermano carta-convocatoria.css; aquí solo va el texto.
    Lo fijo pasa por __(), así que se traduce al idioma con el que se envíe (ver CartaConvocatoria);
    el texto escrito va tal cual.
--}}
<x-emails.layout plantilla="carta-convocatoria" :titulo="__('Convocatoria de junta')">

    <p class="saludo">{{ $nombre ? __('Hola :nombre:', ['nombre' => $nombre]) : __('Hola:') }}</p>

    {{-- Texto escrito a mano: se escapa (e) y luego se respetan sus saltos de línea. --}}
    @foreach (preg_split('/\R\s*\R/', trim($texto)) as $parrafo)
        <p>{!! nl2br(e(trim($parrafo))) !!}</p>
    @endforeach

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

    <x-slot name="pie">
        {{ $comunidad }}<br><br>
        {{ __('Este es un correo automático: por favor no respondas a esta dirección.') }}
        @if ($correoContacto)
            {{ __('Para cualquier consulta, escribe a :correo.', ['correo' => $correoContacto]) }}
        @endif
    </x-slot>
</x-emails.layout>
