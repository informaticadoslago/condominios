{{-- Eventos del calendario agrupados por mes (ver Evento::porMes()), con la cabecera de
     cada mes. Lo usan el PDF del horario semanal (debajo de la rejilla) y el de la lista
     de eventos; las clases (eventos, eventos-mes, dia-evento, caja) las define cada uno en
     su <style>. Del examen sale su asignatura; del resto, el texto del evento. --}}
<h2 class="eventos">{{ __('Calendario') }}</h2>
@foreach ($eventosPorMes as $mes => $eventos)
    <table class="eventos-mes">
        <thead>
            <tr>
                <th colspan="2">{{ $mes }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($eventos as $evento)
                <tr>
                    <td class="dia-evento">{{ ucfirst($evento->fecha->isoFormat('dddd D')) }}</td>
                    <td>
                        @if ($evento->esExamen() && $evento->asignatura)
                            <span class="caja"
                                style="background-color: {{ $evento->asignatura->colorFondo() }}; color: {{ $evento->asignatura->colorTexto() }};">
                                {{ $evento->asignatura->nombreConModulo() }}
                            </span>
                        @else
                            {{ $evento->titulo }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endforeach
