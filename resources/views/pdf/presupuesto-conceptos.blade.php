{{-- Presupuesto con sus conceptos: A4 vertical. --}}
@php
    $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
    $estadoInforme = (int) $presupuesto->estado_id === \App\Models\TipoEstadoPresupuesto::APROBADO
        ? __('Aprobado')
        : __('Provisional');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ __('Presupuesto') }} {{ $presupuesto->anho }} - {{ __('Conceptos') }}</title>
    <style>
        @page { margin: 28mm 12mm 18mm; }
        body  { font-family: DejaVu Sans, sans-serif; font-size: 8.8pt; color: #000; }
        h1    { font-size: 12pt; margin: 0; }

        .cabecera { position: fixed; top: -23mm; left: 0; width: 100%; height: 20mm;
                    border-bottom: 0.5pt solid #ccc; }
        .pie      { position: fixed; bottom: -11mm; left: 0; width: 100%; height: 8mm;
                    border-top: 0.5pt solid #ccc; font-size: 7.5pt; color: #444; }

        .maq td { border: 0; padding: 0; vertical-align: middle; text-align: left; }
        .maq td.centro  { text-align: center; }
        .maq td.derecha { text-align: right; }

        .meta { margin-bottom: 8pt; font-size: 8pt; color: #444; }
        .meta strong { color: #000; }

        table { border-collapse: collapse; width: 100%; }
        thead { display: table-header-group; }
        th, td { padding: 3pt 4pt; }
        thead th { border-bottom: 0.6pt solid #000; }
        th.izq, td.izq { text-align: left; }
        th.der, td.der { text-align: right; }
        tbody td { border-bottom: 0.3pt solid #ccc; }
        tfoot td { border-top: 0.6pt solid #000; font-weight: bold; }
    </style>
</head>
<body>

<div class="cabecera">
    <table class="maq">
        <tr>
            <td width="25%"><img src="{{ resource_path('images/dosLago-128.png') }}" height="55"></td>
            <td class="centro" width="50%">
                <h1>{{ __('Presupuesto') }} {{ $presupuesto->anho }} - {{ __('Conceptos') }}</h1>
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

<div class="meta">
    <strong>{{ $presupuesto->nombre }}</strong>
    &nbsp;·&nbsp;{{ __('Año') }}: {{ $presupuesto->anho }}
    &nbsp;·&nbsp;{{ __('Estado') }}: {{ $estadoInforme }}
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

<table>
    <thead>
        <tr>
            <th class="izq">{{ __('Concepto') }}</th>
            <th class="izq">{{ __('Grupo de reparto') }}</th>
            <th class="der">{{ __('Importe') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($conceptos as $concepto)
            <tr>
                <td class="izq">{{ $concepto->concepto }}</td>
                <td class="izq">{{ $concepto->grupoDeReparto?->nombre ?? '—' }}</td>
                <td class="der">{{ $fmt($concepto->importe) }}</td>
            </tr>
        @empty
            <tr>
                <td class="izq" colspan="3">{{ __('Este presupuesto todavía no tiene conceptos.') }}</td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td class="izq" colspan="2">{{ __('Total') }}</td>
            <td class="der">{{ $fmt($total) }}</td>
        </tr>
    </tfoot>
</table>

</body>
</html>
