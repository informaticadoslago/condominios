<?php

namespace App\Livewire\ComisionesBancarias;

use App\Exceptions\AsientoInvalidoException;
use App\Exceptions\CuentaContableDesconocidaException;
use App\Exceptions\EjercicioCerradoException;
use App\Exceptions\EjercicioContableDesconocidoException;
use App\Models\Comunidad;
use App\Models\Remesa;
use App\Models\TipoComisionBancaria;
use App\Services\ComisionesBancarias\RegistrarComisionBancariaService;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Alta a mano de una comisión bancaria (liquidación de remesa): la comisión y su IVA
 * llegan como movimientos separados del banco, así que se teclean como líneas sueltas,
 * no como un único importe.
 */
#[Layout('layouts.foco')]
class Formulario extends Component
{
    public ?int $cuenta_bancaria_id = null;
    public ?int $tipo_comision_bancaria_id = null;
    public ?int $remesa_id = null;
    public ?string $fecha = null;
    public string $concepto = '';
    public ?string $referencia = null;

    /** [['_key', 'concepto', 'importe'], ...] */
    public array $lineas = [];

    public function mount(): void
    {
        $this->fecha  = now()->toDateString();
        $this->lineas = [$this->lineaVacia()];

        $cuentas = $this->cuentasBancarias();
        if ($cuentas->count() === 1) {
            $this->cuenta_bancaria_id = $cuentas->first()->id;
        }

        $this->tipo_comision_bancaria_id = $this->tiposComisionBancaria()
            ->firstWhere('codigo', TipoComisionBancaria::REMESA)?->id;
    }

    protected function cuentasBancarias()
    {
        return Comunidad::find(session('comunidad_actual_id'))?->cuentasBancarias ?? collect();
    }

    protected function empresaContableId(): ?int
    {
        return Comunidad::find(session('comunidad_actual_id'))?->empresa_contable_id;
    }

    protected function tiposComisionBancaria()
    {
        $empresaId = $this->empresaContableId();

        return $empresaId
            ? TipoComisionBancaria::where('empresa_contable_id', $empresaId)->get()
            : collect();
    }

    /** El tipo elegido es de remesa; solo entonces tiene sentido el selector de remesa. */
    #[Computed]
    public function esRemesa(): bool
    {
        return $this->tiposComisionBancaria()
            ->firstWhere('id', $this->tipo_comision_bancaria_id)
            ?->codigo === TipoComisionBancaria::REMESA;
    }

    /** Al cambiar de tipo, una remesa marcada de antes no pertenece al otro tipo. */
    public function updatedTipoComisionBancariaId(): void
    {
        if (! $this->esRemesa) {
            $this->remesa_id = null;
        }
    }

    protected function lineaVacia(): array
    {
        return ['_key' => Str::random(10), 'concepto' => '', 'importe' => ''];
    }

    #[Computed]
    public function total(): float
    {
        return round(array_sum(array_map(fn ($l) => (float) ($l['importe'] ?: 0), $this->lineas)), 2);
    }

    public function agregarLinea(): void
    {
        $this->lineas[] = $this->lineaVacia();
    }

    public function quitarLinea(int $index): void
    {
        if (count($this->lineas) <= 1) {
            return;
        }

        unset($this->lineas[$index]);
        $this->lineas = array_values($this->lineas);
    }

    protected function rules(): array
    {
        return [
            'cuenta_bancaria_id'        => ['required', 'exists:cuentas_bancarias,id'],
            'tipo_comision_bancaria_id' => ['required', 'exists:tipo_comisiones_bancarias,id'],
            'remesa_id'           => ['nullable', 'exists:remesas,id'],
            'fecha'               => ['required', 'date'],
            'concepto'            => ['required', 'string', 'max:255'],
            'referencia'          => ['nullable', 'string', 'max:60'],
            'lineas'              => ['required', 'array', 'min:1'],
            'lineas.*.concepto'   => ['required', 'string', 'max:120'],
            'lineas.*.importe'    => ['required', 'numeric', 'min:0.01'],
        ];
    }

    protected function messages()
    {
        return [
            'required'         => 'Debe rellenar :attribute',
            'max'              => 'Máxima longitud de :attribute = :max',
            'exists'           => 'El valor de :attribute no es válido',
            'numeric'          => ':attribute debe ser un número',
            'min'              => ':attribute debe ser mayor o igual a :min',
            'lineas.min'       => 'Hace falta al menos una línea',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'cuenta_bancaria_id'        => __('cuenta bancaria'),
            'tipo_comision_bancaria_id' => __('tipo'),
            'fecha'              => __('fecha'),
            'concepto'           => __('concepto'),
            'referencia'         => __('referencia'),
            'lineas.*.concepto'  => __('concepto'),
            'lineas.*.importe'   => __('importe'),
        ];
    }

    public function guardar(RegistrarComisionBancariaService $servicio)
    {
        $data = $this->validate();

        try {
            $servicio->registrar(
                cuentaBancariaId: $data['cuenta_bancaria_id'],
                tipoComisionBancariaId: $data['tipo_comision_bancaria_id'],
                remesaId: $data['remesa_id'] ?: null,
                fecha: $data['fecha'],
                concepto: $data['concepto'],
                referencia: $data['referencia'] ?: null,
                lineas: $data['lineas'],
            );
        } catch (AsientoInvalidoException|EjercicioCerradoException|EjercicioContableDesconocidoException|CuentaContableDesconocidaException $e) {
            // La comisión quedó registrada; lo que falló es su asiento. Se dice tal
            // cual, que no es lo mismo que no haberla registrado.
            $this->dispatch('toast-error', ['title' => __('Comisión registrada, pero sin contabilizar: ').$e->getMessage()]);

            return redirect()->route('comisiones-bancarias.index');
        }

        session()->flash('mensaje', __('Comisión bancaria registrada'));

        return redirect()->route('comisiones-bancarias.index');
    }

    public function render()
    {
        return view('livewire.comisiones-bancarias.formulario', [
            'cuentasBancarias'      => $this->cuentasBancarias(),
            'tiposComisionBancaria' => $this->tiposComisionBancaria(),
            'remesas'          => Remesa::where('comunidad_id', session('comunidad_actual_id'))
                ->orderByDesc('fecha_cargo')
                ->limit(50)
                ->get(),
        ]);
    }
}
