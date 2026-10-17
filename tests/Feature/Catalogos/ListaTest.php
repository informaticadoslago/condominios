<?php

namespace Tests\Feature\Catalogos;

use App\Livewire\Catalogos\Lista;
use App\Models\TipoDirectivoComunidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ListaTest extends TestCase
{
    use RefreshDatabase;

    public function test_dar_de_baja_y_reactivar_piden_confirmacion_y_cambian_el_estado(): void
    {
        $tipo = TipoDirectivoComunidad::firstOrFail();

        $lista = Livewire::test(Lista::class, ['clave' => 'tipo-directivo-comunidades'])
            ->call('confirmarBaja', $tipo->id)
            ->assertDispatched('swalConfirm')
            ->call('ejecutarBaja', $tipo->id);

        $this->assertSame(TipoDirectivoComunidad::ESTADO_BAJA, $tipo->fresh()->estado_id);

        $lista->call('confirmarReactivar', $tipo->id)
            ->assertDispatched('swalConfirm')
            ->call('ejecutarReactivar', $tipo->id);

        $this->assertSame(TipoDirectivoComunidad::ESTADO_ACTIVO, $tipo->fresh()->estado_id);
    }

    public function test_un_catalogo_bloqueado_no_pide_confirmacion(): void
    {
        config(['catalogos.bloqueado-de-prueba' => [
            'modelo'    => TipoDirectivoComunidad::class,
            'titulo'    => 'Bloqueado de prueba',
            'bloqueado' => true,
        ]]);

        Livewire::test(Lista::class, ['clave' => 'bloqueado-de-prueba'])
            ->call('confirmarBaja', 1)
            ->call('confirmarReactivar', 1)
            ->assertNotDispatched('swalConfirm');
    }
}
