<?php

namespace Tests\Feature;

use App\Livewire\TokensApi\Lista;
use App\Models\EmpresaContable;
use App\Models\User;
use App\Support\HabilidadToken;
use Database\Seeders\PlanCuentasComunidadesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TokensApiTest extends TestCase
{
    use RefreshDatabase;

    private EmpresaContable $empresa;

    private EmpresaContable $otra;

    protected function setUp(): void
    {
        parent::setUp();

        // La empresa copia el plan global al crearse: hay que sembrarlo antes.
        $this->seed(PlanCuentasComunidadesSeeder::class);

        $this->empresa = EmpresaContable::create(['cif' => 'H12345678', 'razon_social' => 'La suya']);
        $this->otra    = EmpresaContable::create(['cif' => 'H87654321', 'razon_social' => 'La de otro']);
    }

    private function usuario(): User
    {
        $user = User::forceCreate([
            'login'    => 'tester',
            'email'    => 'tester@example.test',
            'password' => bcrypt('secreto'),
        ]);

        $user->assignRole($this->empresa->nombreRol());

        return $user;
    }

    public function test_el_token_nace_con_la_habilidad_de_la_empresa_elegida(): void
    {
        $user = $this->usuario();

        Livewire::actingAs($user)->test(Lista::class)
            ->set('empresa_contable_id', (string) $this->empresa->id)
            ->set('nombre', 'Gestión')
            ->call('crear')
            ->assertHasNoErrors()
            ->assertSet('tokenNuevo', fn ($token) => is_string($token) && $token !== '');

        $token = $user->tokens()->first();

        $this->assertSame('Gestión', $token->name);
        // Por defecto el token escribe; la pantalla deja elegir «solo leer».
        $this->assertSame(
            [$this->empresa->habilidadToken(), HabilidadToken::ESCRIBIR],
            $token->abilities
        );
    }

    public function test_el_token_de_solo_lectura_no_lleva_la_habilidad_de_escribir(): void
    {
        $user = $this->usuario();

        Livewire::actingAs($user)->test(Lista::class)
            ->set('empresa_contable_id', (string) $this->empresa->id)
            ->set('escribir', false)
            ->call('crear')
            ->assertHasNoErrors();

        $this->assertSame([$this->empresa->habilidadToken()], $user->tokens()->first()->abilities);
    }

    public function test_no_deja_dos_tokens_iguales(): void
    {
        $user = $this->usuario();

        Livewire::actingAs($user)->test(Lista::class)
            ->set('empresa_contable_id', (string) $this->empresa->id)
            ->call('crear')
            ->assertHasNoErrors()
            ->set('empresa_contable_id', (string) $this->empresa->id)
            ->call('crear')
            ->assertHasErrors('empresa_contable_id');

        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_uno_de_lectura_y_otro_de_escritura_si_conviven(): void
    {
        $user = $this->usuario();

        Livewire::actingAs($user)->test(Lista::class)
            ->set('empresa_contable_id', (string) $this->empresa->id)
            ->call('crear')
            ->set('empresa_contable_id', (string) $this->empresa->id)
            ->set('escribir', false)
            ->call('crear')
            ->assertHasNoErrors();

        $this->assertSame(2, $user->tokens()->count());
    }

    public function test_un_token_caducado_no_impide_hacerse_otro(): void
    {
        $user  = $this->usuario();
        $token = $user->createToken('El viejo', [$this->empresa->habilidadToken(), HabilidadToken::ESCRIBIR]);
        $token->accessToken->forceFill(['expires_at' => now()->subDay()])->save();

        Livewire::actingAs($user)->test(Lista::class)
            ->set('empresa_contable_id', (string) $this->empresa->id)
            ->call('crear')
            ->assertHasNoErrors();

        $this->assertSame(2, $user->tokens()->count());
    }

    public function test_sin_nombre_el_token_se_llama_como_la_empresa(): void
    {
        $user = $this->usuario();

        Livewire::actingAs($user)->test(Lista::class)
            ->set('empresa_contable_id', (string) $this->empresa->id)
            ->call('crear')
            ->assertHasNoErrors();

        $this->assertSame('La suya', $user->tokens()->first()->name);
    }

    public function test_no_se_puede_crear_para_una_empresa_a_la_que_no_tiene_acceso(): void
    {
        $user = $this->usuario();

        Livewire::actingAs($user)->test(Lista::class)
            ->set('empresa_contable_id', (string) $this->otra->id)
            ->call('crear')
            ->assertHasErrors('empresa_contable_id');

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_solo_ve_y_revoca_los_suyos(): void
    {
        $user  = $this->usuario();
        $ajeno = User::forceCreate([
            'login'    => 'otro',
            'email'    => 'otro@example.test',
            'password' => bcrypt('secreto'),
        ]);
        $tokenAjeno = $ajeno->createToken('El de otro', [$this->otra->habilidadToken()]);

        $user->createToken('El mío', [$this->empresa->habilidadToken()]);

        Livewire::actingAs($user)->test(Lista::class)
            ->assertSee('El mío')
            ->assertDontSee('El de otro')
            ->call('revocar', $tokenAjeno->accessToken->id)
            ->assertHasNoErrors();

        // El de otro sigue vivo: el id que llega del navegador solo revoca los propios.
        $this->assertSame(1, $ajeno->tokens()->count());
    }

    public function test_revocar_borra_el_token(): void
    {
        $user  = $this->usuario();
        $token = $user->createToken('El mío', [$this->empresa->habilidadToken()]);

        Livewire::actingAs($user)->test(Lista::class)
            ->call('revocar', $token->accessToken->id);

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }
}
