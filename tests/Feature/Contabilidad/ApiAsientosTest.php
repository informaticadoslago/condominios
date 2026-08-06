<?php

namespace Tests\Feature\Contabilidad;

use App\Models\AsientoContable;
use App\Models\CuentaContable;
use App\Models\EjercicioContable;
use App\Models\EmpresaContable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAsientosTest extends TestCase
{
    use RefreshDatabase;

    private EmpresaContable $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = EmpresaContable::create([
            'cif' => 'H12345678', 'razon_social' => 'Comunidad de prueba',
        ]);

        EjercicioContable::create([
            'empresa_contable_id' => $this->empresa->id,
            'nombre'              => '2026',
            'fecha_inicio'        => '2026-01-01',
            'fecha_fin'           => '2026-12-31',
            'cerrado'             => false,
        ]);

        foreach ([['57200001', 'Bancos c/c', 1], ['70500001', 'Cuotas ordinarias', 4]] as [$codigo, $nombre, $tipo]) {
            CuentaContable::create([
                'empresa_contable_id'     => $this->empresa->id,
                'tipo_cuenta_contable_id' => $tipo,
                'codigo'                  => $codigo,
                'nombre'                  => $nombre,
                'estado_id'               => CuentaContable::ESTADO_ACTIVO,
            ]);
        }
    }

    private function autenticar(): void
    {
        // Sin UserFactory: la de serie inserta una columna «name» que esta tabla users
        // no tiene. Solo login, email y password son obligatorios sin valor por defecto.
        Sanctum::actingAs(User::forceCreate([
            'login'    => 'tester',
            'email'    => 'tester@example.test',
            'password' => bcrypt('secreto'),
        ]));
    }

    private function cuerpo(array $extra = []): array
    {
        return array_merge([
            'empresa_contable_id' => $this->empresa->id,
            'ejercicio'           => '2026',
            'fecha'               => '2026-01-31',
            'diario'              => 'REC',
            'concepto'            => 'Recibo enero 2026',
            'lineas'              => [
                ['cuenta' => '57200001', 'debe' => 992],
                ['cuenta' => '70500001', 'haber' => 992],
            ],
        ], $extra);
    }

    public function test_sin_token_devuelve_401(): void
    {
        $this->postJson('/api/contabilidad/asientos', $this->cuerpo())->assertStatus(401);
    }

    public function test_un_asiento_valido_devuelve_201(): void
    {
        $this->autenticar();

        $this->postJson('/api/contabilidad/asientos', $this->cuerpo())
            ->assertStatus(201)
            ->assertJsonPath('numero', 1)
            ->assertJsonPath('ejercicio', '2026')
            ->assertJsonPath('lineas.0.cuenta', '57200001')
            ->assertJsonPath('lineas.0.debe', 992)
            ->assertJsonPath('lineas.1.haber', 992);
    }

    public function test_repetir_la_misma_referencia_devuelve_200_y_no_duplica(): void
    {
        $this->autenticar();

        $cuerpo = $this->cuerpo(['referencia' => ['tipo' => 'recibo', 'id' => 1234, 'evento' => 'emision']]);

        $primera = $this->postJson('/api/contabilidad/asientos', $cuerpo)->assertStatus(201);
        $segunda = $this->postJson('/api/contabilidad/asientos', $cuerpo)->assertStatus(200);

        $this->assertSame($primera->json('id'), $segunda->json('id'));
        $this->assertSame(1, AsientoContable::count());
        // El id de la referencia se normaliza a texto aunque llegue como número.
        $this->assertSame('1234', $segunda->json('referencia.id'));
    }

    public function test_un_asiento_descuadrado_devuelve_422(): void
    {
        $this->autenticar();

        $this->postJson('/api/contabilidad/asientos', $this->cuerpo([
            'lineas' => [
                ['cuenta' => '57200001', 'debe' => 1000],
                ['cuenta' => '70500001', 'haber' => 992],
            ],
        ]))->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'no cuadra'));
    }

    public function test_un_ejercicio_cerrado_devuelve_409(): void
    {
        $this->autenticar();

        EjercicioContable::where('empresa_contable_id', $this->empresa->id)->update(['cerrado' => true]);

        $this->postJson('/api/contabilidad/asientos', $this->cuerpo())->assertStatus(409);
    }

    public function test_una_cuenta_inexistente_devuelve_422(): void
    {
        $this->autenticar();

        $this->postJson('/api/contabilidad/asientos', $this->cuerpo([
            'lineas' => [
                ['cuenta' => '99999999', 'debe' => 992],
                ['cuenta' => '70500001', 'haber' => 992],
            ],
        ]))->assertStatus(422);
    }

    public function test_un_tercero_desconocido_sin_autorizacion_devuelve_422(): void
    {
        $this->autenticar();

        $this->postJson('/api/contabilidad/asientos', $this->cuerpo([
            'lineas' => [
                ['tercero' => ['tipo' => 'propietario', 'id' => '17', 'clase' => 'cliente'], 'debe' => 992],
                ['cuenta' => '70500001', 'haber' => 992],
            ],
        ]))->assertStatus(422);
    }

    public function test_una_sola_linea_no_pasa_la_validacion_de_forma(): void
    {
        $this->autenticar();

        $this->postJson('/api/contabilidad/asientos', $this->cuerpo([
            'lineas' => [['cuenta' => '57200001', 'debe' => 992]],
        ]))->assertStatus(422)->assertJsonValidationErrors('lineas');
    }
}
