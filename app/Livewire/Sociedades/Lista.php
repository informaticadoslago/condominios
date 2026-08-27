<?php

namespace App\Livewire\Sociedades;

use App\Exceptions\EjercicioContableInvalidoException;
use App\Exceptions\EmpresaContableInvalidaException;
use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConBajaPorEstado;
use App\Livewire\Traits\ConFichaInicio;
use App\Models\AccesoDirecto;
use App\Models\CuentaContablePlantilla;
use App\Models\Sociedad;
use App\Services\Contabilidad\AbrirEjercicioContableService;
use App\Services\Contabilidad\ResolverEmpresaContableService;
use App\Services\Sociedades\EnlaceContableSociedad;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    use ConBajaPorEstado;
    use ConFichaInicio;

    /** Modal que pide los nombres contables que faltan antes de enlazar. */
    public bool $abrirNombresContables = false;

    public ?int $sociedadAEnlazar = null;

    /** id de la cuenta bancaria => nombre contable que se está escribiendo. */
    public array $nombresContables = [];

    /** Lo que se pinta de cada cuenta pendiente: id, iban y alias. */
    public array $cuentasPendientes = [];

    protected function modeloBaja(): string
    {
        return Sociedad::class;
    }

    /**
     * Solo son fijables las sociedades en las que este usuario puede entrar: la
     * ficha del inicio es un atajo del menú lateral, no una puerta nueva.
     */
    protected function fichaInicioPara($id): ?array
    {
        $sociedad = auth()->user()->sociedadesAccesibles()->firstWhere('id', (int) $id);

        if (! $sociedad) {
            return null;
        }

        return [
            'tipo'   => AccesoDirecto::TIPO_SOCIEDAD,
            'nombre' => $sociedad->nombre,
            'url'    => route('sociedad.entrar', $sociedad, false),
            'icono'  => 'fa-solid fa-industry',
        ];
    }

    public function mount()
    {
        $this->sort      = 'id';
        $this->direction = 'desc';
    }

    #[On('sociedad-guardada')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    #[On('ejecutarBaja')]
    public function avisarTrasBaja($id)
    {
        $this->dispatch('sociedad-guardada');
    }

    #[On('ejecutarReactivar')]
    public function avisarTrasReactivar($id)
    {
        $this->dispatch('sociedad-guardada');
    }

    /**
     * Enlaza la sociedad con la empresa contable de su CIF, creándola si es la primera
     * vez. Repetirlo no crea una segunda: el CIF manda (ver ResolverEmpresaContableService).
     *
     * Son DOS pasos, no uno: dar de alta la empresa no abre ningún ejercicio, así que
     * después se le abre el del año en curso (del 1 de enero al 31 de diciembre). Sin
     * ejercicio la empresa no admite ningún asiento y el enlace no serviría de nada.
     */
    public function enlazarContabilidad($id)
    {
        $sociedad = Sociedad::find($id);

        if (! $sociedad) {
            return;
        }

        if ($sociedad->cuentasBancarias()->doesntExist()) {
            $this->dispatch('toast-error', ['title' => __('Dé de alta antes una cuenta bancaria de la sociedad.')]);

            return;
        }

        $sinNombre = $sociedad->cuentasBancarias()
            ->where(fn ($q) => $q->whereNull('nombre_contable')->orWhere('nombre_contable', ''))
            ->get();

        if ($sinNombre->isNotEmpty()) {
            $this->sociedadAEnlazar = $sociedad->id;
            $this->nombresContables = $sinNombre->pluck('nombre_contable', 'id')
                ->map(fn ($nombre) => (string) $nombre)->all();
            $this->cuentasPendientes = $sinNombre->map(fn ($cuenta) => [
                'id'    => $cuenta->id,
                'iban'  => $cuenta->iban,
                'alias' => $cuenta->alias,
            ])->all();

            $this->resetValidation();
            $this->abrirNombresContables = true;

            return;
        }

        $this->ejecutarEnlace($sociedad);
    }

    /**
     * Guarda los nombres que faltaban y sigue con el enlace. Se validan todos: a medias
     * no sirve, porque la cuenta sin nombre se quedaría fuera de la contabilidad igual.
     */
    public function guardarNombresYEnlazar()
    {
        $this->validate(
            ['nombresContables.*' => ['required', 'string', 'max:150']],
            ['nombresContables.*.required' => __('Escriba el nombre con el que se leerá en el mayor'),
                'nombresContables.*.max'   => __('Máxima longitud 150')]
        );

        $sociedad = Sociedad::find($this->sociedadAEnlazar);

        if (! $sociedad) {
            $this->cerrarNombresContables();

            return;
        }

        foreach ($this->nombresContables as $cuentaId => $nombre) {
            $sociedad->cuentasBancarias()
                ->whereKey($cuentaId)
                ->update(['nombre_contable' => trim($nombre)]);
        }

        $this->cerrarNombresContables();

        $this->ejecutarEnlace($sociedad->fresh());
    }

    public function cerrarNombresContables()
    {
        $this->abrirNombresContables = false;
        $this->sociedadAEnlazar      = null;
        $this->nombresContables      = [];
        $this->cuentasPendientes     = [];
        $this->resetValidation();
    }

    private function ejecutarEnlace(Sociedad $sociedad)
    {
        $anho = (int) now()->year;

        try {
            $empresa = DB::transaction(function () use ($sociedad, $anho) {
                $empresa = app(ResolverEmpresaContableService::class)
                    ->ejecutar((string) $sociedad->cif, (string) $sociedad->nombre, CuentaContablePlantilla::PLANTILLA_SOCIEDAD);

                app(AbrirEjercicioContableService::class)
                    ->ejecutar($empresa->id, (string) $anho, "$anho-01-01", "$anho-12-31");

                $sociedad->update(['empresa_contable_id' => $empresa->id]);

                return $empresa;
            });
        } catch (EmpresaContableInvalidaException|EjercicioContableInvalidoException $e) {
            $this->dispatch('toast-error', ['title' => $e->getMessage()]);

            return;
        }

        // Ya hay empresa: cada cuenta de la sociedad estrena su subcuenta de bancos.
        // Es idempotente, la que ya la tenga se queda como está.
        foreach ($sociedad->cuentasBancarias as $cuenta) {
            $cuenta->setRelation('titular', $sociedad);
            app(EnlaceContableSociedad::class)->asignarCuentaBancaria($cuenta);
        }

        $this->dispatch('toast-success', ['title' => $empresa->wasRecentlyCreated
            ? __('Empresa contable creada y enlazada')
            : __('Enlazada con la empresa contable de ese CIF'),
        ]);

        $this->dispatch('empresa-contable-guardada');
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = Sociedad::with('estado')
            ->when($search, function ($q) use ($search) {
                $q->whereHas('persona', fn ($p) => $p
                    ->where('razon_social', 'like', "%{$search}%")
                    ->orWhere('documento_identificativo', 'like', "%{$search}%"));
            })
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        return view('livewire.sociedades.lista', [
            'items'         => $items,
            'idsAccesibles' => auth()->user()->sociedadesAccesibles()->pluck('id')->all(),
        ]);
    }
}
