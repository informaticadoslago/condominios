<?php

namespace App\Livewire\PlanDeCuentas;

use App\Livewire\Traits\ConEmpresaContableActiva;
use App\Models\CuentaContable;
use App\Models\TipoCuentaContable;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    use ConEmpresaContableActiva;

    public bool $abrir = false;
    public ?int $itemId = null;

    // Fijada por sesión, nunca por el cliente.
    #[Locked]
    public ?int $empresa_contable_id = null;

    public string $codigo = '';
    public string $nombre = '';
    public ?int $tipo_cuenta_contable_id = null;

    protected function rules()
    {
        return [
            // De 1 a 3 cifras es un nivel del PGC (grupo, subgrupo o cuenta); 8, una cuenta.
            'codigo'                  => [
                'required', 'regex:/^(\d{1,3}|\d{8})$/',
                Rule::unique('cuenta_contables', 'codigo')
                    ->where(fn ($q) => $q->where('empresa_contable_id', $this->empresa_contable_id))
                    ->ignore($this->itemId),
            ],
            'nombre'                  => ['required', 'string', 'max:150'],
            // El grupo no tiene naturaleza propia: del 4 cuelgan activo y pasivo a la vez.
            'tipo_cuenta_contable_id' => [$this->esAgrupacion() ? 'nullable' : 'required', 'exists:tipo_cuenta_contables,id'],
        ];
    }

    protected function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'max'      => 'Máxima longitud de :attribute = :max',
            'regex'    => 'El :attribute lleva 8 dígitos, o de 1 a 3 (grupo, subgrupo o cuenta)',
            'unique'   => 'Ya existe una cuenta con ese código',
            'exists'   => 'El :attribute seleccionado no es válido',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'codigo'                  => __('código'),
            'nombre'                  => __('nombre'),
            'tipo_cuenta_contable_id' => __('tipo'),
        ];
    }

    #[On('abrir-crear-cuenta-contable')]
    public function crear($codigoPrefijo = null)
    {
        $this->reset(['itemId', 'codigo', 'nombre', 'tipo_cuenta_contable_id']);
        $this->resetValidation();
        $this->empresa_contable_id = $this->empresaContableActual()?->id;
        $this->codigo = $codigoPrefijo ? substr(preg_replace('/\D/', '', $codigoPrefijo), 0, 8) : '';
        $this->abrir = true;
    }

    /** Nivel del PGC (hasta 3 cifras): no lleva tipo ni se autonumera. */
    public function esAgrupacion(): bool
    {
        return strlen(preg_replace('/\D/', '', (string) $this->codigo)) <= CuentaContable::CIFRAS_AGRUPACION;
    }

    /**
     * Con el prefijo tecleado en Código (4-7 dígitos), completa el siguiente código de 8
     * libre de ese grupo. Con 3 cifras o menos no toca nada: eso no es un prefijo a
     * medias, es el código de un grupo, un subgrupo o una cuenta del PGC.
     */
    public function siguienteCodigo(): void
    {
        $prefijo = substr(preg_replace('/\D/', '', $this->codigo), 0, 8);

        if (strlen($prefijo) <= CuentaContable::CIFRAS_AGRUPACION || strlen($prefijo) >= 8) {
            return;
        }

        $ultimo = CuentaContable::where('empresa_contable_id', $this->empresa_contable_id)
            ->where('codigo', 'like', $prefijo.str_repeat('_', 8 - strlen($prefijo)))
            ->orderByDesc('codigo')
            ->value('codigo');

        $siguiente = str_pad((string) ($ultimo ? ((int) $ultimo) + 1 : (int) str_pad($prefijo, 8, '0')), 8, '0', STR_PAD_LEFT);

        // Si el +1 se sale del grupo (subcuentas 0001-9999 ya agotadas), no autocompletar.
        if (! str_starts_with($siguiente, $prefijo)) {
            return;
        }

        $this->codigo = $siguiente;

        // Sugiere el tipo de la cuenta de la que va a colgar, si existe.
        if (! $this->tipo_cuenta_contable_id) {
            $padre = CuentaContable::padreDe($siguiente, $this->empresa_contable_id);

            if ($padre) {
                $this->tipo_cuenta_contable_id = $padre->tipo_cuenta_contable_id;
            }
        }
    }

    #[On('cuenta-contable-editar')]
    public function editar($id)
    {
        $item = CuentaContable::find($id);
        if (! $item || $item->estado_id != CuentaContable::ESTADO_ACTIVO) {
            return;
        }
        $this->itemId                  = $item->id;
        $this->empresa_contable_id     = $item->empresa_contable_id;
        $this->codigo                  = $item->codigo;
        $this->nombre                  = $item->nombre;
        $this->tipo_cuenta_contable_id = $item->tipo_cuenta_contable_id;
        $this->resetValidation();
        $this->abrir = true;
    }

    public function guardar()
    {
        $data = $this->validate();

        // Cuelga sola del ancestro más cercano que exista. Se recalcula también al editar:
        // si el código cambia, cambia de sitio en el árbol.
        $data['cuenta_padre_id'] = CuentaContable::padreDe($data['codigo'], $this->empresa_contable_id)?->id;

        if ($this->itemId) {
            $cuenta = CuentaContable::findOrFail($this->itemId);
            $cuenta->update($data);
            $this->dispatch('toast-success', ['title' => __('Cuenta modificada')]);
        } else {
            $cuenta = CuentaContable::create($data + [
                'empresa_contable_id' => $this->empresa_contable_id,
                'estado_id'           => CuentaContable::ESTADO_ACTIVO,
            ]);
            $this->dispatch('toast-success', ['title' => __('Cuenta creada')]);
        }

        // Una cuenta intermedia recién creada se lleva a los nietos que colgaban del abuelo.
        CuentaContable::recolgarPlan($this->empresa_contable_id);

        $this->dispatch('cuenta-contable-guardada', cuenta: ['id' => $cuenta->id, 'codigo' => $cuenta->codigo, 'nombre' => $cuenta->nombre]);
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.plan-de-cuentas.formulario', [
            'tipos' => TipoCuentaContable::orderBy('id')->get(),
        ]);
    }
}
