{{-- Notas de una asignatura en una evaluación: una fila por alumno y una columna por prueba, con
     la nota sobre 10, y al final la media de la evaluación y su nota. A4 apaisado; cada hoja
     lleva su tanda de alumnos y de columnas, y la última de alumnos, la fila de medias. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ __('Notas') }} {{ $cuadro->asignatura->nombre }}</title>
    <style>
        @page { margin: 26mm 10mm 16mm; }
        body  { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #000; }
        h1    { font-size: 14pt; margin: 0; }
        .subtitulo { font-size: 11pt; margin-top: 2pt; }

        .cabecera { position: fixed; top: -21mm; left: 0; width: 100%; height: 18mm;
                    border-bottom: 0.5pt solid #ccc; text-align: center; }
        .pie      { position: fixed; bottom: -10mm; left: 0; width: 100%; height: 8mm;
                    border-top: 0.5pt solid #ccc; font-size: 7.5pt; color: #444; }

        .maq { width: 100%; border-collapse: collapse; }
        .maq td { border: 0; padding: 0; vertical-align: middle; text-align: left; }
        .maq td.derecha { text-align: right; }

        /* Anchos fijos (los pone cada tabla en su cabecera): las columnas miden lo mismo en todas las hojas. */
        table.notas { border-collapse: collapse; }
        table.notas th, table.notas td { padding: 0.5pt 4pt; border: 0.3pt solid #999; overflow: hidden; line-height: 1.15; }
        table.notas thead th { border-bottom: 0.8pt solid #000; font-size: 8pt; vertical-align: bottom; }
        table.notas th.alumno, table.notas td.alumno { text-align: left; }
        /* El alumno, en letra grande: es lo que se busca al leer la hoja. */
        table.notas td.alumno { font-size: 13pt; white-space: nowrap; }
        table.notas td.nota { text-align: center; font-size: 12pt; }
        /* La nota con palabras (Apto, No entregado…), en letra pequeña para que quepa en la columna. */
        table.notas td.palabra { font-size: 6.5pt; }
        table.notas tbody tr:nth-child(odd) { background-color: rgb(241, 241, 242); }
        /* El peso, debajo del nombre; las partes, en gris: cuentan a través de su prueba. */
        table.notas th .peso { font-weight: normal; font-size: 7pt; }
        table.notas th.parte { color: #666; }
        /* La media de la evaluación y su nota, separadas de las pruebas y en negrita. */
        table.notas th.final, table.notas td.final { border-left: 0.8pt solid #000; font-weight: bold; }
        table.notas tfoot td { border-top: 0.8pt solid #000; font-weight: bold; }
        table.notas tfoot td.alumno { font-size: 11pt; }
        .baja { font-size: 8pt; color: #555; }
        .salto { page-break-after: always; }
    </style>
</head>
<body>

<div class="cabecera">
    <h1>{{ $cuadro->asignatura->nombreConModulo() }}</h1>
    <div class="subtitulo">{{ $cuadro->evaluacion->curso->nombre }} · {{ $cuadro->evaluacion->nombre }}</div>
</div>

<div class="pie">
    <table class="maq">
        <tr>
            <td>{{ config('app.name') }}</td>
            <td class="derecha">{{ now()->format('d/m/Y H:i') }}</td>
        </tr>
    </table>
</div>

@forelse ($hojas as $hoja)
    <table @class(['notas', 'salto' => ! $loop->last])>
        <thead>
            <tr>
                <th class="alumno" style="width: {{ $anchoAlumno }}mm;">{{ __('Alumno') }}</th>
                @foreach ($hoja['columnas'] as $columna)
                    @if ($columna === 'calculada')
                        <th class="final" style="width: {{ $anchoColumna }}mm;">{{ __('Evaluación') }}</th>
                    @elseif ($columna === 'final')
                        <th class="final" style="width: {{ $anchoColumna }}mm;">{{ __('Nota') }}</th>
                    @else
                        {{-- Con su peso; el de una parte es dentro de su prueba, y sale en gris. --}}
                        <th @class(['parte' => $columna->prueba_id]) style="width: {{ $anchoColumna }}mm;">
                            {{ $columna->nombre }}
                            <div class="peso">{{ \App\Models\Prueba::numero($columna->peso) }} %@if ($columna->prueba_id) {{ __('de') }} {{ $cuadro->pruebas->firstWhere('id', $columna->prueba_id)?->nombre }}@endif</div>
                        </th>
                    @endif
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($hoja['apuntes'] as $apunte)
                <tr>
                    <td class="alumno">
                        {{ $apunte->alumno->nombreCompleto }}
                        @if ($apunte->estado_id != \App\Models\AsignaturaAlumno::ESTADO_ACTIVO)
                            <span class="baja">({{ __('baja') }})</span>
                        @endif
                    </td>
                    @foreach ($hoja['columnas'] as $columna)
                        @if (is_string($columna))
                            <td class="nota final">{{ \App\Models\Prueba::numero($cuadro->finales[$apunte->id][$columna]) }}</td>
                        @else
                            {{-- De la prueba con partes, la nota final (la media de sus partes o su retoque). --}}
                            @php($nota = $cuadro->notas[$apunte->id][$columna->id])
                            <td @class(['nota', 'palabra' => ! $columna->con_partes && ! $columna->esNumerica()])>{{ $columna->con_partes
                                ? \App\Models\Prueba::numero($nota)
                                : $columna->notaVisible($nota) }}</td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
        {{-- La media de cada columna, de los alumnos activos con nota en ella; en las pruebas con
             palabras, la buena cuenta 10 y la mala 0. --}}
        @if ($hoja['medias'])
            <tfoot>
                <tr class="medias">
                    <td class="alumno">{{ __('Media') }}</td>
                    @foreach ($hoja['columnas'] as $columna)
                        <td @class(['nota', 'final' => is_string($columna)])>{{ \App\Models\Prueba::numero(match ($columna) {
                            'calculada' => $cuadro->mediaCalculada,
                            'final'     => $cuadro->mediaFinal,
                            default     => $cuadro->medias[$columna->id],
                        }) }}</td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>
@empty
    <p>{{ __('No hay pruebas ni alumnos que mostrar.') }}</p>
@endforelse

</body>
</html>
