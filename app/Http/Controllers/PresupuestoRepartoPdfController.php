<?php

namespace App\Http\Controllers;

use App\Models\Presupuesto;
use App\Services\Presupuestos\CalculadorReparto;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PresupuestoRepartoPdfController extends Controller
{
    public function __invoke(Presupuesto $presupuesto, CalculadorReparto $calculador): Response
    {
        abort_if($presupuesto->comunidad_id != session('comunidad_actual_id'), 403);

        $presupuesto->load(['comunidad', 'estado', 'periodicidad', 'conceptos.grupoDeReparto.inmuebles']);

        $reparto = $calculador->calcular($presupuesto);

        $orientacion = count($reparto['fechasPagos']) > 7 ? 'landscape' : 'portrait';

        $pdf = Pdf::loadView('pdf.presupuesto-reparto', [
            'presupuesto'        => $presupuesto,
            'datosPagoCompletos' => $reparto['datosPagoCompletos'],
            'totalPresupuesto'   => $reparto['total'],
            'grupos'             => $reparto['grupos'],
            'global'             => $reparto['global'],
            'fechasPagos'        => $reparto['fechasPagos'],
        ])->setPaper('a4', $orientacion);

        $nombre = "presupuesto-{$presupuesto->anho}-{$presupuesto->id}.pdf";

        return $pdf->stream($nombre);
    }
}
