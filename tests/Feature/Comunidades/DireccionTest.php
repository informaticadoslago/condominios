<?php

namespace Tests\Feature\Comunidades;

use App\Livewire\Comunidades\Formulario;
use App\Models\Municipio;
use App\Models\Provincia;
use Database\Seeders\DemoComunidadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** La dirección del edificio se guarda como domicilio de la persona de la comunidad. */
class DireccionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guarda_la_direccion_y_el_codigo_postal_rellena_la_provincia(): void
    {
        $comunidad = (new DemoComunidadSeeder())->generar()['edificio1'];
        $pontevedra = Provincia::where('codigo', '36')->firstOrFail();
        $vigo = Municipio::where('nombre', 'like', 'Vigo%')->firstOrFail();

        Livewire::test(Formulario::class)
            ->call('editar', $comunidad->id)
            // La comunidad demo trae cuenta con IBAN pero sin entidad bancaria (ver AdministradorTest).
            ->set('formulario.iban', null)
            ->set('formulario.codigo_postal', '36204')
            ->assertSet('formulario.provincia_id', $pontevedra->id)
            ->set('formulario.municipio_id', $vigo->id)
            ->set('formulario.direccion1', 'PARAGUAY')
            ->set('formulario.numero', '19')
            ->call('guardar')
            ->assertHasNoErrors();

        $domicilio = $comunidad->persona->fresh()->domicilio;
        $this->assertSame('PARAGUAY', $domicilio->direccion1);
        $this->assertSame('19', $domicilio->numero);
        $this->assertSame('36204', $domicilio->codigo_postal);
        $this->assertSame($vigo->id, $domicilio->municipio_id);

        Livewire::test(Formulario::class)
            ->call('editar', $comunidad->id)
            ->assertSet('formulario.direccion1', 'PARAGUAY')
            ->assertSet('formulario.municipio_id', $vigo->id);
    }

    public function test_cambiar_de_provincia_vacia_el_municipio(): void
    {
        $comunidad = (new DemoComunidadSeeder())->generar()['edificio1'];
        $vigo = Municipio::where('nombre', 'like', 'Vigo%')->firstOrFail();
        $otra = Provincia::where('id', '<>', $vigo->provincia_id)->firstOrFail();

        Livewire::test(Formulario::class)
            ->call('editar', $comunidad->id)
            ->set('formulario.provincia_id', $vigo->provincia_id)
            ->set('formulario.municipio_id', $vigo->id)
            ->set('formulario.provincia_id', $otra->id)
            ->assertSet('formulario.municipio_id', null);
    }
}
