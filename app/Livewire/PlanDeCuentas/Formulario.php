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
            'codigo'                  => [
                'required', 'digits:8',
                Rule::unique('cuenta_contables', 'codigo')
                    ->where(fn ($q) => $q->where('empresa_contable_id', $this->empresa_contable_id))
                    ->ignore($this->itemId),
            ],
            'nombre'                  => ['required', 'string', 'max:150'],
            'tipo_cuenta_contable_id' => ['required', 'exists:tipo_cuenta_contables,id'],
        ];
    }

    protected function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'max'      => 'Máxima longitud de :attribute = :max',
            'digits'   => 'El :attribute debe tener exactamente :digits dígitos numéricos',
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

    /** Con el prefijo tecleado en Código (1-7 dígitos), completa el siguiente código de 8 libre de ese grupo. */
    public function siguienteCodigo(): void
    {
        $prefijo = substr(preg_replace('/\D/', '', $this->codigo), 0, 8);

        if ($prefijo === '' || strlen($prefijo) >= 8) {
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

        // Sugiere el tipo de la cuenta de grupo (mismos 4 primeros dígitos + 0000), si existe.
        if (! $this->tipo_cuenta_contable_id) {
            $grupo = CuentaContable::where('empresa_contable_id', $this->empresa_contable_id)
                ->where('codigo', substr($prefijo, 0, 4).'0000')
                ->first();
            if ($grupo) {
                $this->tipo_cuenta_contable_id = $grupo->tipo_cuenta_contable_id;
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

        if ($this->itemId) {
            $cuenta = CuentaContable::findOrFail($this->itemId);
            $cuenta->update($data);
            $this->dispatch('toast-success', ['title' => __('Cuenta modificada')]);
        } else {
            // Cuelga automáticamente de la cuenta de grupo (4 primeros dígitos + 0000) si
            // existe, para que esa cuenta de grupo deje de ser "hoja" en cuanto tiene hijas.
            $padre = CuentaContable::where('empresa_contable_id', $this->empresa_contable_id)
                ->where('codigo', substr($data['codigo'], 0, 4).'0000')
                ->first();

            $cuenta = CuentaContable::create($data + [
                'empresa_contable_id' => $this->empresa_contable_id,
                'estado_id'           => CuentaContable::ESTADO_ACTIVO,
                'cuenta_padre_id'     => $padre && $padre->codigo !== $data['codigo'] ? $padre->id : null,
            ]);
            $this->dispatch('toast-success', ['title' => __('Cuenta creada')]);
        }

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
