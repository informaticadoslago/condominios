<?php

namespace Tests\Feature\Contabilidad;

use App\Livewire\CuentasContables\Lista;
use App\Models\CuentaContable;
use App\Models\TipoCuentaContable;
use Database\Seeders\PlanCuentasComunidadesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La lista de cuentas arranca plegada —solo raíces— y cada rama se abre con su
 * chevron, un nivel por clic: hijas primero, nietas después.
 */
class ArbolCuentasContablesTest extends TestCase
{
    use RefreshDatabase;

    private CuentaContable $grupo;
    private CuentaContable $subgrupo;
    private CuentaContable $cuenta;
    private CuentaContable $subcuenta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanCuentasComunidadesSeeder::class);

        // 6 › 62 salen de la plantilla; debajo, una cuenta y una subcuenta propias.
        $this->grupo     = CuentaContable::where('codigo', '6')->whereNull('empresa_contable_id')->firstOrFail();
        $this->subgrupo  = CuentaContable::where('codigo', '62')->whereNull('empresa_contable_id')->firstOrFail();
        $this->cuenta    = $this->crearCuenta('62100000', 'Arrendamientos y cánones');
        $this->subcuenta = $this->crearCuenta('62100001', 'Alquiler del local de la piscina');

        CuentaContable::recolgarPlan(null);
    }

    private function crearCuenta(string $codigo, string $nombre): CuentaContable
    {
        return CuentaContable::create([
            'empresa_contable_id'     => null,
            // Los grupos y subgrupos van sin tipo: no tienen naturaleza propia.
            'tipo_cuenta_contable_id' => strlen($codigo) <= 2 ? null : TipoCuentaContable::GASTO,
            'codigo'                  => $codigo,
            'nombre'                  => $nombre,
            'estado_id'               => CuentaContable::ESTADO_ACTIVO,
        ]);
    }

    public function test_de_entrada_solo_se_ven_los_grupos(): void
    {
        Livewire::test(Lista::class)
            ->set('lineasXPagina', '100')
            ->assertSee('Compras y gastos')
            ->assertDontSee('Servicios exteriores')
            ->assertDontSee('Arrendamientos y cánones');
    }

    public function test_cada_clic_despliega_un_nivel(): void
    {
        Livewire::test(Lista::class)
            ->set('lineasXPagina', '100')
            ->call('alternarRama', $this->grupo->id)
            ->assertSee('Servicios exteriores')
            ->assertDontSee('Arrendamientos y cánones')
            ->call('alternarRama', $this->subgrupo->id)
            ->assertSee('Arrendamientos y cánones')
            ->assertDontSee('Alquiler del local de la piscina')
            ->call('alternarRama', $this->cuenta->id)
            ->assertSee('Alquiler del local de la piscina')
            // Y plegar el grupo se lleva por delante toda la rama.
            ->call('alternarRama', $this->grupo->id)
            ->assertDontSee('Servicios exteriores')
            ->assertDontSee('Alquiler del local de la piscina');
    }

    public function test_buscando_la_lista_se_pinta_plana(): void
    {
        Livewire::test(Lista::class)
            ->set('lineasXPagina', '100')
            ->set('search', 'piscina')
            ->assertSee('Alquiler del local de la piscina');
    }

    public function test_cada_cuenta_busca_su_nivel_del_pgc(): void
    {
        // 7500 y 7501 son hermanas dentro del 750: ninguna cuelga de la otra.
        $this->assertSame(['750', '75', '7'], CuentaContable::codigosAncestros('75000000'));
        $this->assertSame(['750', '75', '7'], CuentaContable::codigosAncestros('75010000'));

        // La subcuenta sí cuelga de su cuenta de 4 cifras.
        $this->assertSame(['75000000', '750', '75', '7'], CuentaContable::codigosAncestros('75000001'));

        // Y los niveles del PGC, unos de otros, hasta el grupo, que no cuelga de nadie.
        $this->assertSame(['75', '7'], CuentaContable::codigosAncestros('750'));
        $this->assertSame(['7'], CuentaContable::codigosAncestros('75'));
        $this->assertSame([], CuentaContable::codigosAncestros('7'));
    }

    public function test_el_plan_de_la_plantilla_queda_colgado_del_pgc(): void
    {
        $porCodigo = CuentaContable::whereNull('empresa_contable_id')->get()->keyBy('codigo');

        // 7 › 75 › 750, y de ahí las dos cuentas, hermanas.
        $this->assertSame($porCodigo['7']->id, $porCodigo['75']->cuenta_padre_id);
        $this->assertSame($porCodigo['75']->id, $porCodigo['750']->cuenta_padre_id);
        $this->assertSame($porCodigo['750']->id, $porCodigo['75000000']->cuenta_padre_id);
        $this->assertSame($porCodigo['750']->id, $porCodigo['75010000']->cuenta_padre_id);

        // Sin fila 120 ni 129, las del subgrupo 12 se quedan colgando del 12.
        foreach (['12000000', '12100000', '12900000'] as $codigo) {
            $this->assertSame($porCodigo['12']->id, $porCodigo[$codigo]->cuenta_padre_id, $codigo);
        }
    }

    public function test_sin_el_padre_la_cuenta_cuelga_del_abuelo(): void
    {
        // Existe el subgrupo 44 pero no la cuenta 441, así que 4411 se cuelga de él.
        $subgrupo = $this->crearCuenta('44', 'Acreedores varios');
        $nieta    = $this->crearCuenta('44110000', 'Acreedores, efectos comerciales a pagar');

        $this->assertSame($subgrupo->id, CuentaContable::padreDe($nieta->codigo, null)?->id);
    }

    public function test_el_nivel_intermedio_creado_despues_se_lleva_a_sus_hijas(): void
    {
        $subgrupo = $this->crearCuenta('44', 'Acreedores varios');
        $nieta    = $this->crearCuenta('44110000', 'Acreedores, efectos comerciales a pagar');

        CuentaContable::recolgarPlan(null);
        $this->assertSame($subgrupo->id, $nieta->fresh()->cuenta_padre_id);

        // Al abrir la cuenta 441, lo que colgaba del subgrupo pasa a colgar de ella.
        $cuenta = $this->crearCuenta('441', 'Acreedores, efectos comerciales a pagar');

        CuentaContable::recolgarPlan(null);

        $this->assertSame($cuenta->id, $nieta->fresh()->cuenta_padre_id);
        $this->assertSame($subgrupo->id, $cuenta->fresh()->cuenta_padre_id);
    }
}
