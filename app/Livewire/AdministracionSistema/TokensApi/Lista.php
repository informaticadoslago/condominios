<?php

namespace App\Livewire\AdministracionSistema\TokensApi;

use App\Livewire\ListaComponent;
use App\Models\Configuracion;
use App\Models\EmpresaContable;
use App\Models\User;
use App\Support\HabilidadToken;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Los tokens de API de TODOS los usuarios, y la caducidad con la que nacen los nuevos.
 *
 * Cada uno se hace los suyos en su pantalla (App\Livewire\TokensApi\Lista); aquí se ven
 * todos y se revoca el de cualquiera. Permiso 'configuracion-token'.
 */
class Lista extends ListaComponent
{
    /** Duración elegida para los tokens nuevos; '' = no caducan. */
    public string $caducidad = '';

    public function mount()
    {
        $this->sort      = 'created_at';
        $this->direction = 'desc';

        $this->caducidad = (string) Configuracion::valor(Configuracion::CADUCIDAD_TOKENS, '');
    }

    protected function columnasOrdenables(): ?array
    {
        return ['name', 'created_at', 'last_used_at', 'expires_at'];
    }

    /**
     * Guarda al cambiar el desplegable. No toca los tokens que ya existen: cada uno se
     * llevó su fecha puesta el día que se creó.
     */
    public function updatedCaducidad($valor)
    {
        $this->validate([
            'caducidad' => ['nullable', Rule::in(array_keys(Configuracion::DURACIONES_TOKENS))],
        ]);

        Configuracion::poner(Configuracion::CADUCIDAD_TOKENS, $valor);

        $this->dispatch('toast-success', ['title' => __('Caducidad guardada')]);
    }

    public function revocar($id)
    {
        PersonalAccessToken::whereKey($id)->delete();

        $this->dispatch('toast-success', ['title' => __('Token revocado')]);
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = PersonalAccessToken::with('tokenable')
            ->where('tokenable_type', User::class)
            ->when($search, function ($q) use ($search) {
                // Por el nombre del token o por quien lo tiene.
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereIn('tokenable_id', User::where('login', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->pluck('id'));
            })
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        // De qué empresa es cada token: la habilidad que lleva dentro.
        $empresas = EmpresaContable::pluck('razon_social', 'id');

        $items->each(function ($token) use ($empresas) {
            $id = collect($token->abilities ?? [])
                ->map(fn ($h) => str_starts_with($h, 'empresa-contable:')
                    ? (int) substr($h, strlen('empresa-contable:'))
                    : null)
                ->filter()
                ->first();

            $token->empresaContable = $id ? ($empresas[$id] ?? __('(empresa borrada)')) : null;
            $token->escribe         = in_array(HabilidadToken::ESCRIBIR, $token->abilities ?? [], true);
        });

        return view('livewire.administracion-sistema.tokens-api.lista', [
            'items'      => $items,
            'duraciones' => Configuracion::DURACIONES_TOKENS,
        ]);
    }
}
