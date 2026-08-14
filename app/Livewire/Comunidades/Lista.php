<?php

namespace App\Livewire\Comunidades;

use App\Exceptions\EjercicioContableInvalidoException;
use App\Exceptions\EmpresaContableInvalidaException;
use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConBajaPorEstado;
use App\Livewire\Traits\ConFichaInicio;
use App\Models\AccesoDirecto;
use App\Models\Comunidad;
use App\Services\Actividades\EnlaceContableActividad;
use App\Services\Comunidades\EnlaceContableComunidad;
use App\Services\Contabilidad\AbrirEjercicioContableService;
use App\Services\Contabilidad\ResolverEmpresaContableService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    use ConBajaPorEstado;
    use ConFichaInicio;

    /** Modal que pide los nombres contables que faltan antes de enlazar. */
    public bool $abrirNombresContables = false;

    public ?int $comunidadAEnlazar = null;

    /** id de la cuenta bancaria => nombre contable que se está escribiendo. */
    public array $nombresContables = [];

    /** Lo que se pinta de cada cuenta pendiente: id, iban y alias. */
    public array $cuentasPendientes = [];

    protected function modeloBaja(): string
    {
        return Comunidad::class;
    }

    /**
     * Solo son fijables las comunidades en las que este usuario puede entrar: la
     * ficha del inicio es un atajo del menú lateral, no una puerta nueva.
     */
    protected function fichaInicioPara($id): ?array
    {
        $comunidad = auth()->user()->comunidadesAccesibles()->firstWhere('id', (int) $id);

        if (! $comunidad) {
            return null;
        }

        return [
            'tipo'   => AccesoDirecto::TIPO_COMUNIDAD,
            'nombre' => $comunidad->nombre,
            'url'    => route('comunidad.entrar', $comunidad, false),
            'icono'  => 'fa-solid fa-city',
        ];
    }

    public function mount()
    {
        $this->sort      = 'id';
        $this->direction = 'desc';
    }

    #[On('comunidad-guardada')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    // ConBajaPorEstado ya ejecuta la baja/reactivación; aquí solo avisamos de que
    // el menú lateral (no reactivo) necesita recargar la página para reflejarlo.
    #[On('ejecutarBaja')]
    public function avisarTrasBaja($id)
    {
        $this->dispatch('comunidad-guardada');
    }

    #[On('ejecutarReactivar')]
    public function avisarTrasReactivar($id)
    {
        $this->dispatch('comunidad-guardada');
    }

    /**
     * Enlaza la comunidad con la empresa contable de su CIF, creándola si es la primera
     * vez. Repetirlo no crea una segunda: el CIF manda (ver ResolverEmpresaContableService).
     *
     * Son DOS pasos, no uno: dar de alta la empresa no abre ningún ejercicio, así que
     * después se le abre el del año en curso (del 1 de enero al 31 de diciembre). Sin
     * ejercicio la empresa no admite ningún asiento y el enlace no serviría de nada.
     */
    public function enlazarContabilidad($id)
    {
        $comunidad = Comunidad::find($id);

        if (! $comunidad) {
            return;
        }

        // Sin ninguna cuenta bancaria, el enlace se completa igual pero no nace ninguna
        // subcuenta de bancos: el primer asiento que la necesite (apertura o un pago) se
        // la inventa por su cuenta, sin que nadie la haya podido nombrar antes.
        if ($comunidad->cuentasBancarias()->doesntExist()) {
            $this->dispatch('toast-error', ['title' => __('Dé de alta antes una cuenta bancaria de la comunidad.')]);

            return;
        }

        // Sin nombre contable, la cuenta del banco no llega a la contabilidad y se queda
        // callada (EnlaceContableComunidad::asignarCuentaBancaria devuelve null). Es el
        // dato del primer asiento, así que se pide ANTES de enlazar, no después.
        $sinNombre = $comunidad->cuentasBancarias()
            ->where(fn ($q) => $q->whereNull('nombre_contable')->orWhere('nombre_contable', ''))
            ->get();

        if ($sinNombre->isNotEmpty()) {
            $this->comunidadAEnlazar = $comunidad->id;
            $this->nombresContables  = $sinNombre->pluck('nombre_contable', 'id')
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

        $this->ejecutarEnlace($comunidad);
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

        $comunidad = Comunidad::find($this->comunidadAEnlazar);

        if (! $comunidad) {
            $this->cerrarNombresContables();

            return;
        }

        foreach ($this->nombresContables as $cuentaId => $nombre) {
            $comunidad->cuentasBancarias()
                ->whereKey($cuentaId)
                ->update(['nombre_contable' => trim($nombre)]);
        }

        $this->cerrarNombresContables();

        $this->ejecutarEnlace($comunidad->fresh());
    }

    public function cerrarNombresContables()
    {
        $this->abrirNombresContables = false;
        $this->comunidadAEnlazar     = null;
        $this->nombresContables      = [];
        $this->cuentasPendientes     = [];
        $this->resetValidation();
    }

    private function ejecutarEnlace(Comunidad $comunidad)
    {
        $anho = (int) now()->year;

        try {
            $empresa = DB::transaction(function () use ($comunidad, $anho) {
                $empresa = app(ResolverEmpresaContableService::class)
                    ->ejecutar((string) $comunidad->cif, (string) $comunidad->nombre);

                app(AbrirEjercicioContableService::class)
                    ->ejecutar($empresa->id, (string) $anho, "$anho-01-01", "$anho-12-31");

                $comunidad->update(['empresa_contable_id' => $empresa->id]);

                return $empresa;
            });
        } catch (EmpresaContableInvalidaException|EjercicioContableInvalidoException $e) {
            $this->dispatch('toast-error', ['title' => $e->getMessage()]);

            return;
        }

        // Ya hay empresa: cada cuenta de la comunidad estrena su subcuenta de bancos.
        // Es idempotente, la que ya la tenga se queda como está.
        foreach ($comunidad->cuentasBancarias as $cuenta) {
            $cuenta->setRelation('titular', $comunidad);
            app(EnlaceContableComunidad::class)->asignarCuentaBancaria($cuenta);
        }

        // Igual con las actividades que ya tuviera la comunidad: la nueva, si se da de
        // alta a partir de ahora, ya se enlaza sola (Actividad::booted()).
        foreach ($comunidad->actividades as $actividad) {
            $actividad->setRelation('comunidad', $comunidad);
            app(EnlaceContableActividad::class)->asignarProyecto($actividad);
        }

        $this->dispatch('toast-success', ['title' => $empresa->wasRecentlyCreated
            ? __('Empresa contable creada y enlazada')
            : __('Enlazada con la empresa contable de ese CIF'),
        ]);

        // La empresa nueva tiene que salir en el menú lateral para poder entrar en ella,
        // y ese menú se arma en el layout: sin repintar la página se queda como estaba.
        $this->dispatch('empresa-contable-guardada');
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = Comunidad::with('estado')
            ->when($search, function ($q) use ($search) {
                $q->whereHas('persona', fn ($p) => $p
                    ->where('razon_social', 'like', "%{$search}%")
                    ->orWhere('documento_identificativo', 'like', "%{$search}%"));
            })
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        return view('livewire.comunidades.lista', [
            'items'          => $items,
            'idsAccesibles'  => auth()->user()->comunidadesAccesibles()->pluck('id')->all(),
        ]);
    }
}
