<?php

namespace App\Models;

use App\Models\Traits\ConCopiaAlBorrar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Documento adjunto (tabla 'documentos', compartida con L9). Es polimórfico: cuelga
 * de cualquier documentable (Alumno, Profesor, Socio, Empresa…) vía el trait
 * ConDocumentos. El fichero vive en el disco 'documentos' (config/documentos.php),
 * con el nombre aleatorio de 'nombrefichero'; 'nombrelocal' guarda el nombre original
 * con el que lo subió el usuario, que es el que se le muestra.
 */
class Documento extends Model
{
    use ConCopiaAlBorrar;

    protected $table = 'documentos';

    protected $fillable = [
        'fechaalta',
        'tipo_documento_id',
        'descripcion',
        'nombrefichero',
        'nombrelocal',
        'camino',
        'extension',
        'size',
        'estado_id',
    ];

    protected $casts = [
        'fechaalta' => 'datetime',
    ];

    // T-L9-L12: columna legacy congelada (ya no la escribe este modelo); estado_id manda. Eliminar al apagar L9.
    protected $hidden = ['estado'];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function tipo()
    {
        return $this->belongsTo(TipoDocumento::class, 'tipo_documento_id');
    }

    public function scopeActivo($query)
    {
        return $query->where('estado_id', Estado::ESTADO_ACTIVO);
    }

    /** Nombre a mostrar: el original si se conserva (L9 no lo guardaba) y, si no, la descripción. */
    public function getNombreMostradoAttribute(): string
    {
        return $this->nombrelocal ?: trim($this->descripcion . '.' . $this->extension, '.');
    }

    /** Ruta del fichero dentro del disco de documentos. */
    public function getRutaAttribute(): string
    {
        return ltrim(trim((string) $this->camino, '/') . '/' . $this->nombrefichero, '/');
    }

    public function existeFichero(): bool
    {
        return static::disco()->exists($this->ruta);
    }

    /** Tamaño legible (para la lista). */
    public function getTamanoAttribute(): string
    {
        $bytes = (int) $this->size;

        return $bytes >= 1048576
            ? round($bytes / 1048576, 1) . ' MB'
            : max(1, round($bytes / 1024)) . ' KB';
    }

    public static function disco()
    {
        return Storage::disk(config('documentos.disco'));
    }

    /**
     * Sube el fichero al disco de documentos y devuelve sus metadatos (aún sin fila).
     * En un alta a medias se deja en la carpeta de borradores; al grabar se mueve.
     */
    public static function subirFichero(UploadedFile $fichero, bool $enBorrador = false): array
    {
        $extension = strtolower($fichero->getClientOriginalExtension());
        $nombre    = Str::random(40) . '.' . $extension;
        $camino    = $enBorrador ? config('documentos.carpeta_borradores') : '';

        static::disco()->putFileAs($camino, $fichero, $nombre);

        return [
            'nombrefichero' => $nombre,
            'nombrelocal'   => $fichero->getClientOriginalName(),
            'camino'        => $camino,
            'extension'     => $extension,
            'size'          => $fichero->getSize(),
        ];
    }

    /** Saca el fichero de la carpeta de borradores y lo deja junto a los definitivos. */
    public static function consolidarFichero(array $metadatos): array
    {
        $origen = ltrim(trim((string) ($metadatos['camino'] ?? ''), '/') . '/' . $metadatos['nombrefichero'], '/');

        if (($metadatos['camino'] ?? '') !== '' && static::disco()->exists($origen)) {
            static::disco()->move($origen, $metadatos['nombrefichero']);
        }

        return ['camino' => ''] + $metadatos;
    }

    protected static function booted()
    {
        static::creating(function (Documento $documento) {
            $documento->fechaalta ??= now();
            $documento->estado_id ??= Estado::ESTADO_ACTIVO;
        });

        // El borrado es físico (con copia en sine_nomines): el fichero también se va.
        static::deleted(function (Documento $documento) {
            static::disco()->delete($documento->ruta);
        });
    }
}
