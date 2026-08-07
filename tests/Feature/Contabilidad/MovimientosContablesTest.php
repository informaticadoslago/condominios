<?php

namespace Tests\Feature\Contabilidad;

use App\Livewire\MovimientosContables\Lista;
use App\Models\ApunteContable;
use App\Models\AsientoContable;
use App\Models\CuentaContable;
use App\Models\EjercicioContable;
use App\Models\EmpresaContable;
use App\Models\TipoCuentaContable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El informe de movimientos entre dos fechas: ingresos y gastos mes a mes, el resumen y
 * la justificación del saldo. El cuadre es el de la partida doble: saldo anterior más
 * ingresos menos gastos tiene que dar el saldo que justifican los bancos y las deudas.
 */
class MovimientosContablesTest extends TestCase
{
    use RefreshDatabase;

    private EmpresaContable $empresa;
    private EjercicioContable $ejercicio;
    private CuentaContable $banco;
    private CuentaContable $propietario;
    private CuentaContable $cuotas;
    private CuentaContable $limpieza;
    private CuentaContable $proveedor;
    private int $numero = 0;

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

        $this->banco       = $this->cuenta('57200001', 'ABANCA', TipoCuentaContable::ACTIVO);
        $this->propietario = $this->cuenta('43000001', 'Aurea Rivas', TipoCuentaContable::ACTIVO);
        $this->proveedor   = $this->cuenta('40000001', 'Limpiezas Vigo', TipoCuentaContable::PASIVO);
        $this->cuotas      = $this->cuenta('75000001', 'Presupuesto 2026', TipoCuentaContable::INGRESO);
        $this->limpieza    = $this->cuenta('62900001', 'Limpieza de escalera', TipoCuentaContable::GASTO);

        session(['empresa_contable_actual_id' => $this->empresa->id]);
    }

    private function cuenta(string $codigo, string $nombre, int $tipo): CuentaContable
    {
        return CuentaContable::create([
            'empresa_contable_id'     => $this->empresa->id,
            'tipo_cuenta_contable_id' => $tipo,
            'codigo'                  => $codigo,
            'nombre'                  => $nombre,
            'estado_id'               => CuentaContable::ESTADO_ACTIVO,
        ]);
    }

    private function asiento(string $fecha, CuentaContable $debe, CuentaContable $haber, int $centimos): void
    {
        $asiento = AsientoContable::create([
            'empresa_contable_id'   => $this->empresa->id,
            'ejercicio_contable_id' => $this->ejercicio->id,
            'numero'                => ++$this->numero,
            'fecha'                 => $fecha,
            'concepto'              => 'Asiento de prueba',
        ]);

        ApunteContable::create([
            'asiento_contable_id' => $asiento->id,
            'cuenta_contable_id'  => $debe->id,
            'debe'                => $centimos,
        ]);

        ApunteContable::create([
            'asiento_contable_id' => $asiento->id,
            'cuenta_contable_id'  => $haber->id,
            'haber'               => $centimos,
        ]);
    }

    /** Dos meses de comunidad: cuotas emitidas y cobradas, y la limpieza de febrero. */
    private function dosMeses(): void
    {
        $this->asiento('2026-01-31', $this->propietario, $this->cuotas, 10000);
        $this->asiento('2026-02-05', $this->banco, $this->propietario, 10000);
        $this->asiento('2026-02-28', $this->propietario, $this->cuotas, 10000);
        $this->asiento('2026-02-20', $this->limpieza, $this->proveedor, 3000);
    }

    public function test_los_ingresos_y_los_gastos_salen_mes_a_mes(): void
    {
        $this->dosMeses();

        $componente = Livewire::test(Lista::class)
            ->set('filtros.desde', '2026-01-01')
            ->set('filtros.hasta', '2026-02-28');

        $ingresos = $componente->viewData('ingresos');
        $gastos   = $componente->viewData('gastos');

        $this->assertSame(['2026-01' => 10000, '2026-02' => 10000], $ingresos['totales']);
        $this->assertSame(20000, $ingresos['total']);
        $this->assertSame(['2026-01' => 0, '2026-02' => 3000], $gastos['totales']);
        $this->assertSame(3000, $gastos['total']);

        // El signo se endereza: los dos bloques se leen en positivo.
        $this->assertSame('75000001', $ingresos['filas'][0]['codigo']);
        $this->assertSame(20000, $ingresos['filas'][0]['total']);
        $this->assertSame(3000, $gastos['filas'][0]['total']);
    }

    public function test_el_saldo_anterior_mas_ingresos_menos_gastos_da_el_saldo_final(): void
    {
        $this->dosMeses();

        // Febrero solo: enero (10.000 de cuotas emitidas) queda como saldo anterior.
        $componente = Livewire::test(Lista::class)
            ->set('filtros.desde', '2026-02-01')
            ->set('filtros.hasta', '2026-02-28');

        $saldoAnterior = $componente->viewData('saldoAnterior');
        $saldoFinal    = $componente->viewData('saldoFinal');
        $ingresos      = $componente->viewData('ingresos')['total'];
        $gastos        = $componente->viewData('gastos')['total'];

        $this->assertSame(10000, $saldoAnterior);
        $this->assertSame($saldoAnterior + $ingresos - $gastos, $saldoFinal);
    }

    public function test_la_justificacion_suma_el_saldo_final(): void
    {
        $this->dosMeses();

        $componente = Livewire::test(Lista::class)
            ->set('filtros.desde', '2026-01-01')
            ->set('filtros.hasta', '2026-02-28');

        $justificacion = $componente->viewData('justificacion');

        // Por código: proveedor con 30 por pagar, propietario debiendo 100 y banco con 100.
        $this->assertSame(
            ['40000001' => -3000, '43000001' => 10000, '57200001' => 10000],
            $justificacion->mapWithKeys(fn ($cuenta) => [$cuenta->codigo => (int) $cuenta->saldo])->all(),
        );
        $this->assertSame(17000, $componente->viewData('saldoFinal'));
        $this->assertSame(17000, (int) $justificacion->sum('saldo'));
    }

    public function test_el_rango_puede_no_ser_un_ejercicio_entero(): void
    {
        $this->dosMeses();

        $componente = Livewire::test(Lista::class)
            ->set('filtros.desde', '2026-02-01')
            ->set('filtros.hasta', '2026-02-28');

        // Un solo mes de columnas, y solo lo que pasó en él.
        $this->assertSame(['2026-02'], array_keys($componente->viewData('meses')));
        $this->assertSame(10000, $componente->viewData('ingresos')['total']);
    }

    public function test_sin_las_dos_fechas_no_hay_informe(): void
    {
        Livewire::test(Lista::class)
            ->set('filtros.desde', '')
            ->assertSee('Elige las dos fechas del informe.');
    }
}
