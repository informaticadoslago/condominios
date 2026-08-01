<?php

namespace App\Livewire\AsientosContables;

use App\Models\AsientoContable;
use App\Models\CuentaContable;
use App\Models\EjercicioContable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.foco')]
class Formulario extends Component
{
    // Fijado al entrar por la ruta, nunca por el cliente.
    #[Locked]
    public int $ejercicio_contable_id;

    public ?string $fecha = null;
    public string $concepto = '';

    /** [['_key', '_cuenta_texto', 'cuenta_contable_id', 'debe', 'haber', 'concepto'], ...] */
    public array $apuntes = [];

    /** Resultados del buscador de cuentas, indexados por la '_key' de la línea que busca. */
    public array $resultadosCuentas = [];

    /** '_key' de la línea que pidió crear cuenta, mientras el modal de Cuentas Contables está abierto. */
    public ?string $creandoCuentaEn = null;

    public function mount(EjercicioContable $ejercicioContable): void
    {
        $this->ejercicio_contable_id = $ejercicioContable->id;

        $this->fecha = now()->between($ejercicioContable->fecha_inicio, $ejercicioContable->fecha_fin)
            ? now()->toDateString()
            : $ejercicioContable->fecha_inicio->toDateString();

        $this->apuntes = [$this->lineaVacia(), $this->lineaVacia()];
    }

    protected function ejercicio(): EjercicioContable
    {
        return EjercicioContable::findOrFail($this->ejercicio_contable_id);
    }

    protected function lineaVacia(): array
    {
        // Sin puntos: la clave se usa en rutas tipo "resultadosCuentas.<clave>" y un
        // punto en la propia clave rompería esa ruta.
        return ['_key' => Str::random(10), '_cuenta_texto' => '', 'cuenta_contable_id' => null, 'debe' => 0, 'haber' => 0, 'concepto' => ''];
    }

    /** Buscador del autocompletado de Cuenta: solo cuentas hoja activas, por código o nombre. */
    public function buscarCuentas(string $q, int $limit, ?string $clave = null): void
    {
        $q = trim($q);

        $this->resultadosCuentas[$clave] = $q === '' ? [] : CuentaContable::where('estado_id', CuentaContable::ESTADO_ACTIVO)
            ->whereDoesntHave('subcuentas')
            ->where(function ($query) use ($q) {
                $query->where('codigo', 'like', "%{$q}%")->orWhere('nombre', 'like', "%{$q}%");
            })
            ->orderBy('codigo')
            ->limit($limit)
            ->get()
            ->map(fn ($c) => ['valor' => $c->id, 'etiqueta' => "{$c->codigo} - {$c->nombre}"])
            ->all();
    }

    /** Abre el modal de Cuentas Contables para dar de alta una nueva; esta página no se mueve de sitio. */
    public function abrirNuevaCuenta(string $clave): void
    {
        $this->creandoCuentaEn = $clave;

        // Si ya había dígitos tecleados en el buscador de esa línea, se los pasa como
        // prefijo de código; si era texto (intento de nombre), no precarga nada.
        $texto       = collect($this->apuntes)->firstWhere('_key', $clave)['_cuenta_texto'] ?? '';
        $soloDigitos = preg_replace('/\D/', '', $texto);
        $prefijo     = ($soloDigitos !== '' && $soloDigitos === $texto) ? $soloDigitos : null;

        $this->dispatch('abrir-crear-cuenta-contable', codigoPrefijo: $prefijo);
    }

    /** El modal de Cuentas Contables guardó una cuenta: la deja puesta en la línea que la pidió. */
    #[On('cuenta-contable-guardada')]
    public function cuentaContableCreada($cuenta = null): void
    {
        if (! $cuenta || ! $this->creandoCuentaEn) {
            return;
        }

        foreach ($this->apuntes as $i => $apunte) {
            if ($apunte['_key'] === $this->creandoCuentaEn) {
                $this->apuntes[$i]['cuenta_contable_id'] = $cuenta['id'];
                $this->apuntes[$i]['_cuenta_texto']      = "{$cuenta['codigo']} - {$cuenta['nombre']}";
                break;
            }
        }

        $this->creandoCuentaEn = null;
    }

    protected function rules()
    {
        $ejercicio = $this->ejercicio();

        return [
            'ejercicio_contable_id'         => ['required', 'exists:ejercicio_contables,id'],
            'fecha'                         => [
                'required', 'date',
                'after_or_equal:'.$ejercicio->fecha_inicio->toDateString(),
                'before_or_equal:'.$ejercicio->fecha_fin->toDateString(),
            ],
            'concepto'                      => ['required', 'string', 'max:255'],
            'apuntes'                       => ['required', 'array', 'min:2'],
            'apuntes.*.cuenta_contable_id'  => ['required', 'exists:cuenta_contables,id'],
            'apuntes.*.debe'                => ['required', 'numeric', 'min:0'],
            'apuntes.*.haber'               => ['required', 'numeric', 'min:0'],
            'apuntes.*.concepto'            => ['nullable', 'string', 'max:255'],
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
            'apuntes.min'      => 'El asiento debe tener al menos 2 líneas',
            'after_or_equal'   => 'La fecha debe estar dentro del ejercicio contable',
            'before_or_equal'  => 'La fecha debe estar dentro del ejercicio contable',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'fecha'                        => __('fecha'),
            'concepto'                     => __('concepto'),
            'apuntes.*.cuenta_contable_id' => __('cuenta'),
            'apuntes.*.debe'               => __('debe'),
            'apuntes.*.haber'              => __('haber'),
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $debeTotal  = 0;
            $haberTotal = 0;

            foreach ($this->apuntes as $i => $apunte) {
                $debe  = (float) ($apunte['debe'] ?? 0);
                $haber = (float) ($apunte['haber'] ?? 0);

                if ($debe > 0 && $haber > 0) {
                    $validator->errors()->add("apuntes.$i.debe", __('Una línea no puede tener importe en Debe y en Haber a la vez'));
                }
                if ($debe == 0 && $haber == 0) {
                    $validator->errors()->add("apuntes.$i.debe", __('La línea debe tener un importe en Debe o en Haber'));
                }

                $debeTotal  += $debe;
                $haberTotal += $haber;
            }

            if (round($debeTotal, 2) !== round($haberTotal, 2)) {
                $validator->errors()->add('apuntes', __('El asiento no cuadra: Debe :debe frente a Haber :haber', [
                    'debe'  => number_format($debeTotal, 2),
                    'haber' => number_format($haberTotal, 2),
                ]));
            }
        });
    }

    public function agregarLinea(): void
    {
        $this->apuntes[] = $this->lineaVacia();
    }

    public function quitarLinea(int $index): void
    {
        if (count($this->apuntes) <= 2) {
            return;
        }

        unset($this->apuntes[$index]);
        $this->apuntes = array_values($this->apuntes);
    }

    public function guardar()
    {
        $data = $this->validate();

        DB::transaction(function () use ($data) {
            // Bloquea el ejercicio para serializar la asignación del número correlativo.
            EjercicioContable::whereKey($data['ejercicio_contable_id'])->lockForUpdate()->first();

            $numero = (int) AsientoContable::where('ejercicio_contable_id', $data['ejercicio_contable_id'])->max('numero') + 1;

            $asiento = AsientoContable::create([
                'ejercicio_contable_id' => $data['ejercicio_contable_id'],
                'numero'                => $numero,
                'fecha'                 => $data['fecha'],
                'concepto'              => $data['concepto'],
            ]);

            foreach ($data['apuntes'] as $apunte) {
                $asiento->apuntesContables()->create([
                    'cuenta_contable_id' => $apunte['cuenta_contable_id'],
                    'debe'               => $apunte['debe'] ?: 0,
                    'haber'              => $apunte['haber'] ?: 0,
                    'concepto'           => $apunte['concepto'] ?: null,
                ]);
            }
        });

        session()->flash('mensaje', __('Asiento creado'));

        return redirect()->route('asientos-contables.index');
    }

    public function render()
    {
        return view('livewire.asientos-contables.formulario', [
            'ejercicio' => $this->ejercicio(),
        ]);
    }
}
