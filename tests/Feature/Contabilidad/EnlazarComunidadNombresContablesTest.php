<?php

namespace Tests\Feature\Contabilidad;

use App\Livewire\Comunidades\Lista;
use App\Models\Comunidad;
use App\Models\User;
use Database\Seeders\DemoComunidadSeeder;
use Database\Seeders\PlanCuentasComunidadesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Enlazar una comunidad con la contabilidad exige antes el nombre contable de sus
 * cuentas del banco: es lo que se lee en el mayor y sin él la cuenta no llega.
 */
class EnlazarComunidadNombresContablesTest extends TestCase
{
    use RefreshDatabase;

    private Comunidad $comunidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanCuentasComunidadesSeeder::class);

        $this->comunidad = (new DemoComunidadSeeder())->generar()['edificio1'];
    }

    private function usuario(): User
    {
        return User::forceCreate([
            'login'    => 'tester',
            'email'    => 'tester@example.test',
            'password' => bcrypt('secreto'),
        ]);
    }

    public function test_si_falta_el_nombre_contable_pide_rellenarlo_y_no_enlaza(): void
    {
        $cuenta = $this->comunidad->cuentasBancarias()->first();

        Livewire::actingAs($this->usuario())->test(Lista::class)
            ->call('enlazarContabilidad', $this->comunidad->id)
            ->assertSet('abrirNombresContables', true)
            // Que el campo se pinta. Que el cursor aparezca dentro no lo puede ver esto:
            // aquí no hay navegador ni Alpine, solo el HTML.
            ->assertSeeHtml('id="nc-'.$cuenta->id.'"')
            ->assertSeeHtml('wire:model="nombresContables.'.$cuenta->id.'"');

        $this->assertNull($this->comunidad->fresh()->empresa_contable_id);
    }

    public function test_dejarlo_en_blanco_no_pasa(): void
    {
        $cuenta = $this->comunidad->cuentasBancarias()->first();

        Livewire::actingAs($this->usuario())->test(Lista::class)
            ->call('enlazarContabilidad', $this->comunidad->id)
            ->set("nombresContables.{$cuenta->id}", '')
            ->call('guardarNombresYEnlazar')
            ->assertHasErrors("nombresContables.{$cuenta->id}");

        $this->assertNull($this->comunidad->fresh()->empresa_contable_id);
    }

    public function test_al_rellenarlo_enlaza_y_la_cuenta_estrena_su_subcuenta(): void
    {
        $cuenta = $this->comunidad->cuentasBancarias()->first();

        Livewire::actingAs($this->usuario())->test(Lista::class)
            ->call('enlazarContabilidad', $this->comunidad->id)
            ->set("nombresContables.{$cuenta->id}", 'Banco Santander c/c')
            ->call('guardarNombresYEnlazar')
            ->assertHasNoErrors()
            ->assertSet('abrirNombresContables', false);

        $this->assertNotNull($this->comunidad->fresh()->empresa_contable_id);

        $cuenta->refresh();
        $this->assertSame('Banco Santander c/c', $cuenta->nombre_contable);
        $this->assertSame('57200001', $cuenta->cuenta_contable);
    }

    public function test_si_ya_tiene_nombre_enlaza_sin_preguntar(): void
    {
        $cuenta = $this->comunidad->cuentasBancarias()->first();
        $cuenta->update(['nombre_contable' => 'Banco de siempre']);

        Livewire::actingAs($this->usuario())->test(Lista::class)
            ->call('enlazarContabilidad', $this->comunidad->id)
            ->assertSet('abrirNombresContables', false);

        $this->assertNotNull($this->comunidad->fresh()->empresa_contable_id);
        $this->assertSame('57200001', $cuenta->fresh()->cuenta_contable);
    }

    public function test_una_comunidad_sin_cuentas_bancarias_enlaza_igual(): void
    {
        $this->comunidad->cuentasBancarias()->delete();

        Livewire::actingAs($this->usuario())->test(Lista::class)
            ->call('enlazarContabilidad', $this->comunidad->id)
            ->assertSet('abrirNombresContables', false);

        $this->assertNotNull($this->comunidad->fresh()->empresa_contable_id);
    }
}
