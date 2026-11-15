<?php

namespace App\Models;

use App\Models\Traits\ConHistorialEstado;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Prueba evaluable de una asignatura en una evaluación.
 *
 * Una prueba «con partes» (con_partes) no tiene puntuación máxima ni notas propias: lleva
 * dentro otras pruebas (sus partes, prueba_id), cada una con su peso dentro de ella, y su
 * nota es la media de las partes. Solo hay un nivel: una parte no tiene partes.
 */
class Prueba extends Model
{
    use ConHistorialEstado;

    const
        ESTADO_ACTIVO = 1,
        ESTADO_BAJA = 2;

    /** Con alguna parte por debajo del mínimo, una media aprobada (5 o más) se queda en 4. */
    const
        NOTA_APROBADO = 5,
        NOTA_CON_PARTE_SUSPENSA = 4;

    protected $table = 'pruebas_evaluables';

    protected $fillable = [
        'asignatura_id',
        'evaluacion_id',
        'prueba_id',
        'nombre',
        'fecha',
        'puntuacion_maxima',
        'peso',
        'con_partes',
        'nota_minima',
        'orden',
        'estado_id',
    ];

    protected $casts = [
        'fecha'             => 'date',
        'puntuacion_maxima' => 'float',
        'peso'              => 'float',
        'con_partes'        => 'boolean',
        'nota_minima'       => 'float',
    ];

    public function asignatura()
    {
        return $this->belongsTo(Asignatura::class);
    }

    public function evaluacion()
    {
        return $this->belongsTo(Evaluacion::class);
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }

    public function notas()
    {
        return $this->hasMany(PruebaNota::class);
    }

    /** Las pruebas que lleva dentro, en su orden. */
    public function partes()
    {
        return $this->hasMany(self::class, 'prueba_id')->orderBy('orden')->orderBy('id');
    }

    /** La prueba de la que es parte, si lo es. */
    public function contenedora()
    {
        return $this->belongsTo(self::class, 'prueba_id');
    }

    protected static function booted(): void
    {
        // La prueba nueva va al final del orden de su asignatura (ver Pruebas\Lista::soltar).
        static::creating(function (self $prueba) {
            $prueba->orden ??= (int) self::where('asignatura_id', $prueba->asignatura_id)->max('orden') + 1;
        });

        // Lo único que una prueba con partes guarda en notas son los retoques a mano de la
        // nota final (ver notasFinales): se van con ella.
        static::deleting(function (self $prueba) {
            if ($prueba->con_partes) {
                $prueba->notas()->delete();
            }
        });
    }

    /**
     * La nota sobre 10 de una prueba con partes para un alumno, a partir de sus notas
     * sobre 10 en cada parte ([id de la parte => nota o null]):
     *  - sin nota en ninguna parte, no hay nota;
     *  - la parte sin nota cuenta como 0;
     *  - es la media según el peso de cada parte, repartida entre el peso que haya (si
     *    las partes aún no suman 100, se pondera con lo que hay);
     *  - con alguna parte por debajo de la nota mínima, una media aprobada se queda en 4
     *    y una suspensa se deja como sale.
     *
     * @param  \Illuminate\Support\Collection<int, self>  $partes  las partes que cuentan (las activas)
     */
    public function mediaDePartes($partes, array $notas): ?float
    {
        $pesoTotal = (float) $partes->sum('peso');
        $conNota = $partes->filter(fn (self $parte) => ($notas[$parte->id] ?? null) !== null);

        if ($pesoTotal <= 0 || $conNota->isEmpty()) {
            return null;
        }

        $media = round($partes->sum(fn (self $parte) => (float) ($notas[$parte->id] ?? 0) * $parte->peso) / $pesoTotal, 2);

        $algunaPorDebajo = $this->nota_minima > 0
            && $partes->contains(fn (self $parte) => (float) ($notas[$parte->id] ?? 0) < $this->nota_minima);

        return $algunaPorDebajo && $media >= self::NOTA_APROBADO ? (float) self::NOTA_CON_PARTE_SUSPENSA : $media;
    }

    /**
     * Las notas de una prueba con partes, por alumno (id de su apunte en la asignatura):
     *   'partes'    => [id de la parte => nota sobre 10 o null]
     *   'calculada' => la media (ver mediaDePartes)
     *   'final'     => la retocada a mano si la hay y sigue valiendo; si no, la calculada
     *   'retocada'  => si 'final' es un retoque
     * Solo salen los alumnos con nota en alguna parte activa.
     *
     * El retoque se guarda en prueba_evaluable_notas con la media de la que partía (columna nota) y
     * la nota puesta a mano (nota_sobre_10): si la media ya no es aquella —ha cambiado la
     * nota de una parte, un peso, el mínimo…—, el retoque deja de valer, igual que la nota
     * sobre 10 de una prueba suelta se recalcula al tocar su nota.
     *
     * @return array<int, array{partes: array<int, ?float>, calculada: ?float, final: ?float, retocada: bool}>
     */
    public function notasFinales(): array
    {
        $partes = $this->partes()->where('estado_id', self::ESTADO_ACTIVO)->get();
        $retoques = $this->notas()->get()->keyBy('asignatura_alumno_id');

        $porApunte = [];
        foreach (PruebaNota::whereIn('prueba_id', $partes->modelKeys())->get() as $nota) {
            $porApunte[$nota->asignatura_alumno_id][$nota->prueba_id] = $nota->nota_sobre_10;
        }

        $finales = [];
        foreach ($porApunte as $apunteId => $notas) {
            $notas = $partes->mapWithKeys(fn (self $parte) => [$parte->id => $notas[$parte->id] ?? null])->all();
            $calculada = $this->mediaDePartes($partes, $notas);
            $retoque = $retoques->get($apunteId);
            $vale = $retoque && $calculada !== null && abs((float) $retoque->nota - $calculada) < 0.005;

            $finales[$apunteId] = [
                'partes'    => $notas,
                'calculada' => $calculada,
                'final'     => $vale ? $retoque->nota_sobre_10 : $calculada,
                'retocada'  => $vale,
            ];
        }

        return $finales;
    }

    /** Título del historial de estados (ver ConHistorialEstadoModal). */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn () => $this->nombre);
    }

    /** La nota sobre 10 que corresponde a una nota sobre la puntuación máxima. */
    public function sobre10(float $nota): float
    {
        return round($nota * 10 / $this->puntuacion_maxima, 2);
    }

    /** Un número sin ceros de más: 30 y no 30.00, 7,5 y no 7.50. */
    public static function numero(float|string|null $valor): string
    {
        return $valor === null || $valor === '' ? '' : str_replace('.', ',', (string) round((float) $valor, 2));
    }
}
