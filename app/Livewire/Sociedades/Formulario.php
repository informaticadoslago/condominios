<?php

namespace App\Livewire\Sociedades;

use App\Livewire\Forms\SociedadForm;
use App\Models\EntidadBancaria;
use App\Models\Sociedad;
use App\Rules\IsIBANRule;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    public bool $abrir = false;

    public SociedadForm $formulario;

    /** Resultados del buscador de entidad bancaria de la cuenta que se está editando. */
    public array $resultadosEntidadesBancarias = [];

    /** Submodal de alta/edición de una cuenta bancaria, por encima del de la sociedad. */
    public bool $abrirCuenta = false;

    /** Índice en formulario.cuentas que se está editando, o null si es una nueva. */
    public ?int $cuentaEditandoIndice = null;

    /** Copia de trabajo de la cuenta del submodal; no toca formulario.cuentas hasta Guardar. */
    public array $cuentaTemp = [];

    public function mount()
    {
        $this->formulario->resetForm();
    }

    /** Buscador del autocompletado de entidad bancaria: por código o nombre. */
    public function buscarEntidadesBancarias(string $q, int $limit = 8): void
    {
        $q = trim($q);

        $this->resultadosEntidadesBancarias = $q === '' ? [] : EntidadBancaria::activo()
            ->where(function ($query) use ($q) {
                $query->where('codigo', 'like', "%{$q}%")->orWhere('descripcion', 'like', "%{$q}%");
            })
            ->orderBy('descripcion')
            ->limit($limit)
            ->get()
            ->map(fn ($e) => ['valor' => $e->id, 'etiqueta' => "{$e->codigo} - {$e->descripcion}"])
            ->all();
    }

    #[On('abrir-crear-sociedad')]
    public function crear()
    {
        $this->formulario->sociedad = new Sociedad();
        $this->formulario->resetForm();
        $this->abrir = true;
    }

    #[On('sociedad-editar')]
    public function editar($id)
    {
        $sociedad = Sociedad::with('persona', 'cuentasBancarias.entidadBancaria')->find($id);
        if (! $sociedad) {
            return;
        }

        $this->formulario->sociedad = $sociedad;
        $this->formulario->setSociedad();
        $this->abrir = true;
    }

    private function cuentaVacia(): array
    {
        return [
            'id'                     => null,
            'iban'                   => null,
            'entidad_bancaria_id'    => null,
            'entidad_bancaria_texto' => null,
            'alias'                  => null,
            'nombre_contable'        => null,
            'cuenta_contable'        => null,
        ];
    }

    public function abrirNuevaCuenta(): void
    {
        $this->cuentaEditandoIndice        = null;
        $this->cuentaTemp                  = $this->cuentaVacia();
        $this->resultadosEntidadesBancarias = [];
        $this->resetValidation();
        $this->abrirCuenta = true;
    }

    public function editarCuenta(int $indice): void
    {
        $fila = $this->formulario->cuentas[$indice] ?? null;
        if (! $fila) {
            return;
        }

        $this->cuentaEditandoIndice        = $indice;
        $this->cuentaTemp                  = $fila + $this->cuentaVacia();
        $this->resultadosEntidadesBancarias = [];
        $this->resetValidation();
        $this->abrirCuenta = true;
    }

    public function guardarCuenta(): void
    {
        if ($this->cuentaTemp['iban']) {
            $this->cuentaTemp['iban'] = $this->normalizarIban($this->cuentaTemp['iban']);
        }

        $this->validate(
            [
                'cuentaTemp.iban'                => [
                    'nullable', 'string', new IsIBANRule(),
                    function ($attribute, $value, $fail) {
                        if ($value && $this->ibanRepetido($value)) {
                            $fail(__('Esta sociedad ya tiene una cuenta con ese IBAN.'));
                        }
                    },
                ],
                'cuentaTemp.entidad_bancaria_id'  => ['nullable', 'exists:entidades_bancarias,id', 'required_with:cuentaTemp.iban'],
                'cuentaTemp.alias'                => ['nullable', 'string', 'max:100'],
                'cuentaTemp.nombre_contable'      => ['nullable', 'string', 'max:150'],
            ],
            [
                'cuentaTemp.entidad_bancaria_id.required_with' => __('Debe rellenar entidad bancaria'),
            ]
        );

        if ($this->cuentaEditandoIndice !== null) {
            $this->formulario->cuentas[$this->cuentaEditandoIndice] = $this->cuentaTemp;
        } else {
            $this->formulario->cuentas[] = $this->cuentaTemp;
        }

        $this->cerrarCuenta();
    }

    /** Sin espacios ni guiones, y el código de país en mayúsculas (como todo el IBAN). */
    private function normalizarIban(string $iban): string
    {
        return strtoupper(preg_replace('/[\s-]+/', '', $iban) ?? '');
    }

    /** El mismo IBAN en otra fila de esta sociedad (ella misma no cuenta si se está editando). */
    private function ibanRepetido(string $iban): bool
    {
        $normalizado = $this->normalizarIban($iban);

        foreach ($this->formulario->cuentas as $indice => $fila) {
            if ($indice === $this->cuentaEditandoIndice || ! $fila['iban']) {
                continue;
            }

            if ($this->normalizarIban($fila['iban']) === $normalizado) {
                return true;
            }
        }

        return false;
    }

    public function cerrarCuenta(): void
    {
        $this->abrirCuenta           = false;
        $this->cuentaEditandoIndice  = null;
        $this->cuentaTemp            = [];
    }

    public function quitarCuenta(int $indice): void
    {
        $fila = $this->formulario->cuentas[$indice] ?? null;
        if (! $fila) {
            return;
        }

        if (empty($fila['id'])) {
            unset($this->formulario->cuentas[$indice]);
            $this->formulario->cuentas = array_values($this->formulario->cuentas);

            return;
        }

        $this->dispatch('swalConfirm', [
            'title'              => __('¿Desactivar esta cuenta bancaria?'),
            'text'               => __('No se borra: se marca como cancelada al guardar.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, desactivar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'confirmarQuitarCuenta',
            'id'                 => $indice,
        ]);
    }

    #[On('confirmarQuitarCuenta')]
    public function confirmarQuitarCuenta($indice): void
    {
        $fila = $this->formulario->cuentas[$indice] ?? null;
        if (! $fila) {
            return;
        }

        $this->formulario->cuentas_canceladas[] = $fila['id'];
        unset($this->formulario->cuentas[$indice]);
        $this->formulario->cuentas = array_values($this->formulario->cuentas);
    }

    public function guardar()
    {
        $validated = $this->formulario->validate();

        if ($this->formulario->sociedad?->exists) {
            $this->formulario->update($validated);
            $this->dispatch('toast-success', ['title' => __('Sociedad modificada')]);
        } else {
            $this->formulario->store($validated);
            $this->dispatch('toast-success', ['title' => __('Sociedad creada')]);
        }

        $this->dispatch('sociedad-guardada');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.sociedades.formulario');
    }
}
