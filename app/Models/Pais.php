<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pais extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'paises';

    const ESPAÑA = 67;

    // Estado (catálogo estados compartido). La columna legacy 'estado' la sincroniza un trigger.
    const
    ESTADO_ACTIVO = 1,
    ESTADO_BAJA   = 2;

    // Grupo del país (solo UE o Resto). España va en UE; su set de documentos
    // propio se decide por ser España (id), no por un grupo aparte.
    const
    GRUPO_UE   = 'UE',
    GRUPO_OTRO = 'OTRO';

    protected $fillable = ['nombre', 'codigo1', 'codigo2', 'grupo', 'orden', 'estado_id'];

    // $table->id();
    // $table->string('codigo2',2);
    // $table->string('codigo3',3)->nullable();
    // $table->string('nombre',50);
    // $table->foreignId('estado_id')->default(Estado::ESTADO_ACTIVO)->constrained();
    // $table->tinyInteger('orden')->default(1);

    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }

    // La tabla tiene columna legacy 'estado' (int) que eclipsa la relación; este
    // accesor da la descripción del catálogo usando la relación ya cargada (with('estado')).
    public function getDescripcionEstadoAttribute()
    {
        return $this->getRelationValue('estado')?->descripcion ?? '';
    }

    public function provincias()
    {
        return $this->hasMany(Provincia::class);
    }

    /** País por defecto (preseleccionado cuando no hay país). Configurable en config/doslago.php. */
    public static function porDefecto(): int
    {
        return (int) config('doslago.pais.inicial', self::ESPAÑA);
    }

    public function scopeActivo(Builder $query): void
    {
        $query->where('estado_id', Estado::ESTADO_ACTIVO);
    }

    public function scopeOrdenado(Builder $query): void
    {
        $query->orderBy('orden', 'desc')->orderBy('nombre');
    }

    /** Orden para los desplegables: primero UE, luego resto; alfabético dentro de cada grupo. */
    public function scopeOrdenGrupo(Builder $query): void
    {
        $query->orderByRaw('FIELD(grupo, ?, ?)', [self::GRUPO_UE, self::GRUPO_OTRO])
            ->orderBy('nombre');
    }

}
