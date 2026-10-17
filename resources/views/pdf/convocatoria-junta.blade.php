{{-- Carta de convocatoria de junta: A4 vertical, una carta por inmueble (ver GenerarCartaConvocatoria).
     Lo fijo es el texto; lo variable (azul) son los datos de la comunidad y de la convocatoria,
     y el orden del día (rojo). El destinatario va en negro: es para la ventana del sobre. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ __('Convocatoria') }} {{ $comunidad }}</title>
    <style>
        @page { margin: 15mm 20mm 10mm 20mm; }
        /* DejaVu Sans, como el resto de PDF: va incrustada (la negrita sale igual en cualquier
           visor) y tiene cualquier carácter que se pegue en el orden del día. Es más ancha que
           la Arial de la plantilla: de ahí el cuerpo pequeño y el interlineado justo, para que
           cada carta quepa en una página. */
        body  { font-family: DejaVu Sans, sans-serif; font-size: 8.4pt; color: #000; line-height: 1.15; }

        .dato      { color: #1b2a8c; font-weight: bold; }
        .membrete  { width: 90mm; text-align: center; color: #1b2a8c; margin-top: 11mm; font-size: 8pt; line-height: 1.1; }
        .membrete .nombre { font-size: 11pt; font-weight: bold; }
        .destinatario { margin: 11mm 0 0 98mm; font-size: 10.5pt; line-height: 1.25; height: 14mm; }
        .fecha-carta  { text-align: right; margin-top: 7mm; }
        .saludo       { margin-top: 1mm; }

        p        { margin: 3.5mm 0 0; text-align: justify; text-indent: 12mm; }
        .titulo  { margin-top: 4mm; text-align: center; font-weight: bold; text-decoration: underline; }
        .puntos  { margin: 3.5mm 12mm 0 15mm; color: #e00000; font-weight: bold; }
        .puntos div { text-align: justify; }
        .firma   { margin: 4mm 0 0 95mm; }

        .delegacion { margin-top: 10mm; border-top: 0.8pt dashed #000; padding-top: 5mm; }
        .delegacion p { margin-top: 2.5mm; }
        .delegacion .linea { margin-top: 4mm; }
        .delegacion .atentamente { margin: 4mm 0 0 3mm; }
        .delegacion .firmado { margin: 1mm 0 0 55mm; line-height: 1.4; }

        .salto { page-break-after: always; }

        /* Marca de agua de la versión de la lista. Fija: DomPDF la repite en cada página, y
           al ir antes que la carta queda por debajo del texto. DomPDF la recorta por el ancho
           del área de texto (170 mm) ANTES de girarla: sin girar tiene que caber en él, de ahí
           el tamaño. Centrada en ese área y girada sobre su centro. */
        .marca-agua { position: fixed; top: 123mm; left: 7mm; width: 170mm; text-align: center;
                      white-space: nowrap; font-size: 40pt; font-weight: bold; color: #000; opacity: 0.12;
                      transform: rotate(-45deg); }
    </style>
</head>
<body>
@if ($enPreparacion ?? false)
    <div class="marca-agua">EN PREPARACIÓN</div>
@endif
@foreach ($inmuebles as $inmueble)
    <div @class(['salto' => ! $loop->last])>
        <div class="membrete">
            <div class="nombre">{{ $comunidad }}</div>
            @if ($direccion !== '')
                <div>{{ mb_strtoupper($direccion) }}</div>
            @endif
            @if ($municipio !== '')
                <div>{{ mb_strtoupper($municipio) }}</div>
            @endif
        </div>

        <div class="destinatario">
            @if ($inmueble)
                <div>{{ mb_strtoupper(trim($direccion.' - '.$inmueble->nomenclaturaOSugerida(), ' -')) }}</div>
                <div>{{ mb_strtoupper(trim($codigoPostal.' '.$municipio)) }}</div>
            @endif
        </div>

        <div class="fecha-carta">{{ $lugarCarta !== '' ? $lugarCarta.', ' : '' }}{{ $fechaCarta }}</div>
        <div class="saludo">Sr./a. Propietario/a:</div>

        <p>
            De conformidad con lo dispuesto en el artículo 16.2 de la vigente Ley de Propiedad Horizontal, por la
            presente se le convoca para que asista a la <span class="dato">Junta General {{ $tipo }}</span> que se
            celebrará el próximo día <span class="dato">{{ $fecha }}</span> a las <span class="dato">{{ $horaPrimera }}</span>
            horas en primera convocatoria, o de no concurrir el quórum necesario, a las
            <span class="dato">{{ $horaSegunda }}</span> horas en segunda,@if ($oficina !== '') en <span class="dato">{{ $oficina }}</span>,@endif
            @if ($localidadReunion !== '') en la población de <span class="dato">{{ $localidadReunion }}</span>,@endif
            con el fin de tratar el siguiente:
        </p>

        <div class="titulo">ORDEN DEL DÍA</div>
        <div class="puntos">
            @foreach ($puntos as $punto)
                <div>{{ $loop->iteration }}. {{ $punto }}</div>
            @endforeach
        </div>

        <p>
            En el caso de que los propietarios con recibos pendientes, al inicio de la Junta, no hubieran acreditado
            suficientemente el abono de las cantidades adeudadas, haber efectuado la impugnación de las mismas, o
            procedido a la consignación judicial o notarial de la suma adeudada, se les advierte que podrán participar en
            las deliberaciones, si bien no tendrán derecho a voto.
        </p>
        <p>
            Dada la importancia de los temas a tratar, rogamos su asistencia a esta Junta y, en el caso de no serle
            posible, le agradeceremos haga uso de la autorización que a este fin se une a la presente convocatoria.
        </p>

        <div class="firma">EL PRESIDENTE</div>

        <div class="delegacion">
            <div>Sr. Presidente de la Comunidad de Propietarios <span class="dato">{{ $comunidad }}</span></div>
            <div class="linea">Muy Sr. mío:</div>
            <p>
                Ante la imposibilidad de asistir a la Junta convocada por Vd., para el próximo
                <span class="dato">{{ $fecha }}</span> le participo haber otorgado mi representación para tal acto y a
                todos los efectos, al portador/a de la presente
            </p>
            <div class="linea">D/Dª_______________________________________________________________________________</div>
            <div class="atentamente">Atentamente:</div>
            <div class="firmado">
                Firmado:<br>
                Propietario del:
                @if ($inmueble)
                    <span class="dato">{{ $inmueble->nomenclaturaOSugerida() }}</span>
                @endif
            </div>
        </div>
    </div>
@endforeach
</body>
</html>
