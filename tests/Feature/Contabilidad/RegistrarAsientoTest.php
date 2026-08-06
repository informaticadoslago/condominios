<?php

namespace Tests\Feature\Contabilidad;

use App\Exceptions\AsientoInvalidoException;
use App\Exceptions\EjercicioCerradoException;
use App\Exceptions\TerceroContableDesconocidoException;
use App\Models\AsientoContable;
use App\Models\CuentaContable;
use App\Models\EjercicioContable;
use App\Models\EmpresaContable;
use App\Models\TerceroContable;
use App\Services\Contabilidad\DatosAsiento;
use App\Services\Contabilidad\RegistrarAsientoService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegistrarAsientoTest extends TestCase
{
    use RefreshDatabase;

    private EmpresaContable $empresa;

    private EjercicioContable $ejercicio;

    private RegistrarAsientoService $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = EmpresaContable::create([
            'cif' => 'H12345678', 'razon_social' => 'Comunidad de prueba',
        ]);

        $this->ejercicio = EjercicioContable::create([
            'empresa_contable_id' => $this->empresa->id,
            'nombre'              => '2026',
            'fecha_inicio'        => '2026-01-01',
            'fecha_fin'           => '2026-12-31',
            'cerrado'             => false,
        ]);

        // Grupo del que cuelgan las subcuentas de cliente, y las cuentas de contrapartida.
        $this->cuenta('43000000', 'Clientes');
        $this->cuenta('70500001', 'Cuotas ordinarias', 4);
        $this->cuenta('43110001', 'Efectos en gestión de cobro');
        $this->cuenta('57200001', 'Bancos c/c');

        $this->servicio = app(RegistrarAsientoService::class);
    }

    private function cuenta(string $codigo, string $nombre, int $tipo = 1): CuentaContable
    {
        return CuentaContable::create([
            'empresa_contable_id'     => $this->empresa->id,
            'tipo_cuenta_contable_id' => $tipo,
            'codigo'                  => $codigo,
            'nombre'                  => $nombre,
            'estado_id'               => CuentaContable::ESTADO_ACTIVO,
        ]);
    }

    /** @param  list<array<string, mixed>>  $lineas */
    private function datos(array $lineas, array $extra = []): DatosAsiento
    {
        return DatosAsiento::desdeArray(array_merge([
            'empresa_contable_id' => $this->empresa->id,
            'ejercicio'           => '2026',
            'fecha'               => '2026-01-31',
            'concepto'            => 'Asiento de prueba',
            'lineas'              => $lineas,
        ], $extra));
    }

    private function propietario(string $id, array $extra = []): array
    {
        return array_merge(['tipo' => 'propietario', 'id' => $id, 'clase' => 'cliente'], $extra);
    }

    /** Saldo de una cuenta en céntimos: deudor positivo, acreedor negativo. */
    private function saldo(string $codigo): int
    {
        return (int) DB::table('apunte_contables')
            ->join('cuenta_contables', 'cuenta_contables.id', '=', 'apunte_contables.cuenta_contable_id')
            ->where('cuenta_contables.empresa_contable_id', $this->empresa->id)
            ->where('cuenta_contables.codigo', $codigo)
            ->sum(DB::raw('debe - haber'));
    }

    public function test_un_asiento_descuadrado_se_rechaza(): void
    {
        $this->expectException(AsientoInvalidoException::class);

        $this->servicio->ejecutar($this->datos([
            ['cuenta' => '57200001', 'debe' => 1000],
            ['cuenta' => '70500001', 'haber' => 992],
        ]));
    }

    public function test_la_misma_referencia_dos_veces_deja_un_solo_asiento(): void
    {
        $lineas = [
            ['cuenta' => '57200001', 'debe' => 992],
            ['cuenta' => '70500001', 'haber' => 992],
        ];
        $referencia = ['referencia' => ['tipo' => 'recibo', 'id' => '1234', 'evento' => 'emision']];

        $primero = $this->servicio->ejecutar($this->datos($lineas, $referencia));
        $segundo = $this->servicio->ejecutar($this->datos($lineas, $referencia));

        $this->assertTrue($primero->wasRecentlyCreated);
        $this->assertFalse($segundo->wasRecentlyCreated, 'La segunda llamada no debe crear nada');
        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(1, AsientoContable::count());
    }

    public function test_un_tercero_sin_cuenta_la_recibe_con_el_correlativo_correcto(): void
    {
        $this->servicio->ejecutar($this->datos([
            ['tercero' => $this->propietario('17', ['nif' => '12345678Z', 'razon_social' => 'García Pérez, Antonio']), 'debe' => 992],
            ['cuenta' => '70500001', 'haber' => 992],
        ], ['crear_terceros_desconocidos' => true]));

        $this->servicio->ejecutar($this->datos([
            ['tercero' => $this->propietario('42', ['razon_social' => 'Otro propietario']), 'debe' => 500],
            ['cuenta' => '70500001', 'haber' => 500],
        ], ['crear_terceros_desconocidos' => true]));

        // El correlativo va por orden de alta, no por el id del sujeto.
        $this->assertSame('43000001', TerceroContable::where('sujeto_id', '17')->first()->cuentaContable->codigo);
        $this->assertSame('43000002', TerceroContable::where('sujeto_id', '42')->first()->cuentaContable->codigo);

        $tercero = TerceroContable::where('sujeto_id', '17')->first();
        $this->assertSame('12345678Z', $tercero->nif);
        $this->assertSame('García Pérez, Antonio', $tercero->razon_social);
    }

    public function test_el_mismo_tercero_reutiliza_su_cuenta(): void
    {
        foreach ([992, 500] as $importe) {
            $this->servicio->ejecutar($this->datos([
                ['tercero' => $this->propietario('17'), 'debe' => $importe],
                ['cuenta' => '70500001', 'haber' => $importe],
            ], ['crear_terceros_desconocidos' => true]));
        }

        $this->assertSame(1, TerceroContable::where('sujeto_id', '17')->count());
    }

    public function test_un_tercero_desconocido_sin_autorizacion_se_rechaza(): void
    {
        $this->expectException(TerceroContableDesconocidoException::class);

        $this->servicio->ejecutar($this->datos([
            ['tercero' => $this->propietario('17'), 'debe' => 992],
            ['cuenta' => '70500001', 'haber' => 992],
        ]));
    }

    public function test_un_asiento_en_ejercicio_cerrado_se_rechaza(): void
    {
        $this->ejercicio->update(['cerrado' => true]);

        $this->expectException(EjercicioCerradoException::class);

        $this->servicio->ejecutar($this->datos([
            ['cuenta' => '57200001', 'debe' => 992],
            ['cuenta' => '70500001', 'haber' => 992],
        ]));
    }

    public function test_el_ciclo_emision_remesa_cobro_deja_la_cuenta_del_propietario_a_cero(): void
    {
        $this->servicio->ejecutar($this->datos([
            ['tercero' => $this->propietario('17'), 'debe' => 992],
            ['cuenta' => '70500001', 'haber' => 992],
        ], ['crear_terceros_desconocidos' => true, 'referencia' => ['tipo' => 'recibo', 'id' => '1', 'evento' => 'emision']]));

        $this->assertSame(992, $this->saldo('43000001'));

        $this->servicio->ejecutar($this->datos([
            ['cuenta' => '43110001', 'debe' => 992],
            ['tercero' => $this->propietario('17'), 'haber' => 992],
        ], ['referencia' => ['tipo' => 'recibo', 'id' => '1', 'evento' => 'remesa']]));

        $this->servicio->ejecutar($this->datos([
            ['cuenta' => '57200001', 'debe' => 992],
            ['cuenta' => '43110001', 'haber' => 992],
        ], ['referencia' => ['tipo' => 'recibo', 'id' => '1', 'evento' => 'cobro']]));

        $this->assertSame(0, $this->saldo('43000001'), 'Cobrado el recibo, el propietario no debe nada');
        $this->assertSame(0, $this->saldo('43110001'), 'Los efectos en gestión de cobro quedan saldados');
        $this->assertSame(992, $this->saldo('57200001'));
    }

    public function test_la_devolucion_devuelve_la_deuda_al_propietario(): void
    {
        foreach ([
            ['emision', [['tercero' => $this->propietario('17'), 'debe' => 992], ['cuenta' => '70500001', 'haber' => 992]]],
            ['remesa', [['cuenta' => '43110001', 'debe' => 992], ['tercero' => $this->propietario('17'), 'haber' => 992]]],
            ['cobro', [['cuenta' => '57200001', 'debe' => 992], ['cuenta' => '43110001', 'haber' => 992]]],
        ] as [$evento, $lineas]) {
            $this->servicio->ejecutar($this->datos($lineas, [
                'crear_terceros_desconocidos' => true,
                'referencia'                  => ['tipo' => 'recibo', 'id' => '1', 'evento' => $evento],
            ]));
        }

        // La devolución no se referencia por el recibo: un recibo puede devolverse más de
        // una vez. Se usa el identificador del movimiento bancario.
        $this->servicio->ejecutar($this->datos([
            ['tercero' => $this->propietario('17'), 'debe' => 992],
            ['cuenta' => '57200001', 'haber' => 992],
        ], ['referencia' => ['tipo' => 'devolucion_sepa', 'id' => 'E2E-0001', 'evento' => 'devolucion']]));

        $this->assertSame(992, $this->saldo('43000001'), 'Tras la devolución vuelve a deber el recibo');
        $this->assertSame(0, $this->saldo('57200001'));
    }

    public function test_la_numeracion_es_correlativa_por_ejercicio(): void
    {
        $otro = EjercicioContable::create([
            'empresa_contable_id' => $this->empresa->id,
            'nombre'              => '2027',
            'fecha_inicio'        => '2027-01-01',
            'fecha_fin'           => '2027-12-31',
            'cerrado'             => false,
        ]);

        $lineas = [['cuenta' => '57200001', 'debe' => 100], ['cuenta' => '70500001', 'haber' => 100]];

        $this->assertSame(1, $this->servicio->ejecutar($this->datos($lineas))->numero);
        $this->assertSame(2, $this->servicio->ejecutar($this->datos($lineas))->numero);

        $enOtro = $this->servicio->ejecutar($this->datos($lineas, ['ejercicio' => '2027', 'fecha' => '2027-03-01']));

        $this->assertSame(1, $enOtro->numero, 'Cada ejercicio empieza a numerar de nuevo');
        $this->assertSame($otro->id, $enOtro->ejercicio_contable_id);
    }

    public function test_una_fecha_fuera_del_ejercicio_se_rechaza(): void
    {
        $this->expectException(AsientoInvalidoException::class);

        $this->servicio->ejecutar($this->datos([
            ['cuenta' => '57200001', 'debe' => 100],
            ['cuenta' => '70500001', 'haber' => 100],
        ], ['fecha' => '2027-01-15']));
    }

    public function test_una_linea_con_cuenta_y_tercero_a_la_vez_se_rechaza(): void
    {
        $this->expectException(AsientoInvalidoException::class);

        $this->servicio->ejecutar($this->datos([
            ['cuenta' => '57200001', 'tercero' => $this->propietario('17'), 'debe' => 100],
            ['cuenta' => '70500001', 'haber' => 100],
        ]));
    }

    public function test_un_importe_negativo_se_rechaza(): void
    {
        $this->expectException(AsientoInvalidoException::class);

        $this->servicio->ejecutar($this->datos([
            ['cuenta' => '57200001', 'debe' => -100],
            ['cuenta' => '70500001', 'haber' => -100],
        ]));
    }

    public function test_una_linea_con_debe_y_haber_a_la_vez_se_rechaza(): void
    {
        $this->expectException(AsientoInvalidoException::class);

        $this->servicio->ejecutar($this->datos([
            ['cuenta' => '57200001', 'debe' => 100, 'haber' => 100],
            ['cuenta' => '70500001', 'haber' => 100],
        ]));
    }

    /**
     * La red de seguridad de verdad contra dos peticiones simultáneas no es el código,
     * es el índice único: aunque las dos pasen a la vez la comprobación previa, la
     * segunda no puede llegar a insertar.
     */
    public function test_el_indice_unico_impide_dos_asientos_con_la_misma_referencia(): void
    {
        $asiento = $this->servicio->ejecutar($this->datos([
            ['cuenta' => '57200001', 'debe' => 992],
            ['cuenta' => '70500001', 'haber' => 992],
        ], ['referencia' => ['tipo' => 'recibo', 'id' => '1234', 'evento' => 'emision']]));

        $this->expectException(QueryException::class);

        AsientoContable::create([
            'empresa_contable_id'   => $this->empresa->id,
            'ejercicio_contable_id' => $this->ejercicio->id,
            'numero'                => $asiento->numero + 1,
            'fecha'                 => '2026-01-31',
            'concepto'              => 'Duplicado que no debe entrar',
            'referencia_tipo'       => 'recibo',
            'referencia_id'         => '1234',
            'evento'                => 'emision',
        ]);
    }

    /** Sin referencia no hay idempotencia: son asientos manuales y pueden repetirse. */
    public function test_los_asientos_sin_referencia_se_pueden_repetir(): void
    {
        $lineas = [['cuenta' => '57200001', 'debe' => 100], ['cuenta' => '70500001', 'haber' => 100]];

        $this->servicio->ejecutar($this->datos($lineas));
        $this->servicio->ejecutar($this->datos($lineas));

        $this->assertSame(2, AsientoContable::count());
    }
}
