{{-- Presupuesto y su reparto en fechas: A4 vertical.
     La cabecera y el pie van en position:fixed para que se repitan en cada hoja. --}}
@php
    $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ __('Presupuesto') }} {{ $presupuesto->anho }}</title>
    <style>
        @page { margin: 28mm 12mm 18mm; }
        body  { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #000; }
        h1    { font-size: 12pt; margin: 0; }
        h2    { font-size: 9.5pt; margin: 8pt 0 3pt; }

        .cabecera { position: fixed; top: -23mm; left: 0; width: 100%; height: 20mm;
                    border-bottom: 0.5pt solid #ccc; }
        .pie      { position: fixed; bottom: -11mm; left: 0; width: 100%; height: 8mm;
                    border-top: 0.5pt solid #ccc; font-size: 7.5pt; color: #444; }

        .maq td { border: 0; padding: 0; vertical-align: middle; text-align: left; }
        .maq td.centro  { text-align: center; }
        .maq td.derecha { text-align: right; }

        .meta       { margin-bottom: 6pt; font-size: 8pt; color: #444; }
        .meta strong{ color: #000; }

        table               { border-collapse: collapse; width: 100%; margin-bottom: 6pt; }
        thead               { display: table-header-group; }
        th, td              { padding: 2pt 4pt; }
        thead th            { border-bottom: 0.6pt solid #000; text-align: right; }
        thead th.izq        { text-align: left; }
        tbody td            { border-bottom: 0.3pt solid #ccc; text-align: right; }
        tbody td.izq        { text-align: left; }
        tfoot td            { border-top: 0.6pt solid #000; font-weight: bold; text-align: right; }
        tfoot td.izq        { text-align: left; }

        .bloque             { margin-bottom: 10pt; }
        .subtitulo-bloque   { font-weight: bold; font-size: 8.5pt;
                              border-bottom: 0.4pt solid #888; padding-bottom: 1pt; margin-bottom: 2pt; }
        .aviso              { color: #b91c1c; font-size: 8pt; }
    </style>
</head>
<body>

<div class="cabecera">
    <table class="maq">
        <tr>
            <td width="25%"><img src="{{ resource_path('images/dosLago-128.png') }}" height="55"></td>
            <td class="centro" width="50%">
                <h1>{{ __('Presupuesto') }} {{ $presupuesto->anho }}</h1>
            </td>
            <td width="25%"></td>
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

{{-- Ficha del presupuesto --}}
<div class="meta">
    <strong>{{ $presupuesto->nombre }}</strong>
    &nbsp;·&nbsp;{{ __('Año') }}: {{ $presupuesto->anho }}
    &nbsp;·&nbsp;{{ __('Estado') }}: {{ $presupuesto->estado?->descripcion }}
    @if ($presupuesto->periodicidad)
        &nbsp;·&nbsp;{{ __('Periodicidad') }}: {{ $presupuesto->periodicidad->descripcion }}
    @endif
    @if ($presupuesto->fecha_primer_pago)
        &nbsp;·&nbsp;{{ __('Primer pago') }}: {{ $presupuesto->fecha_primer_pago->format('d/m/Y') }}
    @endif
    @if ($presupuesto->numero_pagos)
        &nbsp;·&nbsp;{{ __('Nº pagos') }}: {{ $presupuesto->numero_pagos }}
    @endif
</div>

{{-- Resumen: total --}}
<div class="bloque">
    <table>
        <tbody>
            <tr>
                <td class="izq" style="font-weight:bold">{{ __('Total del presupuesto') }}</td>
                <td style="font-weight:bold">{{ $fmt($totalPresupuesto) }}</td>
            </tr>
        </tbody>
    </table>
</div>

{{-- Reparto por grupo --}}
@forelse ($grupos as $datosGrupo)
    <div class="bloque">
        <div class="subtitulo-bloque">
            {{ $datosGrupo['grupo']->nombre }}
            &nbsp;—&nbsp;{{ __('Total') }}: {{ $fmt($datosGrupo['total']) }}
        </div>
        @if ($datosGrupo['sumaCoeficientes'] <= 0)
            <p class="aviso">{{ __('Este grupo no tiene inmuebles o su coeficiente suma 0.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th class="izq">{{ __('Inmueble') }}</th>
                        <th>{{ __('Coeficiente') }}</th>
                        <th>{{ __('Importe') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($datosGrupo['lineas'] as $linea)
                        <tr>
                            <td class="izq">{{ $linea['inmueble']->planta }} / {{ $linea['inmueble']->puerta }}</td>
                            <td>{{ $linea['coeficiente'] }}%</td>
                            <td>{{ $fmt($linea['importe']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td class="izq">{{ __('Total') }}</td>
                        <td></td>
                        <td>{{ $fmt($datosGrupo['total']) }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>
@empty
    <p>{{ __('Este presupuesto todavía no tiene conceptos con grupo de reparto.') }}</p>
@endforelse

{{-- Reparto global con fechas de pago --}}
@if ($global->isNotEmpty())
    <div class="bloque">
        <div class="subtitulo-bloque">{{ __('Reparto global — lo que se cobra a cada inmueble') }}</div>
        @unless ($datosPagoCompletos)
            <p class="aviso">{{ __('Faltan datos de pago: no se puede mostrar el desglose por fechas.') }}</p>
        @endunless
        <table>
            <thead>
                <tr>
                    <th class="izq">{{ __('Inmueble') }}</th>
                    <th>{{ __('Total') }}</th>
                    @if ($datosPagoCompletos)
                        @foreach ($fechasPagos as $i => $fecha)
                            <th>{{ __('Pago') }} {{ $i + 1 }}<br><span style="font-weight:normal;font-size:7pt">{{ $fecha->format('d/m/Y') }}</span></th>
                        @endforeach
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($global as $fila)
                    <tr>
                        <td class="izq">{{ $fila['inmueble']->planta }} / {{ $fila['inmueble']->puerta }}</td>
                        <td>{{ $fmt($fila['total']) }}</td>
                        @if ($datosPagoCompletos)
                            @foreach ($fila['pagos'] as $importePago)
                                <td>{{ $fmt($importePago) }}</td>
                            @endforeach
                        @endif
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td class="izq">{{ __('Total') }}</td>
                    <td>{{ $fmt($global->sum('total')) }}</td>
                    @if ($datosPagoCompletos)
                        @foreach ($fechasPagos as $i => $fecha)
                            <td>{{ $fmt($global->sum(fn ($f) => $f['pagos'][$i] ?? 0)) }}</td>
                        @endforeach
                    @endif
                </tr>
            </tfoot>
        </table>
    </div>
@endif

</body>
</html>
