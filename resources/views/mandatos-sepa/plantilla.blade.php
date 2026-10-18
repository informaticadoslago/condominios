{{-- Formulario de recogida de datos bancarios, para imprimir o mandar al propietario.
     Una hoja por cada elemento de $hojas: [propietario, piso, tiposDocumento].

     Solo van rellenados los datos del propietario y, si se saca desde un inmueble, el
     piso. Todo lo demás va en blanco a propósito: la fecha y la firma las pone quien
     firma y el IBAN es justo el dato que este papel viene a recoger. Sacada para una
     persona, el PISO tampoco se imprime — un mismo titular puede tener varios, y con
     un piso impreso el documento no valdría para los otros. Sin propietario (hoja en
     blanco) no va nada relleno. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ __('Formulario recogida datos bancarios') }}</title>
    <style>
        /* Cabe justo en una hoja: si se tocan alturas o márgenes, comprobar que la
           firma no se va a una segunda página. */
        @page { margin: 1.3cm 1.6cm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #000; }
        h1 { font-size: 16pt; margin: 0 0 10pt; }
        table { border-collapse: collapse; width: 100%; }
        td { vertical-align: middle; padding: 0; }
        .etiqueta { white-space: nowrap; padding-right: 6pt; }
        .caja { border: 1px solid #000; height: 21pt; }
        .caja-texto { border: 1px solid #000; height: 21pt; padding: 3pt 6pt; font-weight: bold; }
        .aviso { font-size: 9pt; margin: 0 0 9pt; }
        .seccion { margin-top: 10pt; margin-bottom: 5pt; }
        .fila { margin-bottom: 7pt; }
        .opciones { margin: 2pt 0 5pt; }
        .opciones span { padding-right: 26pt; }
        .firma { border: 1px solid #000; height: 80pt; }
        .salto { page-break-after: always; }
    </style>
</head>
<body>

@foreach ($hojas as $hoja)
@php($propietario = $hoja['propietario'])
<div @class(['salto' => ! $loop->last])>

<h1>{{ __('Formulario recogida datos bancarios') }}</h1>

<table class="fila">
    <tr>
        <td class="etiqueta" width="46">{{ __('Fecha') }}:</td>
        <td><div class="caja"></div></td>
        <td width="220"></td>
    </tr>
</table>

<p class="aviso">{{ __('Por favor complete todos los campos requeridos') }}</p>

<div class="seccion">{{ __('Propietario/a') }}</div>

<table class="fila">
    <tr>
        <td class="etiqueta" width="70">{{ __('Nombre') }}:</td>
        <td><div class="caja-texto">{{ $propietario?->nombre }}</div></td>
    </tr>
</table>

<table class="fila">
    <tr>
        <td class="etiqueta" width="70">{{ __('Apellidos') }}:</td>
        <td><div class="caja-texto">{{ trim($propietario?->apellido1.' '.$propietario?->apellido2) }}</div></td>
    </tr>
</table>

<div>{{ __('Documento identificativo') }}:</div>
<div class="opciones">
    @foreach ($hoja['tiposDocumento'] as $tipo)
        <span>{{ $tipo['marcado'] ? '◉' : '○' }} {{ $tipo['nombre'] }}</span>
    @endforeach
</div>
<div>{{ __('Número') }}:</div>
<table class="fila">
    <tr>
        <td width="180"><div class="caja-texto">{{ $propietario?->documento_identificativo }}</div></td>
        <td></td>
    </tr>
</table>

{{-- Solo relleno si se saca desde un inmueble: sacado para una persona, el mandato es
     del titular y su cuenta, no de un piso concreto. --}}
<div>{{ __('Piso (ej: Bajo, 1ºA, 4ºIzquierda, etc.)') }}</div>
<table class="fila">
    <tr>
        <td width="180"><div class="caja-texto">{{ $hoja['piso'] }}</div></td>
        <td></td>
    </tr>
</table>

<table class="seccion">
    <tr>
        <td>{{ __('Titular cuenta bancaria') }}</td>
        <td align="right">☐ {{ __('Mismo titular que propietario/a') }}</td>
    </tr>
</table>

<table class="fila">
    <tr>
        <td class="etiqueta" width="70">{{ __('Nombre') }}:</td>
        <td><div class="caja"></div></td>
    </tr>
</table>

<table class="fila">
    <tr>
        <td class="etiqueta" width="70">{{ __('Apellidos') }}:</td>
        <td><div class="caja"></div></td>
    </tr>
</table>

<div>{{ __('Documento identificativo') }}:</div>
<div class="opciones">
    @foreach ($hoja['tiposDocumento'] as $tipo)
        <span>○ {{ $tipo['nombre'] }}</span>
    @endforeach
</div>
<div>{{ __('Número') }}:</div>
<table class="fila">
    <tr>
        <td width="180"><div class="caja"></div></td>
        <td></td>
    </tr>
</table>

<div>{{ __('Entidad bancaria') }}:</div>
<div class="caja fila"></div>

<div>{{ __('IBAN') }}:</div>
<div class="caja fila"></div>

<table>
    <tr>
        <td class="etiqueta" width="230" valign="top">{{ __('Firma') }}: ({{ __('Titular de la cuenta bancaria') }})</td>
        <td><div class="firma"></div></td>
    </tr>
</table>

</div>
@endforeach

</body>
</html>
