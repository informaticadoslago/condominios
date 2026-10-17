<?php

namespace Tests\Feature\Inmuebles;

use App\Livewire\Inmuebles\Crear\Steps\DatosStep;
use App\Models\Borrador;
use App\Models\Comunidad;
use App\Models\Inmueble;
use App\Models\TipoInmueble;
use App\Models\TipoOcupacion;
use App\Models\User;
use Database\Seeders\DemoComunidadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** La ficha propone la nomenclatura (la de las cartas) y la deja cambiar. */
class NomenclaturaTest extends TestCase
{
    use RefreshDatabase;

    private Comunidad $comunidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comunidad = (new DemoComunidadSeeder())->generar()['edificio1'];
        session(['comunidad_actual_id' => $this->comunidad->id]);
        $this->actingAs(User::forceCreate(['login' => 'tester', 'email' => 'tester@example.test', 'password' => bcrypt('secreto')]));
    }

    private function paso(array $estado = [])
    {
        return Livewire::test(DatosStep::class, $estado + [
            'comunidad_id'     => $this->comunidad->id,
            'tipo_inmueble_id' => TipoInmueble::PISO,
            'ocupacion_id'     => TipoOcupacion::PROPIETARIO,
        ]);
    }

    public function test_la_propuesta_sigue_a_planta_y_puerta_mientras_no_se_cambie_a_mano(): void
    {
        $this->paso()
            ->set('planta', '3')
            ->assertSet('nomenclatura', 'Piso 3º')
            ->set('puerta', 'A')
            ->assertSet('nomenclatura', '3ºA')
            ->set('planta', '0')
            ->assertSet('nomenclatura', 'Bajo A')
            ->set('nomenclatura', 'Bajo izquierda')
            ->set('puerta', 'B')
            ->assertSet('nomenclatura', 'Bajo izquierda');
    }

    public function test_se_guarda_en_el_borrador_y_vacia_se_guarda_la_propuesta(): void
    {
        $this->paso()
            ->set('planta', '1')
            ->set('puerta', 'A')
            ->set('coeficiente', '10')
            ->set('nomenclatura', '')
            ->call('submit')
            ->assertHasNoErrors();

        $borrador = Borrador::find(session('inmueble_borrador_id'));
        $this->assertSame('1ºA', $borrador->payload['datos']['nomenclatura']);
    }

    public function test_al_editar_se_carga_la_que_tenia(): void
    {
        $inmueble = Inmueble::create([
            'comunidad_id'     => $this->comunidad->id,
            'ocupacion_id'     => TipoOcupacion::PROPIETARIO,
            'tipo_inmueble_id' => TipoInmueble::PISO,
            'planta'           => '1',
            'puerta'           => 'A',
            'nomenclatura'     => 'Primero A',
            'coeficiente'      => 10,
        ]);

        Livewire::test(DatosStep::class, ['inmuebleId' => $inmueble->id])
            ->assertSet('nomenclatura', 'Primero A')
            ->set('puerta', 'B')
            ->assertSet('nomenclatura', 'Primero A');
    }

    public function test_sugerencias(): void
    {
        $this->assertSame('1ºA', Inmueble::sugerirNomenclatura('Piso', '1', 'A'));
        $this->assertSame('Bajo', Inmueble::sugerirNomenclatura('Piso', '0', ''));
        $this->assertSame('Piso 3º', Inmueble::sugerirNomenclatura('Piso', '3', null));
        $this->assertSame('4º IZQDA', Inmueble::sugerirNomenclatura('Piso', '4', 'IZQDA'));
        $this->assertSame('Garaje -1º', Inmueble::sugerirNomenclatura('Garaje', '-1', null));
        $this->assertSame('ÁTICO B', Inmueble::sugerirNomenclatura('Piso', 'ÁTICO', 'B'));
        $this->assertSame('', Inmueble::sugerirNomenclatura('Piso', null, null));
    }
}
