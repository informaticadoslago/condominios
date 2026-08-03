<?php

namespace Database\Seeders;

use App\Models\Comunidad;
use App\Models\ConceptoPresupuesto;
use App\Models\GrupoDeReparto;
use App\Models\Inmueble;
use App\Models\Persona;
use App\Models\Presupuesto;
use App\Models\TipoEstadoPresupuesto;
use App\Models\TipoInmueble;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Presupuesto anual de demo para el edificio 1 (el de los 6 pisos + 15 garajes, ver
 * DemoInmuebleSeeder): 3 grupos de reparto (General = todo el edificio, Escalera =
 * solo los pisos, Garaje = solo las plazas) y 9 partidas repartidas entre ellos.
 * Aparte de DatabaseSeeder: `php artisan db:seed --class=DemoPresupuestoSeeder`
 * (o mejor, `php artisan doslago:fakeseed`, que ya lo encadena).
 */
class DemoPresupuestoSeeder extends Seeder
{
    private const CIF_EDIFICIO_1 = 'H12345674';

    public function run(): void
    {
        $comunidad = $this->comunidadPorCif(self::CIF_EDIFICIO_1);

        if (! $comunidad) {
            $this->command?->warn('No existe ninguna comunidad con CIF '.self::CIF_EDIFICIO_1.' (¿has ejecutado DemoInmuebleSeeder antes?).');

            return;
        }

        $pisos   = Inmueble::where('comunidad_id', $comunidad->id)->where('tipo_inmueble_id', TipoInmueble::PISO)->get();
        $garajes = Inmueble::where('comunidad_id', $comunidad->id)->where('tipo_inmueble_id', TipoInmueble::GARAJE)->get();

        if ($pisos->isEmpty() || $garajes->isEmpty()) {
            $this->command?->warn('El edificio 1 todavía no tiene pisos/garajes (¿has ejecutado DemoInmuebleSeeder antes?).');

            return;
        }

        $grupoGeneral  = $this->crearGrupo($comunidad, 'General', $pisos->concat($garajes));
        $grupoEscalera = $this->crearGrupo($comunidad, 'Escalera', $pisos);
        $grupoGaraje   = $this->crearGrupo($comunidad, 'Garaje', $garajes);

        $presupuesto = Presupuesto::firstOrCreate(
            ['comunidad_id' => $comunidad->id, 'anho' => 2026],
            ['nombre' => 'Presupuesto 2026', 'estado_id' => TipoEstadoPresupuesto::PROVISIONAL]
        );

        $conceptos = [
            ['concepto' => 'Electricidad de la escalera', 'importe' => 850.00, 'grupo' => $grupoEscalera],
            ['concepto' => 'Electricidad del garaje', 'importe' => 420.00, 'grupo' => $grupoGaraje],
            ['concepto' => 'Seguro general del edificio', 'importe' => 1200.00, 'grupo' => $grupoGeneral],
            ['concepto' => 'Reparación del tejado', 'importe' => 5435.00, 'grupo' => $grupoGeneral],
            ['concepto' => 'Asesoría', 'importe' => 600.00, 'grupo' => $grupoGeneral],
            ['concepto' => 'Gastos bancarios', 'importe' => 90.00, 'grupo' => $grupoGeneral],
            ['concepto' => 'Mantenimiento puerta garaje', 'importe' => 180.00, 'grupo' => $grupoGaraje],
            ['concepto' => 'Mantenimiento ascensor', 'importe' => 950.00, 'grupo' => $grupoEscalera],
            // La 5ª de "5 partidas más" (el resto ya estaban pedidas explícitamente): encaja
            // con el grupo escalera igual que el ascensor y la electricidad de portal.
            ['concepto' => 'Limpieza de la escalera', 'importe' => 780.00, 'grupo' => $grupoEscalera],
        ];

        foreach ($conceptos as $datos) {
            ConceptoPresupuesto::updateOrCreate(
                ['presupuesto_id' => $presupuesto->id, 'concepto' => $datos['concepto']],
                ['importe' => $datos['importe'], 'grupo_de_reparto_id' => $datos['grupo']->id]
            );
        }

        $total = collect($conceptos)->sum('importe');
        $this->command?->info(
            "Presupuesto {$presupuesto->anho} de «{$comunidad->nombre}»: ".count($conceptos).' partidas, total '
            .number_format($total, 2, ',', '.').' €.'
        );
    }

    private function comunidadPorCif(string $cif): ?Comunidad
    {
        $persona = Persona::where('documento_identificativo', $cif)->first();

        return $persona ? Comunidad::where('persona_id', $persona->id)->first() : null;
    }

    /** Crea (o recupera) el grupo y sincroniza sus miembros con el reparto normalizado al 100%. */
    private function crearGrupo(Comunidad $comunidad, string $nombre, Collection $inmuebles): GrupoDeReparto
    {
        $grupo = GrupoDeReparto::firstOrCreate(['comunidad_id' => $comunidad->id, 'nombre' => $nombre]);

        $pivotes = [];
        foreach ($this->repartoNormalizado($inmuebles) as $inmuebleId => $coeficiente) {
            $pivotes[$inmuebleId] = ['coeficiente' => $coeficiente];
        }
        $grupo->inmuebles()->syncWithoutDetaching($pivotes);

        return $grupo;
    }

    /**
     * El coeficiente de cada inmueble (sobre el edificio entero) reescalado para que,
     * SOLO entre los miembros de este grupo, sume 100,00 exactos: 2 decimales, con el
     * remanente del redondeo cargado en el primero para que no se quede en 99,98/100,02.
     *
     * @return array<int, float> inmueble_id => coeficiente dentro del grupo
     */
    private function repartoNormalizado(Collection $inmuebles): array
    {
        $totalBase = (float) $inmuebles->sum('coeficiente');
        if ($totalBase <= 0) {
            return [];
        }

        $normalizado = $inmuebles->mapWithKeys(
            fn (Inmueble $i) => [$i->id => round(((float) $i->coeficiente / $totalBase) * 100, 2)]
        );

        $diferencia = round(100 - $normalizado->sum(), 2);
        if ($diferencia !== 0.0) {
            $primeraClave = $normalizado->keys()->first();
            $normalizado[$primeraClave] = round($normalizado[$primeraClave] + $diferencia, 2);
        }

        return $normalizado->all();
    }
}
