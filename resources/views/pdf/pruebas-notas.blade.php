{{-- Notas de una asignatura en una evaluación: una fila por alumno y una columna por prueba, con
     la nota sobre 10. A4 apaisado; cada hoja lleva su tanda de alumnos y de pruebas. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ __('Notas') }} {{ $asignatura->nombre }}</title>
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
        .baja { font-size: 8pt; color: #555; }
        .salto { page-break-after: always; }
    </style>
</head>
<body>

<div class="cabecera">
    <h1>{{ $asignatura->nombreConModulo() }}</h1>
    <div class="subtitulo">{{ $evaluacion->curso->nombre }} · {{ $evaluacion->nombre }}</div>
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
                @foreach ($hoja['pruebas'] as $prueba)
                    <th style="width: {{ $anchoPrueba }}mm;">{{ $prueba->nombre }}</th>
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
                    @foreach ($hoja['pruebas'] as $prueba)
                        {{-- De la prueba con partes, la nota final (la media de sus partes o su retoque). --}}
                        <td @class(['nota', 'palabra' => ! $prueba->con_partes && ! $prueba->esNumerica()])>{{ $prueba->con_partes
                            ? \App\Models\Prueba::numero($finales[$prueba->id][$apunte->id] ?? null)
                            : $prueba->notaVisible($apunte->notas->firstWhere('prueba_id', $prueba->id)?->nota_sobre_10) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
@empty
    <p>{{ __('No hay pruebas ni alumnos que mostrar.') }}</p>
@endforelse

</body>
</html>
