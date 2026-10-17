<?php

namespace App\Http\Controllers;

use App\Models\ConvocatoriaJunta;
use App\Models\Inmueble;
use App\Services\ConvocatoriasJuntas\GenerarCartaConvocatoria;
use Illuminate\Http\Response;

/**
 * La carta de un solo inmueble, la que se adjunta al correo (el ojo del modal de envío).
 * No se guarda: se hace al vuelo igual que el adjunto (ver GenerarCartaConvocatoria::pdfInmueble).
 */
class ConvocatoriaJuntaCartaInmuebleController extends Controller
{
    public function __invoke(ConvocatoriaJunta $convocatoriaJunta, Inmueble $inmueble, GenerarCartaConvocatoria $generar): Response
    {
        abort_if($convocatoriaJunta->comunidad_id != session('comunidad_actual_id'), 403);
        abort_if($inmueble->comunidad_id != $convocatoriaJunta->comunidad_id, 404);

        return response($generar->pdfInmueble($convocatoriaJunta, $inmueble), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="convocatoria-'.$convocatoriaJunta->fecha->format('Y-m-d').'.pdf"',
            'Cache-Control'       => 'no-store',
        ]);
    }
}
