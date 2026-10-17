<?php

namespace App\Http\Controllers;

use App\Models\ConvocatoriaJunta;
use App\Services\ConvocatoriasJuntas\GenerarCartaConvocatoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Carta de convocatoria en PDF, en pestaña nueva. Enseña la que ya está generada; si no hay
 * ninguna, o con ?regenerar=1 (Mayúsculas + clic en la lista), la genera antes con los datos
 * de ahora y la fecha de hoy.
 */
class ConvocatoriaJuntaCartaController extends Controller
{
    public function __invoke(Request $request, ConvocatoriaJunta $convocatoriaJunta, GenerarCartaConvocatoria $generar): StreamedResponse
    {
        abort_if($convocatoriaJunta->comunidad_id != session('comunidad_actual_id'), 403);

        if ($request->boolean('regenerar') || ! $convocatoriaJunta->tieneCarta()) {
            $generar->ejecutar($convocatoriaJunta);
        }

        return Storage::disk(ConvocatoriaJunta::DISCO_CARTAS)->response(
            $convocatoriaJunta->rutaCarta(),
            'convocatoria-'.$convocatoriaJunta->fecha->format('Y-m-d').'.pdf',
            // Sin caché: la misma URL enseña otra carta en cuanto se regenera.
            ['Content-Type' => 'application/pdf', 'Cache-Control' => 'no-store'],
            'inline',
        );
    }
}
