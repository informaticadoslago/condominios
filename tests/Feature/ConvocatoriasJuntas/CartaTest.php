<?php

namespace Tests\Feature\ConvocatoriasJuntas;

use App\Models\Comunidad;
use App\Models\ConvocatoriaJunta;
use App\Models\Estado;
use App\Models\Inmueble;
use App\Models\LineaOrdenDelDia;
use App\Models\Municipio;
use App\Models\Pais;
use App\Models\TipoDireccion;
use App\Models\TipoJunta;
use App\Models\User;
use App\Services\ConvocatoriasJuntas\GenerarCartaConvocatoria;
use Carbon\Carbon;
use Database\Seeders\DemoComunidadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CartaTest extends TestCase
{
    use RefreshDatabase;

    private Comunidad $comunidad;

    private ConvocatoriaJunta $convocatoria;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ConvocatoriaJunta::DISCO_CARTAS);
        Carbon::setTestNow('2026-10-19 09:00:00');

        $this->comunidad = (new DemoComunidadSeeder())->generar()['edificio1'];
        $this->comunidad->update([
            'administrador_oficina_reunion' => 'las Oficinas de Fincas Lar (Av. García Barbón, 22 – 1º),',
            'administrador_localidad'       => 'Vigo',
        ]);
        $vigo = Municipio::where('nombre', 'like', 'Vigo%')->firstOrFail();
        $this->comunidad->persona->direcciones()->create([
            'tipo_direccion_id' => TipoDireccion::idDomicilio(),
            'direccion1'        => 'Paraguay',
            'numero'            => '19',
            'codigo_postal'     => '36204',
            'provincia_id'      => $vigo->provincia_id,
            'municipio_id'      => $vigo->id,
            'pais_id'           => Pais::ESPAÑA,
            'estado_id'         => Estado::ESTADO_ACTIVO,
        ]);

        foreach ([['1', 'A'], ['0', null]] as [$planta, $puerta]) {
            Inmueble::create([
                'comunidad_id'     => $this->comunidad->id,
                'ocupacion_id'     => 1,
                'tipo_inmueble_id' => 1,
                'planta'           => $planta,
                'puerta'           => $puerta,
                'coeficiente'      => 50,
            ]);
        }

        $this->convocatoria = ConvocatoriaJunta::create([
            'comunidad_id'              => $this->comunidad->id,
            'tipo_junta_id'             => TipoJunta::EXTRAORDINARIA,
            'fecha'                     => '2026-10-28',
            'hora_primera_convocatoria' => '10:30',
        ]);
        foreach (['Presentación nuevo administrador Comunidad.', 'Ruegos y preguntas.'] as $i => $texto) {
            LineaOrdenDelDia::create(['convocatoria_junta_id' => $this->convocatoria->id, 'orden' => $i + 1, 'texto' => $texto]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function pdfGuardado(): string
    {
        return Storage::disk(ConvocatoriaJunta::DISCO_CARTAS)->get($this->convocatoria->rutaCarta());
    }

    /**
     * Páginas del PDF, contando sus objetos /Type /Page. El texto se comprueba en el HTML (ver
     * GenerarCartaConvocatoria::html): leerlo del PDF no sirve con párrafos justificados, y
     * smalot/pdfparser se queda sin memoria al final de la batería de tests.
     */
    private function paginasDelPdf(): int
    {
        return preg_match_all('/\/Type\s*\/Page(?![a-zA-Z])/', $this->pdfGuardado());
    }

    private function usuarioDeLaComunidad(): User
    {
        // Como en TokensApiTest: UserFactory es la de Jetstream y no casa con esta tabla users.
        $usuario = User::forceCreate([
            'login'             => 'tester',
            'email'             => 'tester@example.test',
            'password'          => bcrypt('secreto'),
            'email_verified_at' => now(),
        ]);
        $usuario->assignRole($this->comunidad->nombreRol());

        return $usuario;
    }

    public function test_genera_una_carta_por_inmueble_y_la_guarda_con_fecha_de_hoy(): void
    {
        app(GenerarCartaConvocatoria::class)->ejecutar($this->convocatoria);

        $this->assertSame('2026-10-19', $this->convocatoria->fresh()->fecha_carta->toDateString());

        $this->assertSame(2, $this->paginasDelPdf(), 'Una carta (página) por inmueble.');
    }

    public function test_el_texto_de_la_carta_lleva_los_datos_de_la_convocatoria(): void
    {
        $html = app(GenerarCartaConvocatoria::class)->html($this->convocatoria, Carbon::parse('2026-10-19'));
        $texto = preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html)));

        // Ordenadas por planta: primero el bajo.
        $this->assertLessThan(strpos($texto, 'PARAGUAY, 19 - 1ºA'), strpos($texto, 'PARAGUAY, 19 - BAJO'));

        foreach ([
            '36204 VIGO',
            'Vigo, 19 de octubre de 2026',
            'asista a la Junta General Extraordinaria que se celebrará el próximo día 28 de octubre de 2026 a las 10:30 horas en primera convocatoria',
            'a las 11:00 horas en segunda, en las Oficinas de Fincas Lar (Av. García Barbón, 22 – 1º), en la población de Vigo, con el fin',
            'ORDEN DEL DÍA',
            '1. Presentación nuevo administrador Comunidad.',
            '2. Ruegos y preguntas.',
            'para el próximo 28 de octubre de 2026 le participo',
            'Propietario del: 1ºA',
        ] as $fragmento) {
            $this->assertStringContainsString($fragmento, $texto);
        }

        $this->assertStringNotContainsString('1º),,', $texto, 'La coma tecleada al final de la oficina sobra.');
    }

    public function test_la_ruta_ensena_la_guardada_y_solo_regenera_cuando_se_pide(): void
    {
        $this->actingAs($this->usuarioDeLaComunidad())->withSession(['comunidad_actual_id' => $this->comunidad->id]);
        $url = route('convocatorias-juntas.carta', $this->convocatoria);

        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame('2026-10-19', $this->convocatoria->fresh()->fecha_carta->toDateString());
        $primera = $this->pdfGuardado();

        Carbon::setTestNow('2026-10-20 09:00:00');
        LineaOrdenDelDia::create(['convocatoria_junta_id' => $this->convocatoria->id, 'orden' => 3, 'texto' => 'Punto nuevo.']);

        $this->get($url)->assertOk();
        $this->assertSame('2026-10-19', $this->convocatoria->fresh()->fecha_carta->toDateString(), 'Ver no regenera.');
        $this->assertSame($primera, $this->pdfGuardado(), 'Ver enseña el PDF que ya estaba.');

        $this->get($url.'?regenerar=1')->assertOk();
        $this->assertSame('2026-10-20', $this->convocatoria->fresh()->fecha_carta->toDateString());
        $this->assertNotSame($primera, $this->pdfGuardado(), 'Regenerar escribe un PDF nuevo.');
    }

    public function test_la_carta_de_otra_comunidad_no_se_ve(): void
    {
        $otra = (new DemoComunidadSeeder())->generar()['edificio1'];
        $usuario = $this->usuarioDeLaComunidad();
        $usuario->assignRole($otra->nombreRol());

        $this->actingAs($usuario)->withSession(['comunidad_actual_id' => $otra->id])
            ->get(route('convocatorias-juntas.carta', $this->convocatoria))
            ->assertForbidden();

        Storage::disk(ConvocatoriaJunta::DISCO_CARTAS)->assertMissing($this->convocatoria->rutaCarta());
    }

    public function test_borrar_la_convocatoria_borra_su_pdf(): void
    {
        app(GenerarCartaConvocatoria::class)->ejecutar($this->convocatoria);
        $ruta = $this->convocatoria->rutaCarta();
        Storage::disk(ConvocatoriaJunta::DISCO_CARTAS)->assertExists($ruta);

        $this->convocatoria->delete();

        Storage::disk(ConvocatoriaJunta::DISCO_CARTAS)->assertMissing($ruta);
    }

    public function test_la_marca_en_preparacion_solo_sale_cuando_se_pide(): void
    {
        $generar = app(GenerarCartaConvocatoria::class);
        $fecha = Carbon::parse('2026-10-19');

        $this->assertStringContainsString('EN PREPARACIÓN', $generar->html($this->convocatoria, $fecha, enPreparacion: true));
        $this->assertStringNotContainsString('EN PREPARACIÓN', $generar->html($this->convocatoria, $fecha));
    }

    public function test_la_de_la_lista_lleva_la_marca_y_la_del_correo_no(): void
    {
        $llamadas = [];
        $this->partialMock(GenerarCartaConvocatoria::class, function ($mock) use (&$llamadas) {
            $mock->shouldReceive('html')->andReturnUsing(function (...$args) use (&$llamadas) {
                $llamadas[] = $args[3] ?? false;

                return '<html><body>carta</body></html>';
            });
        });
        $generar = app(GenerarCartaConvocatoria::class);

        $generar->ejecutar($this->convocatoria);
        $generar->pdfInmueble($this->convocatoria, Inmueble::where('comunidad_id', $this->comunidad->id)->first());

        $this->assertSame([true, false], $llamadas, 'Lista (impresora): con marca. Inmueble (ojo y adjunto): sin marca.');
    }

    public function test_usa_la_nomenclatura_de_la_ficha_si_se_ha_cambiado(): void
    {
        Inmueble::where('comunidad_id', $this->comunidad->id)->where('planta', '1')->update(['nomenclatura' => 'Primero A']);

        $texto = strip_tags(app(GenerarCartaConvocatoria::class)->html($this->convocatoria, Carbon::parse('2026-10-19')));

        $this->assertStringContainsString('PARAGUAY, 19 - PRIMERO A', $texto);
        $this->assertStringContainsString('Primero A', preg_replace('/\s+/u', ' ', $texto));
    }
}
