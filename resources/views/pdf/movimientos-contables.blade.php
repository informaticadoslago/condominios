{{-- Informe de movimientos en papel: lo mismo que hay en pantalla, en A4 apaisado.

     El apaisado no es capricho: con doce meses más la columna de total son trece
     columnas de importes, y en vertical no quedaría sitio para el nombre de la cuenta.
     Los anchos se reparten según cuántos meses tenga el rango, así que un trimestre sale
     holgado y el año entero justo pero legible.

     La cabecera y el pie van en position:fixed, que es como dompdf los repite en todas
     las hojas; por eso los márgenes de @page les dejan sitio y ellos se meten dentro con
     un desplazamiento negativo. --}}
@php
    $euros = fn ($centimos) => $centimos === 0 ? '-' : number_format($centimos / 100, 2, ',', '.');
    // Primera columna para la cuenta; el resto a repartir entre el total y los meses.
    $anchoImporte = round(78 / (count($meses) + 1), 3);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ __('Movimientos') }}</title>
    <style>
        /* Márgenes laterales cortos a propósito: el logo y el pie van pegados al borde. */
        @page { margin: 30mm 8mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #000; }
        h1 { font-size: 13pt; margin: 0; }
        h2 { font-size: 10pt; margin: 0 0 3pt; }

        /* La banda mide justo lo que el logo (58pt): así la raya queda pegada a él. */
        .cabecera { position: fixed; top: -25mm; left: 0; width: 100%; height: 20.5mm;
                    border-bottom: 0.5pt solid #ccc; }
        /* Logo y título descansan sobre la raya, para que quede pegada a ellos. */
        .cabecera .maq td { vertical-align: bottom; }
        .pie { position: fixed; bottom: -10mm; left: 0; width: 100%; height: 8mm;
               border-top: 0.5pt solid #ccc; font-size: 7.5pt; color: #444; }
        /* Tablas de maquetación (cabecera y pie): sin bordes ni relleno propios, y a la
           izquierda salvo que se diga — si no, heredan el text-align de los importes. */
        .maq td { border: 0; padding: 0; vertical-align: middle; text-align: left; }
        .maq td.centro { text-align: center; }
        .maq td.derecha { text-align: right; }

        .informe { margin-bottom: 8pt; }
        .empresa { font-size: 9pt; font-weight: bold; }
        .rango { font-size: 8pt; color: #444; }

        table { border-collapse: collapse; width: 100%; }
        /* Con muchas cuentas la tabla pasa de hoja: la cabecera se repite arriba. */
        thead { display: table-header-group; }
        th, td { padding: 2pt 3pt; }
        thead th { border-bottom: 0.6pt solid #000; text-align: right; font-size: 7.5pt; }
        thead th.cuenta { text-align: left; }
        tbody td { border-bottom: 0.3pt solid #bbb; text-align: right; }
        tbody td.cuenta { text-align: left; }
        tfoot td { border-top: 0.6pt solid #000; font-weight: bold; text-align: right; }
        tfoot td.cuenta { text-align: left; }
        .bloque { margin-bottom: 10pt; }
        .vacio { color: #666; text-align: left; }
        /* Resumen y justificación no van en dos columnas como en pantalla: la
           justificación lleva una cuenta por propietario y, metida en una celda, dompdf
           no la parte entre páginas — o la empuja entera, o la recorta. Apilada, son
           filas normales y pasan de hoja sin perder nada. */
        .estrecha { width: 55%; }
    </style>
</head>
<body>

<div class="cabecera">
    <table class="maq">
        <tr>
            {{-- Versión de 128 px del logo: a 20 mm de alto pasa de 160 ppp y pesa 10 KB,
                 en vez del megabyte del original de 1024. --}}
            <td width="25%"><img src="{{ resource_path('images/dosLago-128.png') }}" height="58"></td>
            <td class="centro" width="50%"><h1>{{ __('Movimientos') }}</h1></td>
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

<div class="informe">
    <div class="empresa">{{ $empresaContable->razon_social }} — {{ $empresaContable->cif }}</div>
    <div class="rango">
        {{ __('Del :desde al :hasta', [
            'desde' => \Carbon\Carbon::parse($desde)->format('d/m/Y'),
            'hasta' => \Carbon\Carbon::parse($hasta)->format('d/m/Y'),
        ]) }}
    </div>
</div>

@foreach ([
    ['titulo' => __('Ingresos'), 'bloque' => $ingresos, 'total' => __('Total de ingresos')],
    ['titulo' => __('Gastos'), 'bloque' => $gastos, 'total' => __('Total de gastos')],
] as $seccion)
    <div class="bloque">
        <h2>{{ $seccion['titulo'] }}</h2>
        <table>
            <thead>
                <tr>
                    <th class="cuenta" width="22%">{{ __('Cuenta') }}</th>
                    <th width="{{ $anchoImporte }}%">{{ __('Total') }}</th>
                    @foreach ($meses as $etiqueta)
                        <th width="{{ $anchoImporte }}%">{{ $etiqueta }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($seccion['bloque']['filas'] as $fila)
                    <tr>
                        <td class="cuenta">{{ $fila['codigo'] }} - {{ $fila['nombre'] }}</td>
                        <td>{{ $euros($fila['total']) }}</td>
                        @foreach ($meses as $mes => $etiqueta)
                            <td>{{ $euros($fila['meses'][$mes]) }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td class="vacio" colspan="{{ count($meses) + 2 }}">
                            {{ __('No hay movimientos en estas fechas.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td class="cuenta">{{ $seccion['total'] }}</td>
                    <td>{{ $euros($seccion['bloque']['total']) }}</td>
                    @foreach ($meses as $mes => $etiqueta)
                        <td>{{ $euros($seccion['bloque']['totales'][$mes]) }}</td>
                    @endforeach
                </tr>
            </tfoot>
        </table>
    </div>
@endforeach

<div class="bloque">
    <h2>{{ __('Resumen') }}</h2>
    <table class="estrecha">
        <tbody>
            <tr>
                <td class="cuenta">{{ __('Saldo anterior') }}</td>
                <td>{{ number_format($saldoAnterior / 100, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="cuenta">{{ __('Total de ingresos') }}</td>
                <td>{{ number_format($ingresos['total'] / 100, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="cuenta">{{ __('Total de gastos') }}</td>
                <td>{{ number_format($gastos['total'] / 100, 2, ',', '.') }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td class="cuenta">{{ __('Saldo anterior + ingresos - gastos') }}</td>
                <td>{{ number_format($saldoFinal / 100, 2, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>
</div>

<div class="bloque">
    <h2>{{ __('Justificación del saldo') }}</h2>
    <table class="estrecha">
        <tbody>
            @foreach ($justificacion as $cuenta)
                <tr>
                    <td class="cuenta">{{ $cuenta->codigo }} - {{ $cuenta->nombre }}</td>
                    <td>{{ number_format($cuenta->saldo / 100, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td class="cuenta">{{ __('Total') }}</td>
                <td>{{ number_format($saldoFinal / 100, 2, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>
</div>

</body>
</html>
