<?php

namespace Tests\Feature\MandatosSepa;

use App\Livewire\Inmuebles\Lista as InmueblesLista;
use App\Livewire\Propietarios\Lista as PropietariosLista;
use App\Models\Comunidad;
use App\Models\FormaDePago;
use App\Models\FormaPagoInmueble;
use App\Models\Inmueble;
use App\Models\PersonaComunidad;
use App\Models\Propietario;
use App\Models\TipoDocumentoIdentificativo;
use App\Models\Titularidad;
use App\Models\User;
use Database\Seeders\DemoComunidadSeeder;
use Database\Seeders\FormasDePagoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\TestCase;

/** La plantilla del mandato SEPA desde las listas: en blanco, por propietario o por inmueble. */
class PlantillaTest extends TestCase
{
    use RefreshDatabase;

    private Comunidad $comunidad;

    /** Lo que recibe la vista del PDF, para comprobar qué va en cada hoja. */
    private array $hojas = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->comunidad = (new DemoComunidadSeeder())->generar()['edificio1'];

        View::composer('mandatos-sepa.plantilla', fn ($view) => $this->hojas = $view->getData()['hojas']);
    }

    private function usuarioDeLaComunidad(): User
    {
        if ($usuario = User::where('login', 'tester')->first()) {
            return $usuario;
        }

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

    private function pedir(array $parametros = [])
    {
        return $this->actingAs($this->usuarioDeLaComunidad())
            ->withSession(['comunidad_actual_id' => $this->comunidad->id])
            ->get(route('mandatos-sepa.plantilla-lote', $parametros));
    }

    /** Páginas del PDF, contando sus objetos /Type /Page (ver CartaTest::paginasDelPdf). */
    private function paginas($respuesta): int
    {
        return preg_match_all('/\/Type\s*\/Page(?![a-zA-Z])/', $respuesta->getContent());
    }

    private function propietario(string $nombre, ?Comunidad $comunidad = null): Propietario
    {
        $persona = PersonaComunidad::create([
            'comunidad_id'             => ($comunidad ?? $this->comunidad)->id,
            'nombre'                   => $nombre,
            'apellido1'                => 'Pérez',
            'tipo_documento_id'        => TipoDocumentoIdentificativo::DOCUMENTO_NIE,
            'documento_identificativo' => 'X'.random_int(1000000, 9999999).'L',
        ]);

        return Propietario::create(['persona_comunidad_id' => $persona->id]);
    }

    private function inmueble(string $planta, ?string $puerta, array $titulares = []): Inmueble
    {
        $inmueble = Inmueble::create([
            'comunidad_id'     => $this->comunidad->id,
            'ocupacion_id'     => 1,
            'tipo_inmueble_id' => 1,
            'planta'           => $planta,
            'puerta'           => $puerta,
            'coeficiente'      => 10,
        ]);

        foreach ($titulares as [$propietario, $cuota]) {
            Titularidad::create([
                'inmueble_id'    => $inmueble->id,
                'propietario_id' => $propietario->id,
                'cuota_percent'  => $cuota,
                'causa'          => Titularidad::CAUSA_COMPRAVENTA,
                'fecha_inicio'   => '2020-01-01',
            ]);
        }

        return $inmueble;
    }

    public function test_sin_nada_marcado_sale_una_hoja_en_blanco(): void
    {
        $respuesta = $this->pedir()->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->assertSame(1, $this->paginas($respuesta));
        $this->assertCount(1, $this->hojas);
        $this->assertNull($this->hojas[0]['propietario']);
        $this->assertNull($this->hojas[0]['piso']);
        $this->assertNotContains(true, array_column($this->hojas[0]['tiposDocumento'], 'marcado'));
    }

    public function test_una_hoja_por_propietario_ordenadas_por_nombre_y_sin_piso(): void
    {
        $zoe  = $this->propietario('Zoe');
        $ana  = $this->propietario('Ana');
        $otra = $this->propietario('Intrusa', (new DemoComunidadSeeder())->generar()['edificio1']);

        $respuesta = $this->pedir(['propietarios' => "{$zoe->id},{$ana->id},{$otra->id}"])->assertOk();

        $this->assertSame(2, $this->paginas($respuesta), 'Cada hoja cabe en una página.');
        $this->assertSame(['Ana', 'Zoe'], array_map(fn ($hoja) => $hoja['propietario']->nombre, $this->hojas));
        $this->assertSame([null, null], array_column($this->hojas, 'piso'));
        $this->assertSame(
            [TipoDocumentoIdentificativo::find(TipoDocumentoIdentificativo::DOCUMENTO_NIE)->nombre],
            collect($this->hojas[0]['tiposDocumento'])->where('marcado', true)->pluck('nombre')->all(),
            'Marcada solo la casilla de su documento.'
        );
    }

    public function test_una_hoja_por_inmueble_con_su_piso_y_a_nombre_de_quien_paga(): void
    {
        $this->seed(FormasDePagoSeeder::class);

        $paga     = $this->propietario('Paga');
        $mayor    = $this->propietario('Mayor');
        $menor    = $this->propietario('Menor');
        $primero  = $this->inmueble('1', 'A', [[$mayor, 70], [$paga, 30]]);
        $bajo     = $this->inmueble('0', null, [[$menor, 40], [$mayor, 60]]);
        $sinNadie = $this->inmueble('2', 'B');

        FormaPagoInmueble::create([
            'inmueble_id'      => $primero->id,
            'propietario_id'   => $paga->id,
            'forma_de_pago_id' => FormaDePago::TRANSFERENCIA,
            'fecha_inicio'     => '2020-01-01',
        ]);

        $respuesta = $this->pedir(['inmuebles' => "{$primero->id},{$sinNadie->id},{$bajo->id}"])->assertOk();

        $this->assertSame(3, $this->paginas($respuesta), 'Cada hoja cabe en una página.');
        // Por planta; el que paga manda sobre la cuota, y sin forma de pago, la mayor cuota.
        $this->assertSame(['Bajo', '1ºA', '2ºB'], array_column($this->hojas, 'piso'));
        $this->assertSame(['Mayor', 'Paga', null], array_map(fn ($hoja) => $hoja['propietario']?->nombre, $this->hojas));
    }

    public function test_los_de_otra_comunidad_no_salen(): void
    {
        $otra = (new DemoComunidadSeeder())->generar()['edificio1'];
        $ajeno = Inmueble::create([
            'comunidad_id'     => $otra->id,
            'ocupacion_id'     => 1,
            'tipo_inmueble_id' => 1,
            'planta'           => '1',
            'puerta'           => 'A',
            'coeficiente'      => 10,
        ]);

        $this->pedir(['inmuebles' => $ajeno->id])->assertNotFound();
        $this->pedir(['propietarios' => $this->propietario('Ajeno', $otra)->id])->assertNotFound();
    }

    public function test_el_menu_de_las_listas_lleva_lo_marcado(): void
    {
        $this->actingAs($this->usuarioDeLaComunidad());
        session(['comunidad_actual_id' => $this->comunidad->id]);

        $a = $this->inmueble('1', 'A');
        $b = $this->inmueble('1', 'B');

        Livewire::test(InmueblesLista::class)
            ->assertSee(route('mandatos-sepa.plantilla-lote'), false)
            ->set('marcarTodosVisibles', true)
            ->assertSet('seleccionados', fn ($ids) => collect($ids)->sort()->values()->all() === collect([(string) $a->id, (string) $b->id])->sort()->values()->all())
            ->set('seleccionados', [(string) $a->id])
            ->assertSee(e(route('mandatos-sepa.plantilla-lote', ['inmuebles' => $a->id])), false);

        $propietario = $this->propietario('Ana');

        Livewire::test(PropietariosLista::class)
            ->set('seleccionados', [(string) $propietario->id])
            ->assertSee(e(route('mandatos-sepa.plantilla-lote', ['propietarios' => $propietario->id])), false);
    }
}
