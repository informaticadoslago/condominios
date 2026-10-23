{{-- Los exámenes de un Horario que muestra su lista (con sus filtros), sin la rejilla: A4 vertical. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ __('Exámenes') }} {{ $horario->nombre }}</title>
    <style>
        @page { margin: 28mm 10mm 18mm; }
        body  { font-family: DejaVu Sans, sans-serif; font-size: 8.8pt; color: #000; }
        h1    { font-size: 14pt; margin: 0; text-align: center; }

        .cabecera { position: fixed; top: -23mm; left: 0; width: 100%; height: 20mm;
                    border-bottom: 0.5pt solid #ccc; }
        .pie      { position: fixed; bottom: -11mm; left: 0; width: 100%; height: 8mm;
                    border-top: 0.5pt solid #ccc; font-size: 7.5pt; color: #444; }

        .maq td { border: 0; padding: 0; vertical-align: middle; text-align: left; }
        .maq td.derecha { text-align: right; }

        table { border-collapse: collapse; width: 100%; }
        th, td { padding: 3pt 4pt; }
        thead th { border-bottom: 0.6pt solid #000; }
        .caja { border-radius: 3pt; padding: 3pt 4pt; }
        tbody tr td { border-bottom: 0.3pt solid #ccc; }
        /* Rayado suave: gris clarito en las filas impares, blanco en las pares. */
        tbody tr:nth-child(odd) { background-color: rgb(241, 241, 242); }

        h2.examenes { font-size: 11pt; margin: 0; }
        table.examenes-mes { margin-top: 6pt; page-break-inside: avoid; }
        table.examenes-mes thead th { text-align: left; }
        td.dia-examen { width: 30%; white-space: nowrap; }
    </style>
</head>
<body>

<div class="cabecera">
    <table class="maq">
        <tr>
            <td>
                <h1>{{ $horario->nombre }}</h1>
            </td>
        </tr>
    </table>
</div>

<div class="pie">
    <table class="maq">
        <tr>
            <td>{{ config('app.name') }}</td>
            <td class="derecha">{{ now()->format('d/m/Y') }}</td>
        </tr>
    </table>
</div>

@if ($examenesPorMes->isNotEmpty())
    @include('pdf.parciales.examenes-por-mes')
@else
    <p>{{ __('No hay exámenes que mostrar.') }}</p>
@endif

</body>
</html>
