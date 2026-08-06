<?php

namespace Tests\Feature\Contabilidad;

use App\Models\Comunidad;
use App\Models\EmpresaContable;
use App\Models\PersonaComunidad;
use App\Models\Presupuesto;
use App\Models\Propietario;
use App\Models\TipoEstadoPresupuesto;
use App\Models\TipoPresupuesto;
use App\Services\Comunidades\EnlaceContableComunidad;
use Database\Seeders\DemoComunidadSeeder;
use Database\Seeders\PlanCuentasComunidadesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El enganche visto desde la gestión: dar de alta un propietario o aprobar un
 * presupuesto en una comunidad que lleva contabilidad deja la cuenta guardada; en una
 * que no la lleva, no pasa nada.
 */
class EnlaceContableComunidadTest extends TestCase
{
    use RefreshDatabase;

    private Comunidad $comunidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanCuentasComunidadesSeeder::class);

        $this->comunidad = (new DemoComunidadSeeder())->generar()['edificio1'];
    }

    private function enlazarConContabilidad(): EmpresaContable
    {
        $empresa = EmpresaContable::create([
            'cif' => 'H12345678', 'razon_social' => 'Comunidad de prueba',
        ]);

        $this->comunidad->update(['empresa_contable_id' => $empresa->id]);
        $this->comunidad->refresh();

        return $empresa;
    }

    private function crearPropietario(string $documento = '12345678Z'): Propietario
    {
        $persona = PersonaComunidad::create([
            'comunidad_id'             => $this->comunidad->id,
            'nombre'                   => 'Ana',
            'apellido1'                => 'Pérez',
            'documento_identificativo' => $documento,
        ]);

        return Propietario::create(['persona_comunidad_id' => $persona->id]);
    }

    private function crearPresupuesto(int $tipo): Presupuesto
    {
        return Presupuesto::create([
            'comunidad_id'        => $this->comunidad->id,
            'nombre'              => $tipo === TipoPresupuesto::DERRAMA ? 'Derrama grietas' : 'Presupuesto 2026',
            'anho'                => 2026,
            'tipo_presupuesto_id' => $tipo,
            'estado_id'           => TipoEstadoPresupuesto::PROVISIONAL,
        ]);
    }

    public function test_el_alta_de_un_propietario_le_da_su_cuenta_de_cliente(): void
    {
        $this->enlazarConContabilidad();

        $this->assertSame('43000001', $this->crearPropietario()->cuenta_contable);
        $this->assertSame('43000002', $this->crearPropietario('87654321X')->cuenta_contable);
    }

    public function test_una_comunidad_sin_contabilidad_no_genera_ninguna_cuenta(): void
    {
        $propietario = $this->crearPropietario();

        $this->assertNull($propietario->cuenta_contable);
    }

    public function test_el_presupuesto_estrena_su_cuenta_de_ingresos_segun_su_tipo(): void
    {
        $this->enlazarConContabilidad();
        $enlace = app(EnlaceContableComunidad::class);

        $cuotas = $this->crearPresupuesto(TipoPresupuesto::CUOTAS);
        $this->assertSame('75000001', $enlace->asignarCuentaIngresoPresupuesto($cuotas));

        // Dos derramas en el mismo ejercicio, cada una con su cuenta.
        $primera = $this->crearPresupuesto(TipoPresupuesto::DERRAMA);
        $segunda = $this->crearPresupuesto(TipoPresupuesto::DERRAMA);

        $this->assertSame('75010001', $enlace->asignarCuentaIngresoPresupuesto($primera));
        $this->assertSame('75010002', $enlace->asignarCuentaIngresoPresupuesto($segunda));

        $this->assertSame('75010001', $primera->fresh()->cuenta_contable);
    }

    public function test_volver_a_pedir_la_cuenta_del_presupuesto_no_crea_otra(): void
    {
        $this->enlazarConContabilidad();
        $enlace = app(EnlaceContableComunidad::class);

        $presupuesto = $this->crearPresupuesto(TipoPresupuesto::CUOTAS);

        $this->assertSame('75000001', $enlace->asignarCuentaIngresoPresupuesto($presupuesto));
        $this->assertSame('75000001', $enlace->asignarCuentaIngresoPresupuesto($presupuesto->fresh()));

        // Y tampoco si se le olvida lo que tenía guardado: la contabilidad reconoce al
        // mismo presupuesto por su etiqueta y devuelve la cuenta que ya le dio.
        $presupuesto->update(['cuenta_contable' => null]);

        $this->assertSame('75000001', $enlace->asignarCuentaIngresoPresupuesto($presupuesto->fresh()));
    }

    public function test_un_presupuesto_de_comunidad_sin_contabilidad_no_estrena_cuenta(): void
    {
        $presupuesto = $this->crearPresupuesto(TipoPresupuesto::CUOTAS);

        $this->assertNull(app(EnlaceContableComunidad::class)->asignarCuentaIngresoPresupuesto($presupuesto));
    }
}
