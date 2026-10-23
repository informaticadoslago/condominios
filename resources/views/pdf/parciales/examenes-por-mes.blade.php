{{-- Exámenes agrupados por mes (ver Examen::porMes()), con la cabecera de cada mes. Lo
     usan el PDF del horario semanal (debajo de la rejilla) y el de la lista de exámenes;
     las clases (examenes, examenes-mes, dia-examen, caja) las define cada uno en su <style>. --}}
<h2 class="examenes">{{ __('Exámenes') }}</h2>
@foreach ($examenesPorMes as $mes => $examenes)
    <table class="examenes-mes">
        <thead>
            <tr>
                <th colspan="2">{{ $mes }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($examenes as $examen)
                <tr>
                    <td class="dia-examen">{{ ucfirst($examen->fecha->isoFormat('dddd D')) }}</td>
                    <td>
                        @if ($examen->asignatura)
                            <span class="caja"
                                style="background-color: {{ $examen->asignatura->colorFondo() }}; color: {{ $examen->asignatura->colorTexto() }};">
                                {{ $examen->asignatura->nombre }}
                            </span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endforeach
