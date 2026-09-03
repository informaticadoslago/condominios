<?php

namespace App\Http\Controllers;

use App\Models\Remesa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * El listado de recibos de una remesa para revisarla: inmueble, propietario e importe,
 * con el total al pie. Inline, para verla e imprimirla o guardarla desde el propio
 * visor del navegador.
 */
class RemesaInformePdfController extends Controller
{
    public function __invoke(Remesa $remesa): Response
    {
        abort_unless($remesa->comunidad_id == session('comunidad_actual_id'), 404);

        $lineas = $remesa->lineas()
            ->with(['recibo.inmueble.tipoInmueble', 'recibo.propietario.persona'])
            ->get()
            ->sortBy(fn ($linea) => sprintf('%03s-%03s',
                $linea->recibo?->inmueble?->planta,
                $linea->recibo?->inmueble?->puerta,
            ));

        $pdf = Pdf::loadView('pdf.remesa-informe', [
            'remesa' => $remesa,
            'lineas' => $lineas,
            'total'  => $lineas->sum('importe'),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("remesa-{$remesa->referencia}.pdf");
    }
}
