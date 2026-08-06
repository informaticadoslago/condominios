<?php

namespace Tests\Feature\Contabilidad;

use App\Livewire\Recibos\Lista;
use App\Models\ApunteContable;
use App\Models\AsientoContable;
use App\Models\Comunidad;
use App\Models\EjercicioContable;
use App\Models\EmpresaContable;
use App\Models\FormaDePago;
use App\Models\Inmueble;
use App\Models\PersonaComunidad;
use App\Models\Presupuesto;
use App\Models\Propietario;
use App\Models\Recibo;
use App\Models\TipoEstadoPresupuesto;
use App\Models\TipoPresupuesto;
use App\Models\User;
use App\Services\Comunidades\EnlaceContableComunidad;
use App\Services\Recibos\EnlazarRecibosContabilidad;
use Database\Seeders\DemoComunidadSeeder;
use Database\Seeders\PlanCuentasComunidadesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Once;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El enlace a mano de los recibos ya emitidos: un asiento por presupuesto y vencimiento,
 * con todos sus recibos apuntando al mismo.
 */
class EnlazarRecibosContabilidadTest extends TestCase
{
    use RefreshDatabase;

    private Comunidad $comunidad;

    private Presupuesto $presupuesto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanCuentasComunidadesSeeder::class);

        // Las formas de pago son catálogo de seeder y el recibo tiene FK contra ellas.
        // Con los ids puestos a mano: el seeder no los fija, y el autoincremento de
        // InnoDB no vuelve atrás entre tests aunque la transacción sí, así que a la
        // segunda el id 3 (Transferencia) ya no sería el que dice la constante.
        DB::table('formas_de_pago')->insert([
            ['id' => FormaDePago::RECIBO_BANCARIO, 'descripcion' => 'Recibo bancario', 'estado_id' => 1],
            ['id' => FormaDePago::EFECTIVO, 'descripcion' => 'Efectivo', 'estado_id' => 1],
            ['id' => FormaDePago::TRANSFERENCIA, 'descripcion' => 'Transferencia', 'estado_id' => 1],
        ]);

        $this->comunidad = (new DemoComunidadSeeder())->generar()['edificio1'];

        $empresa = EmpresaContable::create([
            'cif' => 'H12345678', 'razon_social' => 'Comunidad de prueba',
        ]);

        EjercicioContable::create([
            'empresa_contable_id' => $empresa->id,
            'nombre'              => '2026',
            'fecha_inicio'        => '2026-01-01',
            'fecha_fin'           => '2026-12-31',
            'cerrado'             => false,
        ]);

        $this->comunidad->update(['empresa_contable_id' => $empresa->id]);
        $this->comunidad->refresh();

        $this->presupuesto = Presupuesto::create([
            'comunidad_id'        => $this->comunidad->id,
            'nombre'              => 'Presupuesto 2026',
            'anho'                => 2026,
            'tipo_presupuesto_id' => TipoPresupuesto::CUOTAS,
            'estado_id'           => TipoEstadoPresupuesto::PROVISIONAL,
        ]);

        app(EnlaceContableComunidad::class)->asignarCuentaIngresoPresupuesto($this->presupuesto);
        $this->presupuesto->refresh();
    }

    private function crearRecibo(string $importe, string $vencimiento, string $documento): Recibo
    {
        $persona = PersonaComunidad::create([
            'comunidad_id'             => $this->comunidad->id,
            'nombre'                   => 'Vecino '.$documento,
            'documento_identificativo' => $documento,
        ]);

        $inmueble = Inmueble::create([
            'comunidad_id'     => $this->comunidad->id,
            'ocupacion_id'     => 1,
            'tipo_inmueble_id' => 1,
            'planta'           => '1',
            'puerta'           => substr($documento, 0, 1),
            'coeficiente'      => 10,
        ]);

        return Recibo::create([
            'presupuesto_id'   => $this->presupuesto->id,
            'inmueble_id'      => $inmueble->id,
            'propietario_id'   => Propietario::create(['persona_comunidad_id' => $persona->id])->id,
            'numero_pago'      => 1,
            'fecha_vencimiento' => $vencimiento,
            'importe'          => $importe,
            'forma_de_pago_id' => FormaDePago::TRANSFERENCIA,
        ]);
    }

    public function test_los_recibos_de_un_vencimiento_entran_en_un_solo_asiento(): void
    {
        $uno = $this->crearRecibo('50.00', '2026-03-01', '11111111H');
        $dos = $this->crearRecibo('50.00', '2026-03-01', '22222222J');

        $resultado = app(EnlazarRecibosContabilidad::class)->ejecutar([$uno->id, $dos->id]);

        $this->assertSame(['enlazados' => 2, 'omitidos' => 0], $resultado);
        $this->assertSame(1, AsientoContable::count());

        // El mismo número en los dos: el asiento es del vencimiento, no de cada recibo.
        $asientoId = AsientoContable::first()->id;
        $this->assertSame($asientoId, $uno->fresh()->asiento_contable);
        $this->assertSame($asientoId, $dos->fresh()->asiento_contable);
    }

    public function test_el_asiento_lleva_cada_propietario_al_debe_y_el_ingreso_al_haber(): void
    {
        $this->crearRecibo('60.50', '2026-03-01', '11111111H');
        $this->crearRecibo('39.50', '2026-03-01', '22222222J');

        app(EnlazarRecibosContabilidad::class)->ejecutar(Recibo::pluck('id')->all());

        $apuntes = ApunteContable::with('cuentaContable')->get();

        // Céntimos enteros, y el asiento cuadra.
        $this->assertSame(10000, $apuntes->sum('debe'));
        $this->assertSame(10000, $apuntes->sum('haber'));

        $debe = $apuntes->where('debe', '>', 0);
        $this->assertEqualsCanonicalizing(
            ['43000001', '43000002'],
            $debe->pluck('cuentaContable.codigo')->all(),
        );
        $this->assertEqualsCanonicalizing([6050, 3950], $debe->pluck('debe')->all());

        $haber = $apuntes->firstWhere('haber', '>', 0);
        $this->assertSame('75000001', $haber->cuentaContable->codigo);
    }

    public function test_cada_vencimiento_tiene_su_propio_asiento(): void
    {
        $marzo = $this->crearRecibo('50.00', '2026-03-01', '11111111H');
        $junio = $this->crearRecibo('50.00', '2026-06-01', '22222222J');

        app(EnlazarRecibosContabilidad::class)->ejecutar([$marzo->id, $junio->id]);

        $this->assertSame(2, AsientoContable::count());
        $this->assertNotSame($marzo->fresh()->asiento_contable, $junio->fresh()->asiento_contable);
    }

    public function test_volver_a_enlazar_no_duplica_ni_cambia_el_asiento(): void
    {
        $recibo = $this->crearRecibo('50.00', '2026-03-01', '11111111H');

        app(EnlazarRecibosContabilidad::class)->ejecutar([$recibo->id]);
        $asientoId = $recibo->fresh()->asiento_contable;

        // Segunda pasada: ya está enlazado, así que ni se toca ni entra en otro asiento.
        $resultado = app(EnlazarRecibosContabilidad::class)->ejecutar([$recibo->id]);

        $this->assertSame(['enlazados' => 0, 'omitidos' => 0], $resultado);
        $this->assertSame(1, AsientoContable::count());
        $this->assertSame($asientoId, $recibo->fresh()->asiento_contable);
    }

    public function test_la_opcion_del_menu_enlaza_los_seleccionados_o_todo_lo_filtrado(): void
    {
        $uno = $this->crearRecibo('50.00', '2026-03-01', '11111111H');
        $dos = $this->crearRecibo('50.00', '2026-06-01', '22222222J');

        $user = User::forceCreate([
            'login' => 'tester', 'email' => 'tester@example.test', 'password' => bcrypt('secreto'),
        ]);
        $this->actingAs($user);
        session(['comunidad_actual_id' => $this->comunidad->id]);

        // Con uno marcado, la acción va solo sobre ese.
        Livewire::test(Lista::class)
            ->set('seleccionados', [(string) $uno->id])
            ->call('enlazarContabilidad');

        $this->assertNotNull($uno->fresh()->asiento_contable);
        $this->assertNull($dos->fresh()->asiento_contable);

        // Sin nada marcado, sobre todo lo que cumple el filtro.
        Livewire::test(Lista::class)->call('enlazarContabilidad');

        $this->assertNotNull($dos->fresh()->asiento_contable);
    }

    public function test_la_opcion_de_enlazar_solo_sale_si_la_comunidad_lleva_contabilidad(): void
    {
        $this->crearRecibo('50.00', '2026-03-01', '11111111H');

        $user = User::forceCreate([
            'login' => 'tester', 'email' => 'tester@example.test', 'password' => bcrypt('secreto'),
        ]);
        $this->actingAs($user);
        session(['comunidad_actual_id' => $this->comunidad->id]);

        $this->assertStringContainsString('enlazarContabilidad', Livewire::test(Lista::class)->html());

        // Sin contabilidad, cobrar sigue estando —vale para cualquier comunidad— pero
        // enlazar desaparece: no habría con qué.
        $this->comunidad->update(['empresa_contable_id' => null]);
        Once::flush();

        $html = Livewire::test(Lista::class)->html();

        $this->assertStringNotContainsString('enlazarContabilidad', $html);
        $this->assertStringContainsString('abrirCobro', $html);
    }

    public function test_el_filtro_deja_ver_solo_los_que_faltan_por_enlazar(): void
    {
        $enlazado = $this->crearRecibo('50.00', '2026-03-01', '11111111H');
        $pendiente = $this->crearRecibo('50.00', '2026-06-01', '22222222J');

        app(EnlazarRecibosContabilidad::class)->ejecutar([$enlazado->id]);

        $user = User::forceCreate([
            'login' => 'tester', 'email' => 'tester@example.test', 'password' => bcrypt('secreto'),
        ]);
        $this->actingAs($user);
        session(['comunidad_actual_id' => $this->comunidad->id]);

        $lista = Livewire::test(Lista::class)->set('filtros.enlace_contable', 1);

        $this->assertSame([(string) $pendiente->id], $lista->instance()->idsParaAccion());
    }

    public function test_un_presupuesto_sin_cuenta_de_ingresos_se_omite_sin_romper_la_tanda(): void
    {
        $bueno = $this->crearRecibo('50.00', '2026-03-01', '11111111H');

        // Un presupuesto que nunca llegó a estrenar cuenta (se aprobó sin contabilidad).
        $otro = Presupuesto::create([
            'comunidad_id'        => $this->comunidad->id,
            'nombre'              => 'Derrama antigua',
            'anho'                => 2026,
            'tipo_presupuesto_id' => TipoPresupuesto::DERRAMA,
            'estado_id'           => TipoEstadoPresupuesto::PROVISIONAL,
        ]);

        $huerfano = $this->crearRecibo('50.00', '2026-03-01', '22222222J');
        $huerfano->update(['presupuesto_id' => $otro->id]);

        $resultado = app(EnlazarRecibosContabilidad::class)->ejecutar([$bueno->id, $huerfano->id]);

        $this->assertSame(['enlazados' => 1, 'omitidos' => 1], $resultado);
        $this->assertNotNull($bueno->fresh()->asiento_contable);
        $this->assertNull($huerfano->fresh()->asiento_contable);
    }
}
