<?php

namespace App\Livewire;

use App\Livewire\Traits\ConAccesoDirecto;
use App\Models\PreferenciaLista;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListaComponent extends Component
{
    use ConAccesoDirecto;
    use WithPagination;

    // #[Url]
    public $search;

    #[Url]
    public $sort = 'id';

    protected $sort_column;

    #[Url(except: 'desc')]
    public $direction = 'desc';

    #[Url(except: '10')]
    public $lineasXPagina = '10';

    public $readyToLoad = false;

    /** Valor actual de cada filtro, indexado por su clave. */
    public array $filtros = [];

    /** Claves de las columnas que se están mostrando (ver columnasDisponibles). */
    public array $columnas = [];

    /** Configuración de fábrica de la lista; la usa el botón de borrar filtro. */
    public array $porDefecto = [];

    protected $listeners = ['renderiza' => 'render'];

    /**
     * Filtros de esta lista. Cada Lista concreta los declara; los comunes a todas
     * los aportan traits (ConFiltroEstado…). Cada filtro es un array con:
     *   clave     nombre en $filtros (y en las preferencias guardadas)
     *   etiqueta  texto que se pinta delante del control
     *   tipo      'select' (se aplica solo) o 'texto' (se aplica con el botón)
     *   opciones  [valor => etiqueta], solo en los select
     *   neutro    valor que NO filtra nada (0 = Todos en los select, '' en texto)
     *   aplicar   fn($query, $valor) que añade la condición
     */
    public function definicionesFiltro(): array
    {
        return [];
    }

    /**
     * Columnas que la lista deja ocultar, [clave => etiqueta]. Son las columnas de
     * ESTA pantalla, no todos los campos del modelo. La lista que no declare
     * ninguna no enseña el selector y pinta siempre todo.
     */
    public function columnasDisponibles(): array
    {
        return [];
    }

    /** Columnas visibles de fábrica: todas. Una lista puede arrancar con menos. */
    protected function columnasPorDefecto(): array
    {
        return array_keys($this->columnasDisponibles());
    }

    /**
     * Los valores que puede tomar $sort en esta lista, o null si no se restringe (la
     * mayoría: valores fijos que ya vienen bien de su propio blade). Declararla protege
     * contra una preferencia guardada de una versión anterior del blade (una columna o
     * alias que ya no existe) y contra un ?sort=... manipulado en la URL: en vez de
     * reventar la consulta con "Unknown column", se descarta y vuelve al de fábrica.
     */
    protected function columnasOrdenables(): ?array
    {
        return null;
    }

    /** $valor vale si no hay lista blanca declarada, o si está en ella. */
    protected function sortValido($valor): bool
    {
        $permitidas = $this->columnasOrdenables();

        return $permitidas === null || in_array($valor, $permitidas, true);
    }

    /** En la vista: @if ($this->verColumna('curso')). */
    public function verColumna(string $clave): bool
    {
        return in_array($clave, $this->columnas, true);
    }

    /**
     * Opciones de un select de filtro. Salen de catálogos que apenas cambian, así
     * que se cachean en vez de leerlas en cada render (un día; si se toca el
     * catálogo, el filtro tarda como mucho eso en enterarse).
     */
    protected function opcionesCacheadas(string $clave, \Closure $opciones): array
    {
        return Cache::remember('filtro-opciones-'.$clave, now()->addDay(), $opciones);
    }

    /** Añade al query las condiciones de los filtros que no estén en su valor neutro. */
    protected function aplicarFiltros($query)
    {
        foreach ($this->definicionesFiltro() as $filtro) {
            $valor = $this->filtros[$filtro['clave']] ?? null;

            if (! $this->filtroNeutro($filtro, $valor)) {
                ($filtro['aplicar'])($query, $valor);
            }
        }

        return $query;
    }

    protected function filtroNeutro(array $filtro, $valor): bool
    {
        if ($valor === null || $valor === '') {
            return true;
        }

        return (string) $valor === (string) ($filtro['neutro'] ?? '');
    }

    protected function filtrosPorDefecto(): array
    {
        $valores = [];

        foreach ($this->definicionesFiltro() as $filtro) {
            $valores[$filtro['clave']] = $filtro['neutro'] ?? '';
        }

        return $valores;
    }

    /**
     * Tras el mount y ANTES del render (rendering() sería tarde: corre con el query
     * ya construido): fija la configuración de fábrica —ya con los valores que cada
     * Lista pone en su mount— y encima aplica las preferencias del usuario.
     */
    public function booted(): void
    {
        if ($this->porDefecto) {
            return;
        }

        $this->porDefecto = [
            'filtros' => $this->filtrosPorDefecto(),
            'columnas' => $this->columnasPorDefecto(),
            'search' => $this->search,
            'sort' => $this->sort,
            'direction' => $this->direction,
            'lineasXPagina' => $this->lineasXPagina,
        ];
        $this->filtros = $this->porDefecto['filtros'];
        $this->columnas = $this->porDefecto['columnas'];

        $this->cargarPreferencias();
    }

    /** Nombre del componente Livewire; identifica la lista en las preferencias. */
    protected function claveLista(): string
    {
        return $this->getName();
    }

    protected function cargarPreferencias(): void
    {
        $guardado = PreferenciaLista::recordar($this->claveLista());

        if (! $guardado) {
            return;
        }

        // Solo se recuperan los filtros que la lista sigue teniendo declarados.
        $this->filtros = array_merge(
            $this->filtros,
            array_intersect_key($guardado['filtros'] ?? [], $this->filtros),
        );

        // Igual con las columnas: si la lista ha cambiado, las que ya no existen se
        // descartan (y las nuevas no aparecen ocultas, sino con su valor de fábrica).
        if (isset($guardado['columnas'])) {
            $this->columnas = array_values(
                array_intersect($guardado['columnas'], array_keys($this->columnasDisponibles())),
            );
        }

        $this->search = $guardado['search'] ?? $this->search;
        $this->sort = $guardado['sort'] ?? $this->sort;
        $this->direction = $guardado['direction'] ?? $this->direction;
        $this->lineasXPagina = $guardado['lineasXPagina'] ?? $this->lineasXPagina;

        // $this->sort puede venir de aquí o de la URL (#[Url], ya aplicada al llegar a este
        // punto): en los dos casos puede ser un valor de otra época del blade, o manipulado
        // a mano. Si esta lista declara columnasOrdenables(), se descarta y vuelve al de
        // fábrica en vez de dejar que reviente la consulta; y se guarda ya corregido, para
        // no repetir el mismo susto la próxima vez que se abra esta lista.
        if (! $this->sortValido($this->sort)) {
            $this->sort = $this->porDefecto['sort'];
            $this->guardarPreferencias();
        }
    }

    protected function guardarPreferencias(): void
    {
        PreferenciaLista::guardar($this->claveLista(), [
            'filtros' => $this->filtros,
            'columnas' => $this->columnas,
            'search' => $this->search,
            'sort' => $this->sort,
            'direction' => $this->direction,
            'lineasXPagina' => $this->lineasXPagina,
        ]);
    }

    /** Los select se aplican al vuelo; los de texto esperan al botón Aplicar. */
    public function updatedFiltros(): void
    {
        $this->resetPage();
        $this->guardarPreferencias();
    }

    /** Marcar o desmarcar una columna se recuerda, como los filtros. */
    public function updatedColumnas(): void
    {
        $this->guardarPreferencias();
    }

    /** Atajo del selector de columnas: enseñarlas todas. */
    public function marcarTodasColumnas(): void
    {
        $this->columnas = array_keys($this->columnasDisponibles());

        $this->guardarPreferencias();
    }

    public function aplicarFiltro(): void
    {
        $this->resetPage();
        $this->guardarPreferencias();
    }

    /** Deja la lista como de fábrica (filtros, columnas, búsqueda y orden) y olvida lo guardado. */
    public function borrarFiltro(): void
    {
        $this->filtros = $this->porDefecto['filtros'];
        $this->columnas = $this->porDefecto['columnas'];
        $this->search = $this->porDefecto['search'];
        $this->sort = $this->porDefecto['sort'];
        $this->direction = $this->porDefecto['direction'];
        $this->lineasXPagina = $this->porDefecto['lineasXPagina'];

        $this->resetPage();
        PreferenciaLista::olvidar($this->claveLista());
    }

    public function updatingSearch($value)
    {
        $this->resetPage();
    }

    /** La búsqueda también filtra: se guarda como una preferencia más. */
    public function updatedSearch($value)
    {
        $this->guardarPreferencias();
    }

    /** La X del buscador: vacía solo la búsqueda (los filtros siguen puestos). */
    public function limpiarBusqueda(): void
    {
        $this->search = null;

        $this->resetPage();
        $this->guardarPreferencias();
    }

    public function ordenar($nombre_columna, $columna = null)
    {
        // Defensa en profundidad: si el blade pide ordenar por algo que columnasOrdenables()
        // no reconoce (un blade desincronizado del backend, como pasó aquí), se ignora el
        // clic en vez de dejar que la próxima consulta reviente.
        if (! $this->sortValido($nombre_columna)) {
            return;
        }

        if (! $columna) {
            $columna = $nombre_columna;
        }

        $this->sort_column = $columna;
        if ($this->sort == $nombre_columna) {
            if ($this->direction == 'desc') {
                $this->direction = 'asc';
            } else {
                $this->direction = 'desc';
            }
        } else {
            $this->sort = $nombre_columna;
        }

        $this->guardarPreferencias();
    }

    /**
     * Ordena la consulta. Si se ordena por la columna de nombre completo, respeta
     * LIST_NOMBRECOMPLETO: con 2 (Apellidos, Nombre) ordena por apellidos; con 1, por el
     * nombre de pila. Así el orden casa con lo que se muestra. El resto de columnas se
     * ordenan tal cual. $tablaPersonas es el nombre/alias de la tabla personas en la consulta.
     */
    protected function aplicarOrden($query, string $tablaPersonas = 'personas')
    {
        if ($this->sort !== 'nombre') {
            return $query->orderBy($this->sort, $this->direction);
        }

        $columnas = (int) config('settings.list_nombre_completo', 1) === 2
            ? ['apellido1', 'apellido2', 'nombre']
            : ['nombre', 'apellido1', 'apellido2'];

        foreach ($columnas as $columna) {
            $query->orderBy("{$tablaPersonas}.{$columna}", $this->direction);
        }

        return $query;
    }

    public function loadTipos()
    {
        $this->readyToLoad = true;
    }

    public function updatingLineasXPagina($value)
    {
        $this->resetPage();
    }

    public function updatedLineasXPagina($value)
    {
        $this->guardarPreferencias();
    }
}
