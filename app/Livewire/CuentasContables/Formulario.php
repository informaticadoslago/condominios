<?php

namespace App\Livewire\CuentasContables;

use App\Models\CuentaContablePlantilla;
use App\Models\TipoCuentaContable;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    public bool $abrir = false;
    public ?int $itemId = null;

    public string $codigo = '';
    public string $nombre = '';
    public ?int $tipo_cuenta_contable_id = null;

    /** '' en el select = común (se guarda como null); si no, PLANTILLA_COMUNIDAD/SOCIEDAD. */
    public string $plantilla = '';

    private function plantillaNormalizada(): ?string
    {
        return $this->plantilla !== '' ? $this->plantilla : null;
    }

    protected function rules()
    {
        return [
            // De 1 a 3 cifras es un nivel del PGC (grupo, subgrupo o cuenta); 8, una cuenta.
            'codigo'                  => [
                'required', 'regex:/^(\d{1,3}|\d{8})$/',
                // Único dentro de la misma plantilla: el índice único compuesto de la BD no
                // lo garantiza solo (NULL no choca con NULL).
                Rule::unique('cuenta_contable_plantillas', 'codigo')
                    ->where('plantilla', $this->plantillaNormalizada())
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
            'unique'   => 'Ya existe una cuenta con ese código en esa plantilla',
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
        $this->reset(['itemId', 'codigo', 'nombre', 'tipo_cuenta_contable_id', 'plantilla']);
        $this->resetValidation();
        $this->codigo = $codigoPrefijo ? substr(preg_replace('/\D/', '', $codigoPrefijo), 0, 8) : '';
        $this->abrir = true;
    }

    /** Nivel del PGC (hasta 3 cifras): no lleva tipo ni se autonumera. */
    public function esAgrupacion(): bool
    {
        return strlen(preg_replace('/\D/', '', (string) $this->codigo)) <= CuentaContablePlantilla::CIFRAS_AGRUPACION;
    }

    /**
     * Con el prefijo tecleado en Código (4-7 dígitos), completa el siguiente código de 8
     * libre de ese grupo, dentro de la plantilla elegida. Con 3 cifras o menos no toca
     * nada: eso no es un prefijo a medias, es el código de un grupo, un subgrupo o una
     * cuenta del PGC.
     */
    public function siguienteCodigo(): void
    {
        $prefijo = substr(preg_replace('/\D/', '', $this->codigo), 0, 8);

        if (strlen($prefijo) <= CuentaContablePlantilla::CIFRAS_AGRUPACION || strlen($prefijo) >= 8) {
            return;
        }

        $ultimo = CuentaContablePlantilla::where('plantilla', $this->plantillaNormalizada())
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
            $padre = CuentaContablePlantilla::padreDe($siguiente, $this->plantillaNormalizada());

            if ($padre) {
                $this->tipo_cuenta_contable_id = $padre->tipo_cuenta_contable_id;
            }
        }
    }

    #[On('cuenta-contable-editar')]
    public function editar($id)
    {
        $item = CuentaContablePlantilla::find($id);
        if (! $item || $item->estado_id != CuentaContablePlantilla::ESTADO_ACTIVO) {
            return;
        }
        $this->itemId                  = $item->id;
        $this->codigo                  = $item->codigo;
        $this->nombre                  = $item->nombre;
        $this->tipo_cuenta_contable_id = $item->tipo_cuenta_contable_id;
        $this->plantilla               = $item->plantilla ?? '';
        $this->resetValidation();
        $this->abrir = true;
    }

    public function guardar()
    {
        $data = $this->validate();
        $data['plantilla'] = $this->plantillaNormalizada();

        // Cuelga sola del ancestro más cercano que exista. Se recalcula también al editar:
        // si el código cambia, cambia de sitio en el árbol.
        $data['cuenta_padre_id'] = CuentaContablePlantilla::padreDe($data['codigo'], $this->plantillaNormalizada())?->id;

        if ($this->itemId) {
            $cuenta = CuentaContablePlantilla::findOrFail($this->itemId);
            $cuenta->update($data);
            $this->dispatch('toast-success', ['title' => __('Cuenta modificada')]);
        } else {
            $cuenta = CuentaContablePlantilla::create($data + [
                'estado_id' => CuentaContablePlantilla::ESTADO_ACTIVO,
            ]);
            $this->dispatch('toast-success', ['title' => __('Cuenta creada')]);
        }

        // Una cuenta intermedia recién creada se lleva a los nietos que colgaban del abuelo.
        CuentaContablePlantilla::recolgarPlan($this->plantillaNormalizada());

        $this->dispatch('cuenta-contable-guardada', cuenta: ['id' => $cuenta->id, 'codigo' => $cuenta->codigo, 'nombre' => $cuenta->nombre]);
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.cuentas-contables.formulario', [
            'tipos' => TipoCuentaContable::orderBy('id')->get(),
        ]);
    }
}
