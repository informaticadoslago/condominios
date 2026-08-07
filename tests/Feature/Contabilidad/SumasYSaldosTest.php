<?php

namespace Tests\Feature\Contabilidad;

use App\Livewire\SumasYSaldos\Lista;
use App\Models\ApunteContable;
use App\Models\AsientoContable;
use App\Models\CuentaContable;
use App\Models\EjercicioContable;
use App\Models\EmpresaContable;
use App\Services\Contabilidad\SaldosContablesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El balance de sumas y saldos entre dos fechas: qué arrastra cada cuenta, qué movió en
 * el periodo y cómo queda. La columna de saldos tiene que sumar cero.
 */
class SumasYSaldosTest extends TestCase
{
    use RefreshDatabase;

    private EmpresaContable $empresa;
    private EjercicioContable $ejercicio;
    private CuentaContable $banco;
    private CuentaContable $propietario;
    private CuentaContable $cuotas;
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

        $this->banco       = $this->cuenta('57200001', 'Banco c/c');
        $this->propietario = $this->cuenta('43000001', 'Aurea Rivas');
        $this->cuotas      = $this->cuenta('75000001', 'Presupuesto 2026');

        session(['empresa_contable_actual_id' => $this->empresa->id]);
    }

    private function cuenta(string $codigo, string $nombre): CuentaContable
    {
        return CuentaContable::create([
            'empresa_contable_id'     => $this->empresa->id,
            'tipo_cuenta_contable_id' => 1,
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

    /** Emisión del recibo en enero y cobro en marzo: cada uno cae de un lado del rango. */
    private function emisionYCobro(): void
    {
        $this->asiento('2026-01-31', $this->propietario, $this->cuotas, 10000);
        $this->asiento('2026-03-15', $this->banco, $this->propietario, 10000);
    }

    public function test_el_arrastre_se_separa_de_lo_del_periodo(): void
    {
        $this->emisionYCobro();

        $balance = app(SaldosContablesService::class)
            ->balance($this->empresa->id, '2026-03-01', '2026-03-31')
            ->orderBy('codigo')
            ->get()
            ->keyBy('codigo');

        // El propietario llega debiendo los 100 de enero y en marzo los paga.
        $this->assertSame(10000, (int) $balance['43000001']->saldo_inicial);
        $this->assertSame(10000, (int) $balance['43000001']->haber);
        $this->assertSame(0, (int) $balance['43000001']->saldo_final);

        // El banco no traía nada y se queda con los 100.
        $this->assertSame(0, (int) $balance['57200001']->saldo_inicial);
        $this->assertSame(10000, (int) $balance['57200001']->debe);
        $this->assertSame(10000, (int) $balance['57200001']->saldo_final);

        // Y la cuenta de ingresos, que no se movió en marzo, sigue en el balance con su
        // arrastre: es lo que hace que la columna de saldos cuadre a cero.
        $this->assertSame(-10000, (int) $balance['75000001']->saldo_inicial);
        $this->assertSame(0, (int) $balance['75000001']->debe);
        $this->assertSame(-10000, (int) $balance['75000001']->saldo_final);
    }

    public function test_los_saldos_suman_cero(): void
    {
        $this->emisionYCobro();

        $totales = app(SaldosContablesService::class)->totalesBalance($this->empresa->id, '2026-03-01', '2026-03-31');

        $this->assertSame(0, $totales['saldo_final']);
        $this->assertSame(10000, $totales['debe']);
        $this->assertSame(10000, $totales['haber']);
    }

    public function test_sin_fecha_de_inicio_todo_es_periodo(): void
    {
        $this->emisionYCobro();

        $balance = app(SaldosContablesService::class)
            ->balance($this->empresa->id, null, '2026-12-31')
            ->get()
            ->keyBy('codigo');

        $this->assertSame(0, (int) $balance['43000001']->saldo_inicial);
        $this->assertSame(10000, (int) $balance['43000001']->debe);
        $this->assertSame(10000, (int) $balance['43000001']->haber);
    }

    public function test_las_cuentas_sin_movimiento_no_salen(): void
    {
        $this->cuenta('62900001', 'Limpieza de escalera');
        $this->emisionYCobro();

        $codigos = app(SaldosContablesService::class)
            ->balance($this->empresa->id, '2026-01-01', '2026-12-31')
            ->pluck('codigo')
            ->all();

        $this->assertContains('57200001', $codigos);
        $this->assertNotContains('62900001', $codigos);
    }

    public function test_la_pantalla_ensena_el_balance_del_ejercicio(): void
    {
        $this->emisionYCobro();

        Livewire::test(Lista::class)
            ->assertSet('filtros.desde', '2026-01-01')
            ->assertSet('filtros.hasta', '2026-12-31')
            ->assertSee('57200001')
            ->assertSee('Banco c/c')
            ->assertSee('100,00');
    }

    public function test_el_corte_por_la_fecha_final_deja_fuera_lo_posterior(): void
    {
        $this->emisionYCobro();

        Livewire::test(Lista::class)
            ->set('filtros.hasta', '2026-02-28')
            // A finales de febrero el cobro de marzo todavía no ha pasado: el banco no
            // tiene apuntes y no aparece en el balance.
            ->assertDontSee('Banco c/c')
            ->assertSee('Aurea Rivas');
    }
}
