<?php

namespace Tests\Feature\ConvocatoriasJuntas;

use App\Livewire\ConvocatoriasJuntas\Formulario;
use App\Models\Comunidad;
use App\Models\ConvocatoriaJunta;
use App\Models\LineaOrdenDelDia;
use App\Models\TipoJunta;
use Database\Seeders\DemoComunidadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormularioTest extends TestCase
{
    use RefreshDatabase;

    private Comunidad $comunidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comunidad = (new DemoComunidadSeeder())->generar()['edificio1'];
        session(['comunidad_actual_id' => $this->comunidad->id]);
    }

    private function textosDe(ConvocatoriaJunta $convocatoria): array
    {
        return $convocatoria->lineasOrdenDelDia()->pluck('texto')->all();
    }

    public function test_importar_anade_los_puntos_detras_de_los_que_habia_y_quita_los_vacios(): void
    {
        Livewire::test(Formulario::class)
            ->call('crear')
            ->call('anadirLinea')
            ->set('lineas.0.texto', 'Lectura del acta anterior.')
            ->call('anadirLinea')
            ->call('abrirImportar')
            ->assertSet('importarAbierto', true)
            ->set('importarTexto', "1. Cuentas del ejercicio. Decisiones a\nadoptar.\n2. Ruegos y preguntas.")
            ->call('importarLineas')
            ->assertSet('importarAbierto', false)
            ->assertSet('lineas', fn ($lineas) => array_column($lineas, 'texto') === [
                'Lectura del acta anterior.',
                'Cuentas del ejercicio. Decisiones a adoptar.',
                'Ruegos y preguntas.',
            ]);
    }

    public function test_importar_un_texto_sin_puntos_avisa_y_no_cierra(): void
    {
        Livewire::test(Formulario::class)
            ->call('abrirImportar')
            ->set('importarTexto', "  \n ")
            ->call('importarLineas')
            ->assertHasErrors('importarTexto')
            ->assertSet('importarAbierto', true)
            ->assertSet('lineas', []);
    }

    public function test_guardar_crea_la_convocatoria_con_sus_puntos_en_el_orden_de_pantalla(): void
    {
        $componente = Livewire::test(Formulario::class)
            ->call('crear')
            ->set('tipo_junta_id', TipoJunta::EXTRAORDINARIA)
            ->set('fecha', '2026-11-20')
            ->set('hora_primera_convocatoria', '18:00')
            ->assertSet('hora_segunda_convocatoria', '18:30')
            ->call('abrirImportar')
            ->set('importarTexto', "1. Uno\n2. Dos\n3. Tres")
            ->call('importarLineas');

        $claves = array_column($componente->get('lineas'), 'clave');

        $componente->call('moverLineaAntesDe', $claves[2], $claves[0])
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('convocatoria-junta-guardada');

        $convocatoria = ConvocatoriaJunta::where('comunidad_id', $this->comunidad->id)->sole();

        $this->assertSame(TipoJunta::EXTRAORDINARIA, $convocatoria->tipo_junta_id);
        $this->assertSame('18:30:00', $convocatoria->hora_segunda_convocatoria);
        $this->assertSame(['Tres', 'Uno', 'Dos'], $this->textosDe($convocatoria));
        $this->assertSame([1, 2, 3], $convocatoria->lineasOrdenDelDia()->pluck('orden')->all());
    }

    public function test_modificar_conserva_los_puntos_que_siguen_y_borra_los_quitados(): void
    {
        $convocatoria = ConvocatoriaJunta::create([
            'comunidad_id'              => $this->comunidad->id,
            'tipo_junta_id'             => TipoJunta::ORDINARIA,
            'fecha'                     => '2026-11-20',
            'hora_primera_convocatoria' => '18:00',
        ]);
        $uno = LineaOrdenDelDia::create(['convocatoria_junta_id' => $convocatoria->id, 'orden' => 1, 'texto' => 'Uno']);
        $dos = LineaOrdenDelDia::create(['convocatoria_junta_id' => $convocatoria->id, 'orden' => 2, 'texto' => 'Dos']);

        Livewire::test(Formulario::class)
            ->call('editar', $convocatoria->id)
            ->assertSet('hora_segunda_convocatoria', '18:30')
            ->call('quitarLinea', 'l'.$uno->id)
            ->call('abrirImportar')
            ->set('importarTexto', 'Ruegos y preguntas')
            ->call('importarLineas')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(['Dos', 'Ruegos y preguntas'], $this->textosDe($convocatoria));
        $this->assertSame($dos->id, $convocatoria->lineasOrdenDelDia()->first()->id);
        $this->assertDatabaseMissing('lineas_orden_del_dia', ['id' => $uno->id]);
    }

    public function test_la_segunda_convocatoria_ha_de_ser_posterior_a_la_primera(): void
    {
        Livewire::test(Formulario::class)
            ->call('crear')
            ->set('fecha', '2026-11-20')
            ->set('hora_primera_convocatoria', '23:45')
            ->call('guardar')
            ->assertHasErrors(['hora_segunda_convocatoria' => 'after']);

        $this->assertDatabaseCount('convocatorias_juntas', 0);
    }
}
