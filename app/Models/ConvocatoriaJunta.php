<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ConvocatoriaJunta extends Model
{
    /** Separación de partida entre la primera y la segunda convocatoria. */
    const MINUTOS_ENTRE_CONVOCATORIAS = 30;

    /** Disco donde se guarda el PDF de la carta (ver rutaCarta). */
    const DISCO_CARTAS = 'local';

    protected $table = 'convocatorias_juntas';

    protected $fillable = [
        'comunidad_id',
        'tipo_junta_id',
        'fecha',
        'hora_primera_convocatoria',
        'hora_segunda_convocatoria',
        'fecha_carta',
    ];

    protected $casts = [
        'fecha'       => 'date',
        'fecha_carta' => 'date',
    ];

    /** Si al crearla no se dice la hora de la segunda, va la de fábrica tras la primera. */
    protected static function booted(): void
    {
        static::creating(function (self $convocatoria) {
            if (blank($convocatoria->hora_segunda_convocatoria) && filled($convocatoria->hora_primera_convocatoria)) {
                $convocatoria->hora_segunda_convocatoria = self::horaSegundaPorDefecto($convocatoria->hora_primera_convocatoria);
            }
        });

        // Los borrados en bloque (ComunidadEliminador) no pasan por aquí: esos borran la
        // carpeta entera de la comunidad (ver carpetaCartas).
        static::deleted(function (self $convocatoria) {
            Storage::disk(self::DISCO_CARTAS)->delete($convocatoria->rutaCarta());
        });
    }

    /** Carpeta con los PDF de las cartas de todas las convocatorias de una comunidad. */
    public static function carpetaCartas(int $comunidadId): string
    {
        return "convocatorias-juntas/{$comunidadId}";
    }

    /** PDF generado de la carta (una página por inmueble). Se sobrescribe al regenerarla. */
    public function rutaCarta(): string
    {
        return self::carpetaCartas($this->comunidad_id)."/{$this->id}.pdf";
    }

    /** Si ya hay carta generada: entonces se ve la guardada; si no, hay que generarla. */
    public function tieneCarta(): bool
    {
        return $this->fecha_carta !== null && Storage::disk(self::DISCO_CARTAS)->exists($this->rutaCarta());
    }

    /** Hora 'H:i' de la segunda convocatoria a partir de la primera ('H:i' o 'H:i:s'). */
    public static function horaSegundaPorDefecto(string $horaPrimera): string
    {
        return Carbon::createFromFormat('H:i', substr($horaPrimera, 0, 5))
            ->addMinutes(self::MINUTOS_ENTRE_CONVOCATORIAS)
            ->format('H:i');
    }

    public function comunidad()
    {
        return $this->belongsTo(Comunidad::class);
    }

    public function tipoJunta()
    {
        return $this->belongsTo(TipoJunta::class);
    }

    public function lineasOrdenDelDia()
    {
        return $this->hasMany(LineaOrdenDelDia::class)->orderBy('orden')->orderBy('id');
    }
}
