<?php

namespace App\Http\Controllers;

use App\Models\EmpresaContable;
use App\Services\Contabilidad\InformeMovimientos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * El informe de movimientos en papel: el mismo que está en pantalla, con el rango que
 * se le haya puesto allí.
 *
 * A4 apaisado, que es lo que necesita el año entero: en vertical las doce columnas de
 * meses más el total no dejan sitio para el nombre de la cuenta.
 */
class MovimientosContablesPdfController extends Controller
{
    public function __invoke(Request $request, InformeMovimientos $informe): Response
    {
        $rango = $request->validate([
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
        ]);

        // La empresa es la de la sesión, no la que venga en la URL: el rango se pide, el
        // dueño de los números no.
        $empresaContable = EmpresaContable::findOrFail(session('empresa_contable_actual_id'));

        $pdf = Pdf::loadView('pdf.movimientos-contables', [
            'empresaContable' => $empresaContable,
            'desde'           => $rango['desde'],
            'hasta'           => $rango['hasta'],
        ] + $informe->generar($empresaContable->id, $rango['desde'], $rango['hasta']))
            ->setPaper('a4', 'landscape');

        // Inline: se abre en otra pestaña y de ahí se imprime o se guarda.
        return $pdf->stream("movimientos-{$rango['desde']}-{$rango['hasta']}.pdf");
    }
}
