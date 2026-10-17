<?php

namespace Tests\Feature\Comunidades;

use App\Livewire\Comunidades\Formulario;
use Database\Seeders\DemoComunidadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** La pestaña Administrador guarda el texto tal cual: minúsculas, paréntesis, «–», «º». */
class AdministradorTest extends TestCase
{
    use RefreshDatabase;

    private const OFICINA = 'Oficinas de Fincas Lar (Av. García Barbón, 22 – 1º),';

    public function test_guarda_y_recupera_los_datos_del_administrador_tal_cual(): void
    {
        $comunidad = (new DemoComunidadSeeder())->generar()['edificio1'];

        Livewire::test(Formulario::class)
            ->call('editar', $comunidad->id)
            // La comunidad demo trae cuenta con IBAN pero sin entidad bancaria (el catálogo
            // no se carga en pruebas): sin IBAN, la cuenta no se toca y no estorba.
            ->set('formulario.iban', null)
            ->set('formulario.administrador_nombre', 'María Pérez Núñez')
            ->set('formulario.administrador_oficina_reunion', self::OFICINA)
            ->set('formulario.administrador_localidad', 'Vigo')
            ->call('guardar')
            ->assertHasNoErrors();

        $comunidad->refresh();
        $this->assertSame('María Pérez Núñez', $comunidad->administrador_nombre);
        $this->assertSame(self::OFICINA, $comunidad->administrador_oficina_reunion);
        $this->assertSame('Vigo', $comunidad->administrador_localidad);

        Livewire::test(Formulario::class)
            ->call('editar', $comunidad->id)
            ->assertSet('formulario.administrador_oficina_reunion', self::OFICINA);
    }

    public function test_la_oficina_admite_hasta_255_caracteres(): void
    {
        $comunidad = (new DemoComunidadSeeder())->generar()['edificio1'];

        Livewire::test(Formulario::class)
            ->call('editar', $comunidad->id)
            ->set('formulario.iban', null)
            ->set('formulario.administrador_oficina_reunion', str_repeat('a', 256))
            ->call('guardar')
            ->assertHasErrors(['formulario.administrador_oficina_reunion' => 'max']);
    }
}
