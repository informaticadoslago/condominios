<?php

namespace Tests\Feature\Contabilidad;

use App\Livewire\Propietarios\Lista;
use App\Models\Comunidad;
use App\Models\EmpresaContable;
use App\Models\PersonaComunidad;
use App\Models\Propietario;
use App\Models\User;
use Database\Seeders\DemoComunidadSeeder;
use Database\Seeders\PlanCuentasComunidadesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Once;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El alta a mano en contabilidad de los propietarios que ya existían cuando se enlazó la
 * comunidad, desde la lista y en lote.
 */
class EnlazarPropietariosTest extends TestCase
{
    use RefreshDatabase;

    private Comunidad $comunidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanCuentasComunidadesSeeder::class);

        $this->comunidad = (new DemoComunidadSeeder())->generar()['edificio1'];

        $user = User::forceCreate([
            'login' => 'tester', 'email' => 'tester@example.test', 'password' => bcrypt('secreto'),
        ]);
        $this->actingAs($user);
        session(['comunidad_actual_id' => $this->comunidad->id]);
    }

    private function enlazarComunidad(): void
    {
        $empresa = EmpresaContable::create([
            'cif' => 'H12345678', 'razon_social' => 'Comunidad de prueba',
        ]);

        $this->comunidad->update(['empresa_contable_id' => $empresa->id]);

        // contabilidad_activa() lee la comunidad una sola vez por petición. En un test
        // todo ocurre dentro de la misma, así que hay que olvidar lo memoizado para que
        // lo siguiente sea de verdad una petición nueva.
        Once::flush();
    }

    private function crearPropietario(string $documento): Propietario
    {
        $persona = PersonaComunidad::create([
            'comunidad_id'             => $this->comunidad->id,
            'nombre'                   => 'Vecino',
            'apellido1'                => $documento,
            'documento_identificativo' => $documento,
        ]);

        return Propietario::create(['persona_comunidad_id' => $persona->id]);
    }

    public function test_la_accion_enlaza_los_marcados_y_deja_el_resto(): void
    {
        // Existían antes de que la comunidad llevara contabilidad: sin cuenta.
        $uno = $this->crearPropietario('11111111H');
        $dos = $this->crearPropietario('22222222J');

        $this->enlazarComunidad();

        Livewire::test(Lista::class)
            ->set('seleccionados', [(string) $uno->id])
            ->call('enlazarContabilidad');

        $this->assertSame('43000001', $uno->fresh()->cuenta_contable);
        $this->assertNull($dos->fresh()->cuenta_contable);
    }

    public function test_sin_nada_marcado_enlaza_todo_lo_filtrado_y_no_repite_a_los_que_ya_tienen(): void
    {
        $uno = $this->crearPropietario('11111111H');
        $dos = $this->crearPropietario('22222222J');

        $this->enlazarComunidad();

        Livewire::test(Lista::class)->call('enlazarContabilidad');

        $this->assertSame('43000001', $uno->fresh()->cuenta_contable);
        $this->assertSame('43000002', $dos->fresh()->cuenta_contable);

        // Segunda pasada: ya están todos, no se les toca ni se gastan correlativos.
        $tres = $this->crearPropietario('33333333P');

        Livewire::test(Lista::class)->call('enlazarContabilidad');

        $this->assertSame('43000001', $uno->fresh()->cuenta_contable);
        $this->assertSame('43000003', $tres->fresh()->cuenta_contable);
    }

    public function test_la_seleccion_sobrevive_al_cambio_de_pagina(): void
    {
        foreach (range(1, 12) as $n) {
            $this->crearPropietario(str_pad((string) $n, 8, '0', STR_PAD_LEFT).'H');
        }

        $lista = Livewire::test(Lista::class)
            ->set('marcarTodosVisibles', true);

        $this->assertCount(10, $lista->get('seleccionados'));

        $lista->call('nextPage');

        $this->assertCount(10, $lista->get('seleccionados'));
    }

    public function test_el_menu_de_acciones_solo_sale_si_la_comunidad_lleva_contabilidad(): void
    {
        $this->crearPropietario('11111111H');

        $this->assertStringNotContainsString('fa-ellipsis-vertical', Livewire::test(Lista::class)->html());

        $this->enlazarComunidad();

        $this->assertStringContainsString('fa-ellipsis-vertical', Livewire::test(Lista::class)->html());
    }
}
