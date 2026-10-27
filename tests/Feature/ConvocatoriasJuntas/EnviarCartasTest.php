<?php

namespace Tests\Feature\ConvocatoriasJuntas;

use App\Livewire\ConvocatoriasJuntas\EnviarCartas;
use App\Mail\CartaConvocatoria;
use App\Models\Comunidad;
use App\Models\ConvocatoriaJunta;
use App\Models\CorreoEnviado;
use App\Models\Documento;
use App\Models\Estado;
use App\Models\Inmueble;
use App\Models\LineaOrdenDelDia;
use App\Models\PersonaComunidad;
use App\Models\Propietario;
use App\Models\TipoContacto;
use App\Models\TipoDocumento;
use App\Models\TipoJunta;
use App\Models\Titularidad;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DemoComunidadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EnviarCartasTest extends TestCase
{
    use RefreshDatabase;

    private Comunidad $comunidad;

    private ConvocatoriaJunta $convocatoria;

    /** @var array<string, Inmueble> por nomenclatura */
    private array $inmuebles = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ConvocatoriaJunta::DISCO_CARTAS);
        Storage::fake(config('documentos.disco'));
        Mail::fake();
        Carbon::setTestNow('2026-10-19 09:00:00');

        $this->comunidad = (new DemoComunidadSeeder())->generar()['edificio1'];
        session(['comunidad_actual_id' => $this->comunidad->id]);
        $this->actingAs(User::forceCreate([
            'login'             => 'tester',
            'email'             => 'tester@example.test',
            'password'          => bcrypt('secreto'),
            'email_verified_at' => now(),
        ]));
        auth()->user()->assignRole($this->comunidad->nombreRol());

        // Bajo: dos titulares, una con correo sin validar y otro sin correo.
        // 1ºA: una titular con el correo validado. 3º: un titular sin correo.
        $this->inmueble('0', null, 'Bajo', [['Beatriz', 'beatriz@example.test', false], ['Samuel', null, false]]);
        $this->inmueble('1', 'A', '1ºA', [['Ana', 'ana@example.test', true]]);
        $this->inmueble('3', null, 'Piso 3º', [['Carlos', null, false]]);

        $this->convocatoria = ConvocatoriaJunta::create([
            'comunidad_id'              => $this->comunidad->id,
            'tipo_junta_id'             => TipoJunta::EXTRAORDINARIA,
            'fecha'                     => '2026-10-28',
            'hora_primera_convocatoria' => '10:30',
        ]);
        LineaOrdenDelDia::create(['convocatoria_junta_id' => $this->convocatoria->id, 'orden' => 1, 'texto' => 'Ruegos y preguntas.']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param array<int, array{0: string, 1: ?string, 2: bool}> $titulares nombre, correo, validado */
    private function inmueble(string $planta, ?string $puerta, string $nomenclatura, array $titulares): void
    {
        $inmueble = Inmueble::create([
            'comunidad_id'     => $this->comunidad->id,
            'ocupacion_id'     => 1,
            'tipo_inmueble_id' => 1,
            'planta'           => $planta,
            'puerta'           => $puerta,
            'nomenclatura'     => $nomenclatura,
            'coeficiente'      => 10,
        ]);

        foreach ($titulares as $i => [$nombre, $correo, $validado]) {
            $persona = PersonaComunidad::create([
                'comunidad_id'             => $this->comunidad->id,
                'nombre'                   => $nombre,
                'documento_identificativo' => sprintf('%08dZ', $inmueble->id * 10 + $i),
            ]);

            if ($correo) {
                $persona->contactos()->create([
                    'tipo_contacto_id' => TipoContacto::EMAIL,
                    'descripcion'      => 'Email',
                    'valor'            => $correo,
                    'estado_id'        => Estado::ESTADO_ACTIVO,
                    'verified_at'      => $validado ? now() : null,
                ]);
            }

            Titularidad::create([
                'inmueble_id'    => $inmueble->id,
                'propietario_id' => Propietario::create(['persona_comunidad_id' => $persona->id])->id,
                'cuota_percent'  => 100 / count($titulares),
                'causa'          => Titularidad::CAUSA_COMPRAVENTA,
                'fecha_inicio'   => '2020-01-01',
            ]);
        }

        $this->inmuebles[$nomenclatura] = $inmueble;
    }

    public function test_abrir_genera_la_carta_si_no_la_hay_y_marca_solo_los_validados(): void
    {
        Livewire::test(EnviarCartas::class)
            ->call('abrir', $this->convocatoria->id)
            ->assertSet('abrir', true)
            ->assertSet('lineas', fn ($lineas) => array_column($lineas, 'nomenclatura') === ['Bajo', '1ºA', 'Piso 3º']
                && $lineas[2]['con_correo'] === false
                && $lineas[0]['propietarios'][0]['correo'] === 'beatriz@example.test'
                && $lineas[0]['propietarios'][0]['validado'] === false)
            ->assertSet('seleccion', [(string) $this->inmuebles['1ºA']->id]);

        $this->assertSame('2026-10-19', $this->convocatoria->fresh()->fecha_carta->toDateString());
        Storage::disk(ConvocatoriaJunta::DISCO_CARTAS)->assertExists($this->convocatoria->rutaCarta());
    }

    public function test_enviar_manda_un_correo_por_propietario_con_direccion_y_lo_registra(): void
    {
        Livewire::test(EnviarCartas::class)
            ->call('abrir', $this->convocatoria->id)
            ->set('seleccion', [(string) $this->inmuebles['Bajo']->id, (string) $this->inmuebles['1ºA']->id])
            ->call('enviar')
            ->assertSet('abrir', false)
            ->assertDispatched('toast-success');

        Mail::assertQueued(CartaConvocatoria::class, 2);
        Mail::assertQueued(CartaConvocatoria::class, fn ($correo) => $correo->hasTo('beatriz@example.test'));
        Mail::assertQueued(CartaConvocatoria::class, fn ($correo) => $correo->hasTo('ana@example.test'));

        $this->assertEqualsCanonicalizing(
            ['beatriz@example.test', 'ana@example.test'],
            CorreoEnviado::where('tipo', CartaConvocatoria::class)->pluck('destinatario')->all(),
        );
    }

    public function test_sin_nada_marcado_avisa_y_no_manda(): void
    {
        Livewire::test(EnviarCartas::class)
            ->call('abrir', $this->convocatoria->id)
            ->set('seleccion', [])
            ->call('enviar')
            ->assertDispatched('toast-error')
            ->assertSet('abrir', true);

        Mail::assertNothingQueued();
    }

    public function test_cc_y_cco_van_solo_en_el_primer_correo_del_inmueble(): void
    {
        // Dos titulares con correo: dos correos, pero la copia solo en uno.
        $this->inmueble('2', 'B', '2ºB', [['Diana', 'diana@example.test', true], ['Elena', 'elena@example.test', true]]);
        $id = $this->inmuebles['2ºB']->id;

        Livewire::test(EnviarCartas::class)
            ->call('abrir', $this->convocatoria->id)
            ->call('toggleConCopia', $id)
            ->assertSet('conCopia', [$id])
            ->set('seleccion', [(string) $id])
            ->set("cc.{$id}", 'familia@example.test')
            ->set("cco.{$id}", 'asesoria@example.test')
            ->call('enviar')
            ->assertHasNoErrors();

        Mail::assertQueued(CartaConvocatoria::class, 2);
        $this->assertSame(1, Mail::queued(CartaConvocatoria::class, fn ($correo) => $correo->hasCc('familia@example.test') && $correo->hasBcc('asesoria@example.test'))->count());
    }

    public function test_un_cc_mal_escrito_no_deja_enviar(): void
    {
        $id = $this->inmuebles['1ºA']->id;

        Livewire::test(EnviarCartas::class)
            ->call('abrir', $this->convocatoria->id)
            ->call('toggleConCopia', $id)
            ->set("cc.{$id}", 'esto no es un correo')
            ->call('enviar')
            ->assertHasErrors(["cc.{$id}" => 'email'])
            ->assertSet('abrir', true);

        Mail::assertNothingQueued();
    }

    public function test_el_correo_lleva_adjunta_la_carta_de_su_inmueble(): void
    {
        $correo = new CartaConvocatoria($this->convocatoria, $this->inmuebles['1ºA'], 'Ana');
        $html = $correo->render();

        $this->assertStringContainsString('Hola Ana:', $html);
        $this->assertStringContainsString('28 de octubre de 2026', $html);
        $this->assertStringContainsString('Junta General Extraordinaria', $correo->asunto());

        $this->assertCount(1, $correo->rawAttachments);
        $adjunto = $correo->rawAttachments[0];
        $this->assertSame('convocatoria-2026-10-28-1oa.pdf', $adjunto['name']);
        $this->assertStringStartsWith('%PDF', $adjunto['data']);
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page(?![a-zA-Z])/', $adjunto['data']), 'Solo la carta de su inmueble.');
    }

    private function documento(string $nombre): Documento
    {
        return $this->convocatoria->adjuntarDocumento(UploadedFile::fake()->create($nombre, 20, 'application/pdf'), TipoDocumento::CONVOCATORIA_JUNTA);
    }

    public function test_los_adjuntos_marcados_van_en_el_correo_y_queda_constancia_solo_de_sus_nombres(): void
    {
        $cuentas = $this->documento('cuentas-2025.pdf');
        $presupuesto = $this->documento('presupuesto-2026.pdf');

        Livewire::test(EnviarCartas::class)
            ->call('abrir', $this->convocatoria->id)
            ->assertSet('conCarta', true)
            ->assertSet('documentosMarcados', [(string) $cuentas->id, (string) $presupuesto->id])
            ->set('documentosMarcados', [(string) $cuentas->id])
            ->call('enviar')
            ->assertSet('abrir', false);

        Mail::assertQueued(CartaConvocatoria::class, 1);
        $this->assertSame(
            ['convocatoria-2026-10-28-1oa.pdf', 'cuentas-2025.pdf'],
            CorreoEnviado::where('destinatario', 'ana@example.test')->sole()->adjuntos,
        );
        $this->assertNotNull($presupuesto->fresh(), 'Desmarcarlo no lo borra.');
    }

    public function test_la_carta_tambien_se_puede_desmarcar(): void
    {
        $cuentas = $this->documento('cuentas-2025.pdf');

        Livewire::test(EnviarCartas::class)
            ->call('abrir', $this->convocatoria->id)
            ->set('conCarta', false)
            ->call('enviar');

        $this->assertSame(['cuentas-2025.pdf'], CorreoEnviado::where('destinatario', 'ana@example.test')->sole()->adjuntos);

        $correo = new CartaConvocatoria($this->convocatoria, $this->inmuebles['1ºA'], 'Ana', documentoIds: [$cuentas->id], conCarta: false);
        $correo->render();
        $this->assertCount(0, $correo->rawAttachments);
        $this->assertCount(1, $correo->diskAttachments);
    }

    public function test_el_asunto_y_el_texto_escritos_son_los_del_correo(): void
    {
        Livewire::test(EnviarCartas::class)
            ->call('abrir', $this->convocatoria->id)
            ->assertSet('asunto', CartaConvocatoria::asuntoPorDefecto($this->convocatoria))
            ->assertSet('texto', CartaConvocatoria::textoPorDefecto($this->convocatoria))
            ->set('asunto', 'Junta del día 28')
            ->set('texto', "Os mando la convocatoria.\n\nUn saludo,\nla administración")
            ->call('enviar')
            ->assertHasNoErrors();

        $this->assertSame('Junta del día 28', CorreoEnviado::where('destinatario', 'ana@example.test')->sole()->asunto);

        $correo = new CartaConvocatoria($this->convocatoria, $this->inmuebles['1ºA'], 'Ana', asunto: 'Junta del día 28', texto: "Os mando la <b>convocatoria</b>.\n\nUn saludo,\nla administración");
        $html = $correo->render();

        $this->assertSame('Junta del día 28', $correo->asunto());
        $this->assertStringContainsString('Hola Ana:', $html);
        $this->assertStringContainsString('Os mando la &lt;b&gt;convocatoria&lt;/b&gt;.', $html);
        $this->assertStringContainsString('Un saludo,<br />', $html);
    }

    public function test_sin_asunto_o_sin_texto_no_deja_enviar(): void
    {
        Livewire::test(EnviarCartas::class)
            ->call('abrir', $this->convocatoria->id)
            ->set('asunto', ' ')
            ->set('texto', '')
            ->call('enviar')
            ->assertHasErrors(['asunto' => 'required', 'texto' => 'required'])
            ->assertSet('abrir', true);

        Mail::assertNothingQueued();
    }

    public function test_el_correo_lleva_los_documentos_detras_de_la_carta(): void
    {
        $cuentas = $this->documento('cuentas-2025.pdf');
        $this->documento('presupuesto-2026.pdf');

        $correo = new CartaConvocatoria($this->convocatoria, $this->inmuebles['1ºA'], 'Ana', documentoIds: [$cuentas->id]);

        $correo->render();
        $correo->assertHasAttachmentFromStorageDisk(config('documentos.disco'), $cuentas->ruta, 'cuentas-2025.pdf');
        $this->assertCount(1, $correo->diskAttachments);
        $this->assertSame(['convocatoria-2026-10-28-1oa.pdf', 'cuentas-2025.pdf'], $correo->nombresAdjuntos());
    }

    public function test_un_documento_borrado_antes_de_enviarse_no_impide_el_correo(): void
    {
        $cuentas = $this->documento('cuentas-2025.pdf');
        $correo = new CartaConvocatoria($this->convocatoria, $this->inmuebles['1ºA'], 'Ana', documentoIds: [$cuentas->id]);

        $cuentas->delete();

        $correo->render();
        $this->assertCount(0, $correo->diskAttachments);
        $this->assertCount(1, $correo->rawAttachments);
    }

    public function test_el_ojo_ensena_la_carta_de_ese_inmueble_y_no_la_de_otra_comunidad(): void
    {
        $respuesta = $this->get(route('convocatorias-juntas.carta-inmueble', [$this->convocatoria, $this->inmuebles['1ºA']]));

        $respuesta->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page(?![a-zA-Z])/', $respuesta->getContent()));

        $ajeno = Inmueble::create([
            'comunidad_id'     => (new DemoComunidadSeeder())->generar()['edificio1']->id,
            'ocupacion_id'     => 1,
            'tipo_inmueble_id' => 1,
            'planta'           => '2',
            'coeficiente'      => 10,
        ]);

        $this->get(route('convocatorias-juntas.carta-inmueble', [$this->convocatoria, $ajeno]))->assertNotFound();
    }
}
