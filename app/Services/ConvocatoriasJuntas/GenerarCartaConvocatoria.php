<?php

namespace App\Services\ConvocatoriasJuntas;

use App\Models\ConvocatoriaJunta;
use App\Models\Inmueble;
use App\Models\Municipio;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;

/**
 * Genera el PDF de la carta de convocatoria: una carta por inmueble de la comunidad (sin
 * nombre del propietario, a la dirección del inmueble) y lo guarda en disco, con la fecha
 * de hoy como fecha de la carta. Regenerarla vuelve a leer los datos de ahora (orden del
 * día, horas…) y le pone la fecha del día.
 */
class GenerarCartaConvocatoria
{
    public function ejecutar(ConvocatoriaJunta $convocatoria): string
    {
        $fechaCarta = today();
        // La de la lista es para revisar e imprimir: va con la marca "EN PREPARACIÓN".
        $pdf = Pdf::loadHTML($this->html($convocatoria, $fechaCarta, enPreparacion: true))->setPaper('a4', 'portrait');

        Storage::disk(ConvocatoriaJunta::DISCO_CARTAS)->put($convocatoria->rutaCarta(), $pdf->output());
        $convocatoria->update(['fecha_carta' => $fechaCarta]);

        return $convocatoria->rutaCarta();
    }

    /**
     * La carta de un solo inmueble, la que se adjunta al correo (ver EnviarCartasConvocatoria):
     * no se guarda, se hace con los datos de ahora y con la fecha de la carta ya generada.
     * Sin la marca "EN PREPARACIÓN": es la que de verdad le llega al propietario.
     */
    public function pdfInmueble(ConvocatoriaJunta $convocatoria, Inmueble $inmueble): string
    {
        $inmueble->loadMissing('tipoInmueble');

        return Pdf::loadHTML($this->html($convocatoria, $convocatoria->fecha_carta ?? today(), collect([$inmueble])))
            ->setPaper('a4', 'portrait')
            ->output();
    }

    /**
     * HTML de las cartas, lo que se pasa a PDF. Aparte para poder comprobar su texto: el de
     * un PDF con párrafos justificados no se puede volver a leer entero. Sin $inmuebles,
     * una carta por cada inmueble de la comunidad. $enPreparacion pone la marca de agua.
     */
    public function html(ConvocatoriaJunta $convocatoria, CarbonInterface $fechaCarta, $inmuebles = null, bool $enPreparacion = false): string
    {
        $convocatoria->load(['tipoJunta', 'lineasOrdenDelDia', 'comunidad']);

        $comunidad = $convocatoria->comunidad;
        $domicilio = $comunidad->persona->domicilio;
        $municipio = $domicilio?->municipio_id ? $this->limpiar(Municipio::find($domicilio->municipio_id)?->nombre) : '';

        $inmuebles ??= Inmueble::ordenarPorPlanta($comunidad->inmuebles()->with('tipoInmueble')->get());

        return view('pdf.convocatoria-junta', [
            'comunidad'        => $this->limpiar($comunidad->nombre),
            'direccion'        => $this->direccionDelEdificio($domicilio?->direccion1, $domicilio?->numero),
            'codigoPostal'     => $this->limpiar($domicilio?->codigo_postal),
            'municipio'        => $municipio,
            // Donde se firma la carta: la localidad del administrador o, si no la tiene, la del edificio.
            'lugarCarta'       => $this->limpiar($comunidad->administrador_localidad) ?: $municipio,
            'fechaCarta'       => $this->fechaEnLetra($fechaCarta),
            'tipo'             => $convocatoria->tipoJunta->descripcion,
            'fecha'            => $this->fechaEnLetra($convocatoria->fecha),
            'horaPrimera'      => substr($convocatoria->hora_primera_convocatoria, 0, 5),
            'horaSegunda'      => substr($convocatoria->hora_segunda_convocatoria, 0, 5),
            // La coma ya la pone la carta detrás: una que venga tecleada al final sobra.
            'oficina'          => rtrim($this->limpiar($comunidad->administrador_oficina_reunion), ', '),
            'localidadReunion' => $this->limpiar($comunidad->administrador_localidad),
            'puntos'           => $convocatoria->lineasOrdenDelDia->pluck('texto')->all(),
            // Sin inmuebles sale una sola carta, sin destinatario.
            'inmuebles'        => $inmuebles->isEmpty() ? collect([null]) : $inmuebles,
            'enPreparacion'    => $enPreparacion,
        ])->render();
    }

    /** "PARAGUAY, 19": calle y número como en el membrete de la plantilla. */
    private function direccionDelEdificio(?string $calle, ?string $numero): string
    {
        return implode(', ', array_filter([$this->limpiar($calle), $this->limpiar($numero)], fn ($parte) => $parte !== ''));
    }

    private function fechaEnLetra(CarbonInterface $fecha): string
    {
        return $fecha->locale('es')->translatedFormat('j \d\e F \d\e Y');
    }

    /** Los nombres de municipio del catálogo vienen con espacios y salto de línea detrás. */
    private function limpiar(?string $texto): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $texto));
    }
}
