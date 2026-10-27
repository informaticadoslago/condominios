<?php

namespace Tests\Feature\ConvocatoriasJuntas;

use App\Livewire\ConvocatoriasJuntas\Formulario;
use App\Models\Comunidad;
use App\Models\ConvocatoriaJunta;
use App\Models\LineaOrdenDelDia;
use App\Models\TipoDocumento;
use App\Models\TipoJunta;
use Database\Seeders\DemoComunidadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_los_documentos_subidos_se_suman_y_se_guardan_con_la_convocatoria(): void
    {
        Storage::fake(config('documentos.disco'));

        Livewire::test(Formulario::class)
            ->call('crear')
            ->set('fecha', '2026-11-20')
            ->set('hora_primera_convocatoria', '18:00')
            ->set('ficheros', [UploadedFile::fake()->create('cuentas-2025.pdf', 20, 'application/pdf')])
            ->set('ficheros', [UploadedFile::fake()->create('presupuesto-2026.pdf', 20, 'application/pdf')])
            ->assertSet('documentosNuevos', fn ($nuevos) => count($nuevos) === 2)
            ->call('guardar')
            ->assertHasNoErrors();

        $documentos = ConvocatoriaJunta::where('comunidad_id', $this->comunidad->id)->sole()->documentos()->orderBy('id')->get();

        $this->assertSame(['cuentas-2025.pdf', 'presupuesto-2026.pdf'], $documentos->pluck('nombrelocal')->all());
        $this->assertSame([TipoDocumento::CONVOCATORIA_JUNTA], $documentos->pluck('tipo_documento_id')->unique()->values()->all());
        $documentos->each(fn ($documento) => Storage::disk(config('documentos.disco'))->assertExists($documento->ruta));
    }

    public function test_un_fichero_no_admitido_no_se_suma(): void
    {
        Livewire::test(Formulario::class)
            ->call('crear')
            ->set('ficheros', [UploadedFile::fake()->create('programa.exe', 20)])
            ->assertHasErrors('ficheros.0')
            ->assertSet('documentosNuevos', []);
    }

    public function test_quitar_un_documento_lo_borra_con_su_fichero_al_guardar(): void
    {
        Storage::fake(config('documentos.disco'));

        $convocatoria = ConvocatoriaJunta::create([
            'comunidad_id'              => $this->comunidad->id,
            'tipo_junta_id'             => TipoJunta::ORDINARIA,
            'fecha'                     => '2026-11-20',
            'hora_primera_convocatoria' => '18:00',
        ]);
        $cuentas = $convocatoria->adjuntarDocumento(UploadedFile::fake()->create('cuentas-2025.pdf', 20), TipoDocumento::CONVOCATORIA_JUNTA);
        $presupuesto = $convocatoria->adjuntarDocumento(UploadedFile::fake()->create('presupuesto-2026.pdf', 20), TipoDocumento::CONVOCATORIA_JUNTA);

        Livewire::test(Formulario::class)
            ->call('editar', $convocatoria->id)
            ->assertSet('documentos', fn ($documentos) => array_column($documentos, 'nombre') === ['cuentas-2025.pdf', 'presupuesto-2026.pdf'])
            ->call('quitarDocumento', $cuentas->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame([$presupuesto->id], $convocatoria->documentos()->pluck('id')->all());
        Storage::disk(config('documentos.disco'))->assertMissing($cuentas->ruta);
        Storage::disk(config('documentos.disco'))->assertExists($presupuesto->ruta);
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
