<?php

namespace Tests\Feature\Contabilidad;

use App\Livewire\MayorContable\Lista;
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
 * El mayor de una cuenta entre dos fechas: arrastre de lo anterior, acumulado apunte a
 * apunte y totales del periodo.
 */
class MayorContableTest extends TestCase
{
    use RefreshDatabase;

    private EmpresaContable $empresa;
    private EjercicioContable $ejercicio;
    private CuentaContable $banco;
    private CuentaContable $propietario;
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

    /** Un cobro: entra dinero en el banco y el propietario deja de deberlo. */
    private function cobro(string $fecha, int $centimos): AsientoContable
    {
        $asiento = AsientoContable::create([
            'empresa_contable_id'   => $this->empresa->id,
            'ejercicio_contable_id' => $this->ejercicio->id,
            'numero'                => ++$this->numero,
            'fecha'                 => $fecha,
            'concepto'              => 'Cobro de recibo',
        ]);

        ApunteContable::create([
            'asiento_contable_id' => $asiento->id,
            'cuenta_contable_id'  => $this->banco->id,
            'debe'                => $centimos,
        ]);

        ApunteContable::create([
            'asiento_contable_id' => $asiento->id,
            'cuenta_contable_id'  => $this->propietario->id,
            'haber'               => $centimos,
        ]);

        return $asiento;
    }

    public function test_el_saldo_anterior_no_cuenta_lo_del_rango(): void
    {
        $this->cobro('2026-01-15', 10000);
        $this->cobro('2026-03-10', 5000);

        $saldos = app(SaldosContablesService::class);

        $this->assertSame(10000, $saldos->saldoAnteriorA($this->banco->id, '2026-03-01'));
        $this->assertSame(15000, $saldos->saldo($this->banco->id, '2026-12-31'));
        $this->assertSame(['debe' => 5000, 'haber' => 0], $saldos->sumas($this->banco->id, '2026-03-01', '2026-03-31'));

        // La misma jugada vista desde el propietario: le abona, así que su saldo baja.
        $this->assertSame(-10000, $saldos->saldoAnteriorA($this->propietario->id, '2026-03-01'));
    }

    /**
     * La emisión de un presupuesto es un solo asiento con un apunte al debe por
     * propietario: la contrapartida de cada uno es la cuenta de ingresos, no los otros
     * propietarios que iban en el mismo asiento.
     */
    public function test_la_contrapartida_es_el_otro_lado_del_asiento(): void
    {
        $otro   = $this->cuenta('43000002', 'Lucía Rodríguez');
        $cuotas = $this->cuenta('75000001', 'Presupuesto 2026');

        $asiento = AsientoContable::create([
            'empresa_contable_id'   => $this->empresa->id,
            'ejercicio_contable_id' => $this->ejercicio->id,
            'numero'                => ++$this->numero,
            'fecha'                 => '2026-02-01',
            'concepto'              => 'Emisión de recibos',
        ]);

        foreach ([$this->propietario, $otro] as $cliente) {
            ApunteContable::create([
                'asiento_contable_id' => $asiento->id,
                'cuenta_contable_id'  => $cliente->id,
                'debe'                => 6000,
            ]);
        }

        ApunteContable::create([
            'asiento_contable_id' => $asiento->id,
            'cuenta_contable_id'  => $cuotas->id,
            'haber'               => 12000,
        ]);

        $apunte = ApunteContable::where('asiento_contable_id', $asiento->id)
            ->where('cuenta_contable_id', $this->propietario->id)
            ->first();

        $this->assertSame(['75000001'], $apunte->contrapartidas()->pluck('codigo')->all());

        Livewire::test(Lista::class)
            ->set('filtros.cuenta_contable_id', $this->propietario->id)
            ->assertSee('75000001 - Presupuesto 2026');
    }

    public function test_el_mayor_arranca_pidiendo_una_cuenta(): void
    {
        Livewire::test(Lista::class)
            ->assertSee('Elige una cuenta para ver su mayor.');
    }

    public function test_el_acumulado_arrastra_lo_anterior_al_rango(): void
    {
        $this->cobro('2026-01-15', 10000);
        $this->cobro('2026-03-10', 5000);
        $this->cobro('2026-03-20', 2500);

        Livewire::test(Lista::class)
            ->set('filtros.cuenta_contable_id', $this->banco->id)
            ->set('filtros.desde', '2026-03-01')
            ->set('filtros.hasta', '2026-03-31')
            // Saldo anterior, los dos acumulados del rango y el total del periodo.
            ->assertSeeInOrder(['100,00', '150,00', '175,00', '75,00'])
            // El apunte de enero se queda fuera: es arrastre, no movimiento del periodo.
            ->assertDontSee('15/01/2026');
    }

    /** La window function se calcula antes del LIMIT: la página 2 sigue donde acabó la 1. */
    public function test_el_acumulado_no_se_reinicia_en_la_segunda_pagina(): void
    {
        $this->cobro('2026-03-01', 10000);
        $this->cobro('2026-03-02', 10000);
        $this->cobro('2026-03-03', 10000);

        Livewire::test(Lista::class)
            ->set('filtros.cuenta_contable_id', $this->banco->id)
            ->set('lineasXPagina', '2')
            ->call('gotoPage', 2)
            // El tercer apunte llega con 300,00 acumulados, no con los 100,00 suyos.
            ->assertSee('300,00')
            ->assertDontSee('200,00')
            // Y el arrastre solo se pinta en la primera página.
            ->assertDontSee('Saldo anterior');
    }

    public function test_las_fechas_de_fabrica_son_las_del_ejercicio(): void
    {
        Livewire::test(Lista::class)
            ->assertSet('filtros.desde', '2026-01-01')
            ->assertSet('filtros.hasta', '2026-12-31');
    }

    public function test_el_rango_puede_cruzar_ejercicios(): void
    {
        EjercicioContable::create([
            'empresa_contable_id' => $this->empresa->id,
            'nombre'              => '2027',
            'fecha_inicio'        => '2027-01-01',
            'fecha_fin'           => '2027-12-31',
            'cerrado'             => false,
        ]);

        $this->cobro('2026-12-20', 10000);
        $asiento2027 = $this->cobro('2027-01-10', 5000);
        $asiento2027->update(['ejercicio_contable_id' => EjercicioContable::where('nombre', '2027')->value('id')]);

        Livewire::test(Lista::class)
            ->set('filtros.cuenta_contable_id', $this->banco->id)
            ->set('filtros.desde', '2026-12-01')
            ->set('filtros.hasta', '2027-01-31')
            ->assertSee('20/12/2026')
            ->assertSee('10/01/2027')
            ->assertSee('150,00');
    }

    public function test_una_cuenta_de_otra_empresa_no_se_recuerda(): void
    {
        $otra = EmpresaContable::create(['cif' => 'H87654321', 'razon_social' => 'Otra comunidad']);

        $ajena = CuentaContable::create([
            'empresa_contable_id'     => $otra->id,
            'tipo_cuenta_contable_id' => 1,
            'codigo'                  => '57200001',
            'nombre'                  => 'Banco de la otra',
            'estado_id'               => CuentaContable::ESTADO_ACTIVO,
        ]);

        Livewire::test(Lista::class)
            ->set('filtros.cuenta_contable_id', $ajena->id);

        // Al volver a entrar, la preferencia guardada apunta a una cuenta que no es de la
        // empresa activa: se descarta en vez de enseñar su mayor.
        Livewire::test(Lista::class)
            ->assertSet('filtros.cuenta_contable_id', 0)
            ->assertSee('Elige una cuenta para ver su mayor.');
    }
}
