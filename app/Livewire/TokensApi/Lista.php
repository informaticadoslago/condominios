<?php

namespace App\Livewire\TokensApi;

use App\Models\Configuracion;
use App\Models\EmpresaContable;
use App\Support\HabilidadToken;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Los tokens de API del usuario que está dentro. Cada uno vale para UNA empresa
 * contable —la que elige aquí, de entre las que le abre su rol— y para ninguna más.
 *
 * No usa la pantalla de Jetstream (Features::api() sigue apagado) porque su lista de
 * permisos es fija y global: enseñaría a todo el mundo todas las empresas.
 */
class Lista extends Component
{
    public string $empresa_contable_id = '';

    public string $nombre = '';

    /** Si el token podrá escribir (asientos, altas) o solo consultar. */
    public bool $escribir = true;

    /**
     * El token en claro. Se enseña UNA vez, al crearlo, y no se vuelve a saber: en la
     * base solo queda su hash.
     */
    public ?string $tokenNuevo = null;

    protected function rules()
    {
        return [
            'empresa_contable_id' => [
                'required',
                Rule::in(auth()->user()->empresasContablesAccesibles()->pluck('id')),
            ],
            'nombre' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'in'       => 'No tiene acceso a esa empresa contable',
            'max'      => 'Máxima longitud de :attribute = :max',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'empresa_contable_id' => __('empresa contable'),
            'nombre'              => __('nombre'),
        ];
    }

    public function crear()
    {
        $this->validate();

        $empresa = auth()->user()->empresasContablesAccesibles()
            ->firstWhere('id', (int) $this->empresa_contable_id);

        $habilidades = [$empresa->habilidadToken()];

        if ($this->escribir) {
            $habilidades[] = HabilidadToken::ESCRIBIR;
        }

        // Uno por empresa y alcance: un segundo token igual no sirve para nada y es otra
        // puerta más que vigilar. Para cambiarlo se revoca el que hay y se hace otro.
        if ($this->yaTieneUno($habilidades)) {
            $this->addError('empresa_contable_id', __(
                'Ya tiene un token para esa empresa con ese alcance. Por favor, revoque el que tiene antes de crear otro.'
            ));

            return;
        }

        // La caducidad la fija el administrador en su pantalla y se resuelve aquí: el
        // token nace con su fecha puesta y cambiarla después no le afecta.
        $this->tokenNuevo = auth()->user()->createToken(
            trim($this->nombre) ?: $empresa->razon_social,
            $habilidades,
            Configuracion::caducidadTokensApi()
        )->plainTextToken;

        $this->reset(['nombre', 'empresa_contable_id', 'escribir']);
        $this->dispatch('toast-success', ['title' => __('Token creado')]);
    }

    /**
     * Si ya tiene un token vigente con exactamente esas habilidades. Los caducados no
     * cuentan: no sirven para entrar, así que no estorban para hacerse otro.
     */
    private function yaTieneUno(array $habilidades): bool
    {
        sort($habilidades);

        return auth()->user()->tokens()
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get()
            ->contains(function ($token) use ($habilidades) {
                $suyas = $token->abilities ?? [];
                sort($suyas);

                return $suyas === $habilidades;
            });
    }

    public function revocar($id)
    {
        // whereKey sobre sus propios tokens: el id que llega del navegador no puede
        // servir para revocar el de otro usuario.
        auth()->user()->tokens()->whereKey($id)->delete();

        $this->tokenNuevo = null;
        $this->dispatch('toast-success', ['title' => __('Token revocado')]);
    }

    public function olvidarToken()
    {
        $this->tokenNuevo = null;
    }

    public function render()
    {
        $empresas = auth()->user()->empresasContablesAccesibles()->sortBy('razon_social');
        $nombres  = $empresas->pluck('razon_social', 'id');

        $items = auth()->user()->tokens()->latest()->get()->map(function ($token) use ($nombres) {
            // De qué empresa es este token: la habilidad que lleva dentro. Los tokens
            // viejos, de antes de esto, no llevan ninguna y valen para todas.
            $id = collect($token->abilities ?? [])
                ->map(fn ($h) => str_starts_with($h, 'empresa-contable:')
                    ? (int) substr($h, strlen('empresa-contable:'))
                    : null)
                ->filter()
                ->first();

            $token->empresaContable = $id ? ($nombres[$id] ?? __('(empresa borrada)')) : null;
            $token->escribe         = in_array(HabilidadToken::ESCRIBIR, $token->abilities ?? [], true);

            return $token;
        });

        return view('livewire.tokens-api.lista', compact('items', 'empresas'));
    }
}
