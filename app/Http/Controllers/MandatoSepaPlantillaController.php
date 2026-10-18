<?php

namespace App\Http\Controllers;

use App\Models\Inmueble;
use App\Models\PersonaComunidad;
use App\Models\Propietario;
use App\Models\TipoDocumentoIdentificativo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Formulario de recogida de datos bancarios, para imprimir o mandar al propietario.
 *
 * El IBAN y el titular van siempre en blanco porque son justo lo que este papel viene a
 * recoger. El piso solo sale cuando se pide desde un inmueble concreto: sacado para una
 * persona, el mismo titular puede tener varios, y con uno impreso no valdría para los otros.
 */
class MandatoSepaPlantillaController extends Controller
{
    /** La del asistente del inmueble: a nombre del propietario que paga, sin piso. */
    public function persona(PersonaComunidad $personaComunidad): Response
    {
        abort_if($personaComunidad->comunidad_id != session('comunidad_actual_id'), 403);

        // Inline: se abre dentro de la ventana del wizard, desde donde se imprime o se
        // descarga para mandarlo por correo.
        return $this->pdf([['propietario' => $personaComunidad, 'piso' => null]]);
    }

    /**
     * La de las listas: una hoja por cada propietario (?propietarios=1,2) o inmueble
     * (?inmuebles=3,4) marcado y, sin ninguno, una sola en blanco para rellenar a mano.
     */
    public function lote(Request $request): Response
    {
        $comunidadId = session('comunidad_actual_id');

        if ($request->filled('propietarios')) {
            $hojas = Propietario::with('persona')
                ->whereIn('id', $this->ids($request->query('propietarios')))
                ->whereHas('persona', fn ($p) => $p->where('comunidad_id', $comunidadId))
                ->get()
                ->sortBy(fn (Propietario $propietario) => $propietario->persona->nombreCompleto, SORT_NATURAL | SORT_FLAG_CASE)
                ->map(fn (Propietario $propietario) => ['propietario' => $propietario->persona, 'piso' => null]);
        } elseif ($request->filled('inmuebles')) {
            $inmuebles = Inmueble::with(['tipoInmueble', 'formaPagoVigente.propietario.persona', 'propietarios.persona'])
                ->where('comunidad_id', $comunidadId)
                ->whereIn('id', $this->ids($request->query('inmuebles')))
                ->get();

            $hojas = Inmueble::ordenarPorPlanta($inmuebles)
                ->map(fn (Inmueble $inmueble) => [
                    'propietario' => $this->propietarioDelInmueble($inmueble),
                    'piso'        => $inmueble->nomenclaturaOSugerida(),
                ]);
        } else {
            $hojas = collect([['propietario' => null, 'piso' => null]]);
        }

        // Pedidos unos ids y ninguno de esta comunidad: mejor un 404 que una hoja en
        // blanco que parezca la de alguien.
        abort_if($hojas->isEmpty(), 404);

        return $this->pdf($hojas->values()->all());
    }

    /**
     * A nombre de quien paga los recibos del inmueble, que es quien firma el mandato; si
     * aún no tiene forma de pago, el titular con más cuota. Sin propietarios, en blanco.
     */
    private function propietarioDelInmueble(Inmueble $inmueble): ?PersonaComunidad
    {
        return $inmueble->formaPagoVigente?->propietario?->persona
            ?? $inmueble->propietarios->sortByDesc('pivot.cuota_percent')->first()?->persona;
    }

    /** @return list<int> */
    private function ids(?string $lista): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $lista))));
    }

    /** @param list<array{propietario: ?PersonaComunidad, piso: ?string}> $hojas */
    private function pdf(array $hojas): Response
    {
        $tipos = $this->tiposDocumento();

        $hojas = array_map(fn (array $hoja) => $hoja + [
            'tiposDocumento' => array_map(fn (TipoDocumentoIdentificativo $tipo) => [
                'nombre'  => $tipo->nombre,
                'marcado' => $hoja['propietario'] && $tipo->id == $hoja['propietario']->tipo_documento_id,
            ], $tipos),
        ], $hojas);

        return Pdf::loadView('mandatos-sepa.plantilla', ['hojas' => $hojas])
            ->stream('formulario-datos-bancarios.pdf');
    }

    /**
     * Las cuatro casillas del impreso; en cada hoja sale marcada la del propietario
     * (ninguna en la hoja en blanco). El CIF no entra: quien firma es una persona física.
     *
     * @return list<TipoDocumentoIdentificativo>
     */
    private function tiposDocumento(): array
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
            ->all();
    }
}
