<?php

namespace Tests\Feature\Directivos;

use App\Livewire\Catalogos\Lista as CatalogosLista;
use App\Livewire\Directivos\Formulario;
use App\Livewire\Directivos\Lista;
use App\Models\Comunidad;
use App\Models\ComunidadDirectivo;
use App\Models\Pais;
use App\Models\PersonaComunidad;
use App\Models\TipoDocumentoIdentificativo;
use App\Models\TipoGenero;
use Database\Seeders\DemoComunidadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DirectivosTest extends TestCase
{
    use RefreshDatabase;

    // Filas que la migración deja en tipo_directivo_comunidades.
    private const PRESIDENTE = 1;
    private const SECRETARIO = 3;

    private Comunidad $comunidad;
    private Comunidad $otraComunidad;

    protected function setUp(): void
    {
        parent::setUp();

        ['edificio1' => $this->comunidad, 'edificio2' => $this->otraComunidad] = (new DemoComunidadSeeder())->generar();
        session(['comunidad_actual_id' => $this->comunidad->id]);
    }

    private function persona(array $datos = []): PersonaComunidad
    {
        return PersonaComunidad::create($datos + [
            'comunidad_id'             => $this->comunidad->id,
            'nombre'                   => 'Ana',
            'apellido1'                => 'García',
            'documento_pais_id'        => Pais::ESPAÑA,
            'tipo_documento_id'        => TipoDocumentoIdentificativo::DOCUMENTO_NIF,
            'documento_identificativo' => '12345678Z',
            'fecha_nacimiento'         => '1970-05-01',
            'genero_id'                => TipoGenero::GENERO_MUJER,
        ]);
    }

    private function directivo(PersonaComunidad $persona, array $datos = []): ComunidadDirectivo
    {
        return ComunidadDirectivo::create($datos + [
            'comunidad_id'                => $persona->comunidad_id,
            'persona_comunidad_id'        => $persona->id,
            'tipo_directivo_comunidad_id' => self::PRESIDENTE,
            'fecha_inicio'                => '2026-01-01',
        ]);
    }

    public function test_alta_de_una_persona_nueva_crea_la_persona_y_el_directivo(): void
    {
        Livewire::test(Formulario::class)
            ->call('crear')
            ->set('documento_identificativo', ' 12345678z ')
            ->call('guardar') // primer clic: comprueba el documento
            ->assertHasNoErrors()
            ->assertSet('documentoComprobado', true)
            ->assertSet('personaComunidadId', null)
            ->set('nombre', 'Ana')
            ->set('apellido1', 'García')
            ->set('genero_id', TipoGenero::GENERO_MUJER)
            ->set('fecha_nacimiento', '1970-05-01')
            ->set('tipo_directivo_comunidad_id', self::PRESIDENTE)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('directivo-guardado');

        $directivo = ComunidadDirectivo::where('comunidad_id', $this->comunidad->id)->sole();

        $this->assertSame(self::PRESIDENTE, $directivo->tipo_directivo_comunidad_id);
        $this->assertSame(now()->toDateString(), $directivo->fecha_inicio->toDateString());
        $this->assertNull($directivo->fecha_fin);
        $this->assertSame('12345678Z', $directivo->persona->documento_identificativo);
        $this->assertSame($this->comunidad->id, $directivo->persona->comunidad_id);
    }

    public function test_una_persona_nueva_menor_de_edad_no_se_guarda(): void
    {
        Livewire::test(Formulario::class)
            ->call('crear')
            ->set('documento_identificativo', '12345678Z')
            ->call('comprobarDocumento')
            ->set('nombre', 'Luis')
            ->set('apellido1', 'Pérez')
            ->set('genero_id', TipoGenero::GENERO_HOMBRE)
            ->set('fecha_nacimiento', now()->subYears(18)->addDay()->toDateString())
            ->set('tipo_directivo_comunidad_id', self::PRESIDENTE)
            ->call('guardar')
            ->assertHasErrors(['fecha_nacimiento' => 'before_or_equal']);

        $this->assertSame(0, ComunidadDirectivo::count());
        $this->assertFalse(PersonaComunidad::where('documento_identificativo', '12345678Z')->exists());
    }

    public function test_reutiliza_la_persona_que_ya_esta_en_la_comunidad(): void
    {
        $persona = $this->persona();

        Livewire::test(Formulario::class)
            ->call('crear')
            ->set('documento_identificativo', '12345678Z')
            ->call('comprobarDocumento')
            ->assertSet('personaComunidadId', $persona->id)
            ->set('tipo_directivo_comunidad_id', self::SECRETARIO)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(1, PersonaComunidad::where('documento_identificativo', '12345678Z')->count());
        $this->assertSame($persona->id, ComunidadDirectivo::sole()->persona_comunidad_id);
    }

    public function test_el_mismo_documento_en_otra_comunidad_es_otra_persona(): void
    {
        $this->persona(['comunidad_id' => $this->otraComunidad->id]);

        Livewire::test(Formulario::class)
            ->call('crear')
            ->set('documento_identificativo', '12345678Z')
            ->call('comprobarDocumento')
            ->assertSet('documentoComprobado', true)
            ->assertSet('personaComunidadId', null);
    }

    public function test_a_una_persona_sin_fecha_de_nacimiento_se_le_pide_y_se_le_guarda(): void
    {
        $persona = $this->persona(['fecha_nacimiento' => null]);

        $componente = Livewire::test(Formulario::class)
            ->call('crear')
            ->set('documento_identificativo', '12345678Z')
            ->call('comprobarDocumento')
            ->assertSet('personaComunidadId', $persona->id)
            ->set('tipo_directivo_comunidad_id', self::PRESIDENTE)
            ->call('guardar')
            ->assertHasErrors(['fecha_nacimiento' => 'required']);

        $componente->set('fecha_nacimiento', '1980-02-03')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('1980-02-03', $persona->fresh()->fecha_nacimiento->toDateString());
        $this->assertSame(1, ComunidadDirectivo::count());
    }

    public function test_no_admite_a_una_persona_de_la_comunidad_menor_de_edad(): void
    {
        $this->persona(['fecha_nacimiento' => now()->subYears(10)->toDateString()]);

        Livewire::test(Formulario::class)
            ->call('crear')
            ->set('documento_identificativo', '12345678Z')
            ->call('comprobarDocumento')
            ->assertHasErrors('documento_identificativo')
            ->assertSet('documentoComprobado', false);
    }

    public function test_no_admite_documentos_de_persona_juridica(): void
    {
        Livewire::test(Formulario::class)
            ->call('crear')
            ->set('tipo_documento_id', TipoDocumentoIdentificativo::DOCUMENTO_CIF)
            ->set('documento_identificativo', 'B12345674')
            ->call('comprobarDocumento')
            ->assertHasErrors(['tipo_documento_id' => 'in'])
            ->assertSet('documentoComprobado', false);
    }

    public function test_modificar_cambia_puesto_y_fechas_y_no_la_persona(): void
    {
        $persona   = $this->persona();
        $directivo = $this->directivo($persona);

        Livewire::test(Formulario::class)
            ->call('editar', $directivo->id)
            ->assertSet('documentoComprobado', true)
            ->set('tipo_directivo_comunidad_id', self::SECRETARIO)
            ->set('fecha_fin', '2025-12-31')
            ->call('guardar')
            ->assertHasErrors(['fecha_fin' => 'after_or_equal'])
            ->set('fecha_fin', '2026-12-31')
            ->call('guardar')
            ->assertHasNoErrors();

        $directivo->refresh();
        $this->assertSame(self::SECRETARIO, $directivo->tipo_directivo_comunidad_id);
        $this->assertSame('2026-12-31', $directivo->fecha_fin->toDateString());
        $this->assertSame($persona->id, $directivo->persona_comunidad_id);
    }

    public function test_la_lista_solo_enseña_y_borra_los_de_la_comunidad_activa(): void
    {
        $propio = $this->directivo($this->persona());
        $ajeno  = $this->directivo($this->persona([
            'comunidad_id'             => $this->otraComunidad->id,
            'documento_identificativo' => '11111111H',
        ]));

        Livewire::test(Lista::class)
            ->assertSee('12345678Z')
            ->assertDontSee('11111111H')
            ->call('ejecutarEliminar', $ajeno->id)
            ->call('ejecutarEliminar', $propio->id);

        $this->assertFalse(ComunidadDirectivo::whereKey($propio->id)->exists());
        $this->assertTrue(ComunidadDirectivo::whereKey($ajeno->id)->exists());

        Livewire::test(Formulario::class)
            ->call('editar', $ajeno->id)
            ->assertSet('abrir', false);
    }

    public function test_los_puestos_se_gestionan_como_catalogo_comun(): void
    {
        Livewire::test(CatalogosLista::class, ['clave' => 'tipo-directivo-comunidades'])
            ->assertSee(['Presidente', 'Vicepresidente', 'Secretario', 'Tesorero', 'Vocal']);
    }
}
