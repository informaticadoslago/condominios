<?php

namespace Tests\Feature\Contabilidad;

use App\Models\CuentaContable;
use App\Models\EmpresaContable;
use App\Models\User;
use Database\Seeders\PlanCuentasComunidadesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Las dos altas que le pide la gestión a la contabilidad antes de poder cobrar nada:
 * quién paga (el propietario, cliente) y por qué concepto (el presupuesto o la derrama).
 */
class ApiAltasContablesTest extends TestCase
{
    use RefreshDatabase;

    private EmpresaContable $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        // La empresa copia el plan global al crearse: hay que sembrarlo antes.
        $this->seed(PlanCuentasComunidadesSeeder::class);

        $this->empresa = EmpresaContable::create([
            'cif' => 'H12345678', 'razon_social' => 'Comunidad de prueba',
        ]);
    }

    private function autenticar(bool $conAcceso = true): User
    {
        // Sin UserFactory: la de serie inserta una columna «name» que esta tabla users
        // no tiene. Solo login, email y password son obligatorios sin valor por defecto.
        $user = User::forceCreate([
            'login'    => 'tester',
            'email'    => 'tester@example.test',
            'password' => bcrypt('secreto'),
        ]);

        if ($conAcceso) {
            $user->assignRole($this->empresa->nombreRol());
        }

        Sanctum::actingAs($user);

        return $user;
    }

    private function cuerpoTercero(array $extra = []): array
    {
        return array_merge([
            'empresa_contable_id' => $this->empresa->id,
            'clase'               => 'cliente',
            'nif'                 => '12345678Z',
            'razon_social'        => 'Pérez Gómez, Ana',
            'sujeto'              => ['tipo' => 'propietario', 'id' => '7'],
        ], $extra);
    }

    private function cuerpoIngreso(array $extra = []): array
    {
        return array_merge([
            'empresa_contable_id' => $this->empresa->id,
            'clase'               => 'cuotas',
            'nombre'              => 'Presupuesto 2026',
            'sujeto'              => ['tipo' => 'presupuesto', 'id' => '1'],
        ], $extra);
    }

    public function test_alta_de_propietario_devuelve_su_cuenta_de_cliente(): void
    {
        $this->autenticar();

        $this->postJson('/api/contabilidad/terceros', $this->cuerpoTercero())
            ->assertStatus(201)
            ->assertJsonPath('cuenta', '43000001');

        // El segundo propietario se lleva el siguiente correlativo del mismo grupo.
        $this->postJson('/api/contabilidad/terceros', $this->cuerpoTercero([
            'nif' => '87654321X', 'razon_social' => 'Luna Vera, Beatriz',
            'sujeto' => ['tipo' => 'propietario', 'id' => '8'],
        ]))
            ->assertStatus(201)
            ->assertJsonPath('cuenta', '43000002');
    }

    public function test_repetir_el_alta_del_mismo_propietario_devuelve_la_misma_cuenta(): void
    {
        $this->autenticar();

        $this->postJson('/api/contabilidad/terceros', $this->cuerpoTercero())->assertStatus(201);

        // 200, no 201: ya existía. Y sin crear una segunda subcuenta.
        $this->postJson('/api/contabilidad/terceros', $this->cuerpoTercero())
            ->assertStatus(200)
            ->assertJsonPath('cuenta', '43000001');

        $this->assertSame(1, CuentaContable::where('empresa_contable_id', $this->empresa->id)
            ->where('codigo', 'like', '4300%')->where('codigo', '!=', '43000000')->count());
    }

    public function test_presupuesto_de_cuotas_y_derramas_van_a_grupos_distintos(): void
    {
        $this->autenticar();

        $this->postJson('/api/contabilidad/cuentas-ingreso', $this->cuerpoIngreso())
            ->assertStatus(201)
            ->assertJsonPath('cuenta', '75000001');

        // Cada derrama del ejercicio tiene la suya, para poder verlas por separado.
        $this->postJson('/api/contabilidad/cuentas-ingreso', $this->cuerpoIngreso([
            'clase' => 'derramas', 'nombre' => 'Derrama grietas',
            'sujeto' => ['tipo' => 'presupuesto', 'id' => '2'],
        ]))
            ->assertStatus(201)
            ->assertJsonPath('cuenta', '75010001');

        $this->postJson('/api/contabilidad/cuentas-ingreso', $this->cuerpoIngreso([
            'clase' => 'derramas', 'nombre' => 'Derrama ascensor',
            'sujeto' => ['tipo' => 'presupuesto', 'id' => '3'],
        ]))
            ->assertStatus(201)
            ->assertJsonPath('cuenta', '75010002');
    }

    public function test_repetir_el_alta_del_mismo_presupuesto_devuelve_la_misma_cuenta(): void
    {
        $this->autenticar();

        $this->postJson('/api/contabilidad/cuentas-ingreso', $this->cuerpoIngreso())->assertStatus(201);

        $this->postJson('/api/contabilidad/cuentas-ingreso', $this->cuerpoIngreso())
            ->assertStatus(200)
            ->assertJsonPath('cuenta', '75000001');
    }

    public function test_una_clase_de_ingreso_que_no_existe_se_rechaza(): void
    {
        $this->autenticar();

        $this->postJson('/api/contabilidad/cuentas-ingreso', $this->cuerpoIngreso(['clase' => 'inventada']))
            ->assertStatus(422);
    }

    public function test_sin_acceso_a_esa_empresa_contable_no_se_puede_dar_de_alta_ni_leer(): void
    {
        $this->autenticar(conAcceso: false);

        $this->postJson('/api/contabilidad/terceros', $this->cuerpoTercero())->assertStatus(403);
        $this->postJson('/api/contabilidad/cuentas-ingreso', $this->cuerpoIngreso())->assertStatus(403);
    }

    public function test_sin_token_devuelve_401(): void
    {
        $this->postJson('/api/contabilidad/terceros', $this->cuerpoTercero())->assertStatus(401);
        $this->postJson('/api/contabilidad/cuentas-ingreso', $this->cuerpoIngreso())->assertStatus(401);
    }
}
