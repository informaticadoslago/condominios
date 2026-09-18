{{-- Horario semanal de un Horario: A4 apaisado (muchas columnas de días). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ __('Horario') }} {{ $horario->nombre }}</title>
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
        thead th { border-bottom: 0.6pt solid #000; text-align: center; }
        td.celda  { text-align: center; vertical-align: top; }
        .caja      { border-radius: 3pt; padding: 3pt 4pt; }
        .caja .hora   { display: block; font-weight: bold; font-size: 7.5pt; }
        .caja.vacia   { color: #999; }
        .caja.recreo  { background-color: #e5e5e5; color: #555; }
        tbody tr td { border-bottom: 0.3pt solid #ccc; }
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

@if (! count($diasConfig))
    <p>{{ __('Todavía no hay días de clase configurados.') }}</p>
@else
    <table>
        <thead>
            <tr>
                @foreach ($diasConfig as $config)
                    <th>{{ $config['nombre'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @for ($fila = 0; $fila < $maxSlots; $fila++)
                <tr>
                    @foreach ($diasConfig as $dia => $config)
                        @php($slot = $config['slots'][$fila] ?? null)
                        <td class="celda">
                            @if ($slot)
                                @if ($slot['tipo'] === 'recreo')
                                    <div class="caja recreo">
                                        <span class="hora">{{ $slot['hora'] }}</span>
                                        {{ __('Recreo') }} ({{ $slot['duracion'] }} min)
                                    </div>
                                @elseif (isset($asignaciones[$dia][$slot['numero']]))
                                    <div class="caja"
                                        style="background-color: {{ $asignaciones[$dia][$slot['numero']]['fondo'] }}; color: {{ $asignaciones[$dia][$slot['numero']]['texto'] }};">
                                        <span class="hora">{{ $slot['hora'] }}</span>
                                        {{ $asignaciones[$dia][$slot['numero']]['nombre'] }}
                                    </div>
                                @else
                                    <div class="caja vacia">
                                        <span class="hora">{{ $slot['hora'] }}</span>
                                        —
                                    </div>
                                @endif
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endfor
        </tbody>
    </table>
@endif

</body>
</html>
