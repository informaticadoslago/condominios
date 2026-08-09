<?php

namespace App\Http\Controllers;

use App\Models\Presupuesto;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PresupuestoConceptosPdfController extends Controller
{
    public function __invoke(Presupuesto $presupuesto): Response
    {
        abort_if($presupuesto->comunidad_id != session('comunidad_actual_id'), 403);

        $presupuesto->load(['comunidad', 'estado', 'periodicidad', 'conceptos.grupoDeReparto']);

        $pdf = Pdf::loadView('pdf.presupuesto-conceptos', [
            'presupuesto' => $presupuesto,
            'conceptos'   => $presupuesto->conceptos,
            'total'       => (float) $presupuesto->conceptos->sum('importe'),
        ])->setPaper('a4', 'portrait');

        $nombre = "presupuesto-conceptos-{$presupuesto->anho}-{$presupuesto->id}.pdf";

        return $pdf->stream($nombre);
    }
}
