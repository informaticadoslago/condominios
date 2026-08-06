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

    private CuentaContable $abuela;
    private CuentaContable $madre;
    private CuentaContable $nieta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanCuentasComunidadesSeeder::class);

        $this->abuela = $this->crearCuenta('62200000', 'Reparaciones ascensor');
        $this->madre  = $this->crearCuenta('62200001', 'Mantenimiento', $this->abuela);
        $this->nieta  = $this->crearCuenta('62200002', 'Revisión anual', $this->madre);
    }

    private function crearCuenta(string $codigo, string $nombre, ?CuentaContable $padre = null): CuentaContable
    {
        return CuentaContable::create([
            'empresa_contable_id'     => null,
            'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO,
            'cuenta_padre_id'         => $padre?->id,
            'codigo'                  => $codigo,
            'nombre'                  => $nombre,
            'estado_id'               => CuentaContable::ESTADO_ACTIVO,
        ]);
    }

    public function test_de_entrada_solo_se_ven_las_raices(): void
    {
        Livewire::test(Lista::class)
            ->set('lineasXPagina', '100')
            ->assertSee('Reparaciones ascensor')
            ->assertDontSee('Mantenimiento')
            ->assertDontSee('Revisión anual');
    }

    public function test_cada_clic_despliega_un_nivel(): void
    {
        Livewire::test(Lista::class)
            ->set('lineasXPagina', '100')
            ->call('alternarRama', $this->abuela->id)
            ->assertSee('Mantenimiento')
            ->assertDontSee('Revisión anual')
            ->call('alternarRama', $this->madre->id)
            ->assertSee('Revisión anual')
            ->call('alternarRama', $this->abuela->id)
            ->assertDontSee('Mantenimiento')
            ->assertDontSee('Revisión anual');
    }

    public function test_buscando_la_lista_se_pinta_plana(): void
    {
        Livewire::test(Lista::class)
            ->set('lineasXPagina', '100')
            ->set('search', 'Revisión')
            ->assertSee('Revisión anual');
    }
}
