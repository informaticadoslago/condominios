<?php

namespace Tests\Feature;

use App\Livewire\AdministracionSistema\TokensApi\Lista;
use App\Livewire\TokensApi\Lista as TokensDelUsuario;
use App\Models\Configuracion;
use App\Models\EmpresaContable;
use App\Models\User;
use Database\Seeders\PlanCuentasComunidadesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminTokensApiTest extends TestCase
{
    use RefreshDatabase;

    private EmpresaContable $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanCuentasComunidadesSeeder::class);

        $this->empresa = EmpresaContable::create(['cif' => 'H12345678', 'razon_social' => 'La suya']);

        Permission::firstOrCreate(['name' => 'configuracion-token', 'guard_name' => 'web']);
    }

    private function usuario(string $login = 'tester', bool $administra = true): User
    {
        $user = User::forceCreate([
            'login'    => $login,
            'email'    => $login.'@example.test',
            'password' => bcrypt('secreto'),
        ]);

        $user->assignRole($this->empresa->nombreRol());

        if ($administra) {
            $user->givePermissionTo('configuracion-token');
        }

        return $user;
    }

    public function test_la_caducidad_elegida_se_guarda(): void
    {
        Livewire::actingAs($this->usuario())->test(Lista::class)
            ->set('caducidad', '+30 days')
            ->assertHasNoErrors();

        $this->assertSame('+30 days', Configuracion::valor(Configuracion::CADUCIDAD_TOKENS));
    }

    public function test_una_duracion_inventada_se_rechaza(): void
    {
        Livewire::actingAs($this->usuario())->test(Lista::class)
            ->set('caducidad', '+3 siglos')
            ->assertHasErrors('caducidad');

        $this->assertNull(Configuracion::valor(Configuracion::CADUCIDAD_TOKENS));
    }

    public function test_el_token_nuevo_nace_con_la_caducidad_configurada(): void
    {
        Configuracion::poner(Configuracion::CADUCIDAD_TOKENS, '+30 days');

        $user = $this->usuario();

        Livewire::actingAs($user)->test(TokensDelUsuario::class)
            ->set('empresa_contable_id', (string) $this->empresa->id)
            ->call('crear')
            ->assertHasNoErrors();

        $this->assertTrue(
            $user->tokens()->first()->expires_at->isSameDay(now()->addDays(30))
        );
    }

    public function test_sin_caducidad_configurada_el_token_no_caduca(): void
    {
        $user = $this->usuario();

        Livewire::actingAs($user)->test(TokensDelUsuario::class)
            ->set('empresa_contable_id', (string) $this->empresa->id)
            ->call('crear');

        $this->assertNull($user->tokens()->first()->expires_at);
    }

    public function test_cambiar_la_caducidad_no_toca_los_tokens_que_ya_existen(): void
    {
        $user  = $this->usuario();
        $token = $user->createToken('El de antes', [$this->empresa->habilidadToken()]);

        Livewire::actingAs($user)->test(Lista::class)->set('caducidad', '+1 year');

        $this->assertNull($token->accessToken->fresh()->expires_at);
    }

    public function test_el_administrador_revoca_el_token_de_otro(): void
    {
        $admin = $this->usuario();
        $otro  = $this->usuario('otro', administra: false);
        $token = $otro->createToken('El de otro', [$this->empresa->habilidadToken()]);

        Livewire::actingAs($admin)->test(Lista::class)
            ->assertSee('El de otro')
            ->assertSee('otro')
            ->call('revocar', $token->accessToken->id);

        $this->assertSame(0, $otro->tokens()->count());
    }

    public function test_sin_el_permiso_no_se_entra(): void
    {
        $this->actingAs($this->usuario('pelagatos', administra: false))
            ->get('/administracion-sistema/tokens-api')
            ->assertStatus(403);
    }

}
