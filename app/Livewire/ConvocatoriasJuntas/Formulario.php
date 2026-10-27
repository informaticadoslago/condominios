<?php

namespace App\Livewire\ConvocatoriasJuntas;

use App\Models\ConvocatoriaJunta;
use App\Models\Documento;
use App\Models\LineaOrdenDelDia;
use App\Models\TipoDocumento;
use App\Models\TipoJunta;
use App\Services\ConvocatoriasJuntas\LeerOrdenDelDia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Formulario extends Component
{
    use WithFileUploads;

    public bool $abrir = false;
    public ?int $itemId = null;

    public int $tipo_junta_id = TipoJunta::ORDINARIA;
    public string $fecha = '';
    public string $hora_primera_convocatoria = '';
    public string $hora_segunda_convocatoria = '';

    /**
     * Puntos del orden del día, en el orden en que se ven. Cada uno es ['id' => ?int,
     * 'clave' => string, 'texto' => string]: id nulo = punto nuevo, aún sin guardar;
     * la clave no cambia al arrastrar y es la que usan wire:key y el arrastre (el
     * índice sí cambia).
     */
    public array $lineas = [];

    /**
     * Documentos ya guardados de la convocatoria (los que van al correo, si se marcan al
     * enviarla): ['id' => int, 'nombre' => string, 'tamano' => string]. Quitar uno solo
     * lo saca de aquí: se borra al guardar, como los puntos.
     */
    public array $documentos = [];

    /** Ficheros subidos en este formulario, aún sin guardar (se van sumando en cada selección). */
    public array $documentosNuevos = [];

    /** Lo que trae cada selección del input de ficheros; updatedFicheros lo pasa a $documentosNuevos. */
    public array $ficheros = [];

    /** Modal secundario para pegar el orden del día entero como texto. */
    public bool $importarAbierto = false;
    public string $importarTexto = '';

    protected function rules()
    {
        return [
            'tipo_junta_id'             => ['required', 'exists:tipo_juntas,id'],
            'fecha'                     => ['required', 'date'],
            'hora_primera_convocatoria' => ['required', 'date_format:H:i'],
            // Mismo día que la primera: una segunda que pase de medianoche no cabe.
            'hora_segunda_convocatoria' => ['required', 'date_format:H:i', 'after:hora_primera_convocatoria'],
            // Sin required: un punto que se deja en blanco se descarta al guardar.
            'lineas'                    => ['array'],
            'lineas.*.texto'            => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function messages()
    {
        return [
            'required'                        => 'Debe rellenar :attribute',
            'date_format'                     => 'Hora no válida en :attribute',
            'max'                             => 'Máxima longitud de :attribute = :max',
            'hora_segunda_convocatoria.after' => 'La segunda convocatoria tiene que ser posterior a la primera, el mismo día',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'tipo_junta_id'             => __('tipo'),
            'fecha'                     => __('fecha'),
            'hora_primera_convocatoria' => __('primera convocatoria'),
            'hora_segunda_convocatoria' => __('segunda convocatoria'),
            'lineas.*.texto'            => __('punto del orden del día'),
        ];
    }

    #[On('abrir-crear-convocatoria-junta')]
    public function crear()
    {
        $this->reset(['itemId', 'tipo_junta_id', 'fecha', 'hora_primera_convocatoria', 'hora_segunda_convocatoria', 'lineas', 'documentos', 'documentosNuevos', 'ficheros']);
        $this->resetValidation();
        $this->abrir = true;
    }

    #[On('convocatoria-junta-editar')]
    public function editar($id)
    {
        $item = ConvocatoriaJunta::where('comunidad_id', session('comunidad_actual_id'))->find($id);
        if (! $item) {
            return;
        }
        $this->itemId                    = $item->id;
        $this->tipo_junta_id             = $item->tipo_junta_id;
        $this->fecha                     = $item->fecha->format('Y-m-d');
        $this->hora_primera_convocatoria = substr($item->hora_primera_convocatoria, 0, 5);
        $this->hora_segunda_convocatoria = substr($item->hora_segunda_convocatoria, 0, 5);
        $this->lineas = $item->lineasOrdenDelDia->map(fn (LineaOrdenDelDia $linea) => [
            'id'    => $linea->id,
            'clave' => 'l'.$linea->id,
            'texto' => $linea->texto,
        ])->all();
        $this->documentos = $item->documentos()->orderBy('id')->get()->map(fn (Documento $documento) => [
            'id'     => $documento->id,
            'nombre' => $documento->nombre_mostrado,
            'tamano' => $documento->tamano,
        ])->all();
        $this->documentosNuevos = [];
        $this->ficheros         = [];
        $this->resetValidation();
        $this->abrir = true;
    }

    /**
     * La segunda convocatoria acompaña a la primera mientras siga con la separación de
     * fábrica (o esté vacía). Si se le ha puesto otra hora a mano, ya no se toca.
     * En el hook "updating" $this->hora_primera_convocatoria aún es la hora anterior.
     */
    public function updatingHoraPrimeraConvocatoria($valor)
    {
        if (! preg_match('/^\d{2}:\d{2}/', (string) $valor)) {
            return;
        }

        $anterior = $this->hora_primera_convocatoria;
        $seguiaLaDeFabrica = blank($this->hora_segunda_convocatoria)
            || (filled($anterior) && $this->hora_segunda_convocatoria === ConvocatoriaJunta::horaSegundaPorDefecto($anterior));

        if ($seguiaLaDeFabrica) {
            $this->hora_segunda_convocatoria = ConvocatoriaJunta::horaSegundaPorDefecto($valor);
        }
    }

    /** Cada selección del input se suma a las anteriores (el input solo trae la última). */
    public function updatedFicheros(): void
    {
        $this->validate([
            'ficheros.*' => ['file', 'max:'.config('documentos.max_kb'), 'mimes:'.implode(',', config('documentos.extensiones'))],
        ], [
            'max'   => 'El fichero no puede pasar de :max KB',
            'mimes' => 'Solo se admiten ficheros :values',
        ]);

        $this->documentosNuevos = [...$this->documentosNuevos, ...$this->ficheros];
        $this->ficheros = [];
    }

    public function quitarDocumento(int $id): void
    {
        $this->documentos = array_values(array_filter($this->documentos, fn ($documento) => $documento['id'] !== $id));
    }

    /** $nombre: el del fichero temporal de Livewire, único por subida. */
    public function quitarDocumentoNuevo(string $nombre): void
    {
        $this->documentosNuevos = array_values(array_filter(
            $this->documentosNuevos,
            fn (TemporaryUploadedFile $fichero) => $fichero->getFilename() !== $nombre,
        ));
    }

    public function anadirLinea(): void
    {
        $this->lineas[] = ['id' => null, 'clave' => (string) Str::uuid(), 'texto' => ''];
    }

    public function abrirImportar(): void
    {
        $this->importarTexto = '';
        $this->resetErrorBag('importarTexto');
        $this->importarAbierto = true;
    }

    /**
     * Añade detrás de los puntos que ya hubiera los que salgan del texto pegado (ver
     * LeerOrdenDelDia). Los puntos en blanco que hubiera se quitan antes: suelen ser el
     * que se añadió para empezar a escribir. Como el resto, se guarda con el formulario.
     */
    public function importarLineas(LeerOrdenDelDia $leer): void
    {
        $textos = $leer->ejecutar($this->importarTexto);

        if (! $textos) {
            $this->addError('importarTexto', __('No se ha encontrado ningún punto en el texto'));

            return;
        }

        $this->lineas = array_values(array_filter($this->lineas, fn ($linea) => filled(trim($linea['texto'] ?? ''))));

        foreach ($textos as $texto) {
            $this->lineas[] = ['id' => null, 'clave' => (string) Str::uuid(), 'texto' => $texto];
        }

        $this->olvidarErroresDeLineas();
        $this->importarAbierto = false;
        $this->dispatch('toast-success', ['title' => __('Puntos importados: :n', ['n' => count($textos)])]);
    }

    public function quitarLinea(string $clave): void
    {
        $this->lineas = array_values(array_filter($this->lineas, fn ($linea) => $linea['clave'] !== $clave));
        $this->olvidarErroresDeLineas();
    }

    /**
     * Suelta el punto $clave justo antes del punto $antesDe; con $antesDe nulo (la
     * franja de debajo del último), al final. Solo cambia el orden en pantalla: se
     * guarda con el resto del formulario.
     */
    public function moverLineaAntesDe(string $clave, ?string $antesDe = null): void
    {
        $origen = array_search($clave, array_column($this->lineas, 'clave'), true);

        if ($origen === false || $clave === $antesDe) {
            return;
        }

        $linea = $this->lineas[$origen];
        $resto = array_values(array_filter($this->lineas, fn ($l) => $l['clave'] !== $clave));

        if ($antesDe === null) {
            $posicion = count($resto);
        } else {
            $posicion = array_search($antesDe, array_column($resto, 'clave'), true);

            if ($posicion === false) {
                return;
            }
        }

        array_splice($resto, $posicion, 0, [$linea]);
        $this->lineas = $resto;
        $this->olvidarErroresDeLineas();
    }

    /** Los errores de los puntos van por índice: al mover o quitar uno, apuntarían a otro. */
    private function olvidarErroresDeLineas(): void
    {
        $claves = array_filter($this->getErrorBag()->keys(), fn ($clave) => str_starts_with($clave, 'lineas.'));

        if ($claves) {
            $this->resetErrorBag($claves);
        }
    }

    public function guardar()
    {
        $data = $this->validate();
        unset($data['lineas']);

        DB::transaction(function () use ($data) {
            if ($this->itemId) {
                $convocatoria = ConvocatoriaJunta::where('comunidad_id', session('comunidad_actual_id'))->findOrFail($this->itemId);
                $convocatoria->update($data);
            } else {
                $convocatoria = ConvocatoriaJunta::create($data + ['comunidad_id' => session('comunidad_actual_id')]);
            }

            $this->guardarLineas($convocatoria);
            $this->guardarDocumentos($convocatoria);
        });

        $this->dispatch('toast-success', ['title' => $this->itemId ? __('Convocatoria modificada') : __('Convocatoria creada')]);

        $this->dispatch('convocatoria-junta-guardada');
        $this->cerrar();
    }

    /**
     * Deja en BD los puntos tal cual están en el formulario: los quitados se borran, los
     * que ya existían conservan su id y los nuevos se crean. Los ids se cruzan con los
     * de la propia convocatoria, así que un id ajeno colado en $lineas se trata como nuevo.
     */
    private function guardarLineas(ConvocatoriaJunta $convocatoria): void
    {
        $lineas = array_values(array_filter($this->lineas, fn ($linea) => filled(trim($linea['texto'] ?? ''))));
        $existentes = LineaOrdenDelDia::where('convocatoria_junta_id', $convocatoria->id)->pluck('id')->all();
        $siguen = array_values(array_intersect(array_column($lineas, 'id'), $existentes));

        LineaOrdenDelDia::where('convocatoria_junta_id', $convocatoria->id)->whereNotIn('id', $siguen)->delete();

        foreach ($lineas as $i => $linea) {
            $valores = ['orden' => $i + 1, 'texto' => trim($linea['texto'])];

            if (in_array($linea['id'], $siguen, true)) {
                LineaOrdenDelDia::where('id', $linea['id'])->update($valores);
            } else {
                LineaOrdenDelDia::create($valores + ['convocatoria_junta_id' => $convocatoria->id]);
            }
        }
    }

    /**
     * Sube los nuevos y borra los quitados (cada uno con su fichero, ver Documento). Se
     * borran al final: si una subida falla, la transacción deshace sin haber borrado ningún
     * fichero. Los quitados se buscan antes de subir: si no, los nuevos también lo parecerían.
     */
    private function guardarDocumentos(ConvocatoriaJunta $convocatoria): void
    {
        $quitados = $convocatoria->documentos()
            ->whereNotIn('id', array_column($this->documentos, 'id'))
            ->get();

        foreach ($this->documentosNuevos as $fichero) {
            $convocatoria->adjuntarDocumento($fichero, TipoDocumento::CONVOCATORIA_JUNTA);
        }

        $quitados->each->delete();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.convocatorias-juntas.formulario', [
            'tipos' => TipoJunta::orderBy('id')->pluck('descripcion', 'id'),
        ]);
    }
}
