<?php

namespace App\Http\Controllers;

use App\Models\PersonaComunidad;
use App\Models\TipoDocumentoIdentificativo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Formulario de recogida de datos bancarios, para imprimir o mandar al propietario.
 *
 * Solo salen rellenados los datos del propietario. El IBAN y el titular van en blanco
 * porque son justo lo que este papel viene a recoger, y tampoco lleva el piso: el mismo
 * titular puede tener varios, y con uno impreso el documento no valdría para los otros.
 */
class MandatoSepaPlantillaController extends Controller
{
    public function __invoke(PersonaComunidad $personaComunidad): Response
    {
        abort_if($personaComunidad->comunidad_id != session('comunidad_actual_id'), 403);

        $pdf = Pdf::loadView('mandatos-sepa.plantilla', [
            'propietario'    => $personaComunidad,
            'tiposDocumento' => $this->tiposDocumento($personaComunidad),
        ]);

        // Inline: se abre dentro de la ventana del wizard, desde donde se imprime o se
        // descarga para mandarlo por correo.
        return $pdf->stream('formulario-datos-bancarios.pdf');
    }

    /**
     * Las cuatro casillas del impreso, con la del propietario ya marcada. El CIF no
     * entra: quien firma es una persona física.
     *
     * @return list<array{nombre: string, marcado: bool}>
     */
    private function tiposDocumento(PersonaComunidad $propietario): array
    {
        $ids = [
            TipoDocumentoIdentificativo::DOCUMENTO_NIF,
            TipoDocumentoIdentificativo::DOCUMENTO_NIE,
            TipoDocumentoIdentificativo::DOCUMENTO_NIFEU,
            TipoDocumentoIdentificativo::DOCUMENTO_PASAPORTE,
        ];

        return TipoDocumentoIdentificativo::whereIn('id', $ids)
            ->orderByRaw('FIELD(id, '.implode(',', $ids).')')
            ->get()
            ->map(fn ($tipo) => [
                'nombre'  => $tipo->nombre,
                'marcado' => $tipo->id == $propietario->tipo_documento_id,
            ])
            ->all();
    }
}
