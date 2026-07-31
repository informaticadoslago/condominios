<?php
namespace App\Models;

use App\Models\Traits\ConHistorialEstado;
use App\Observers\PersonaObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[ObservedBy([PersonaObserver::class])]
class Persona extends Model
{

    use Notifiable, LogsActivity, ConHistorialEstado;

    // Estados heredados de L9 (columna legacy 'estado')
    const
    ESTADO_ACTIVO = 1,
    ESTADO_BAJA = 2,
    ESTADO_ANONIMA = 4;

    protected $fillable = [
        'nombre',
        'apellido1',
        'apellido2',
        'razon_social', 'nombre_comercial',
        'documento_pais_id',
        'tipo_documento_id',
        'documento_identificativo',
        'fecha_nacimiento',
        'alergias_alimentos', 'comentarios',
        'genero_id',
        'nacionalidad_id',
        'estado_id',
        'estados_previos',
        'invisible',
    ];
    protected $casts = [
        'fecha_nacimiento' => 'date',
        'estados_previos'  => 'array',
    ];

    // FIXME: al añadir nombreIniciales al appends rompe por estar usando attribute 9+ en vez de getNombreInicialesAttribute() que es lo que busca
    protected $appends = ['iniciales',];
    // 'fechaNacimientoFormated', 'nombreCompleto', 'edad',
    //     // 'esAlumno', 'esProfesor', 'esSocio',
    //     'esUsuario', 'generoNombre',
    //     // 'isAldiaPago',
    // ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->logOnlyDirty();
        // Chain fluent methods for configuration options
    }

    public static function listNombreCompleto()
    {
        return config('settings.list_nombre_completo', 1);
    }

    public function estado()
    {
        return $this->belongsTo(EstadoPersona::class, 'estado_id');
    }

    // T-L9-L12: Eliminar cuando se borre la columna de la BD
    // Forzar que $usuario->estado siempre devuelva la relación
    public function getEstadoAttribute()
    {
        // Si la relación ya está cargada, devolverla
        if ($this->relationLoaded('estado')) {
            return $this->getRelation('estado');
        }

        // Si no, cargarla y devolverla
        return $this->getRelationValue('estado');
    }

    // Si necesitas acceder al valor antiguo ocasionalmente
    // T-L9-L12: Eliminar cuando se borre la columna de la BD
    public function getEstadoObsoletoAttribute()
    {
        return $this->attributes['estado'];
    }

    public function usuario()
    {
        return $this->hasOne(User::class);
    }

    public function tipoDocumentoIdentificativo()
    {
        return $this->belongsTo(TipoDocumentoIdentificativo::class, 'tipo_documento_id');
    }

    public function paisDocumentoIdentificativo()
    {
        return $this->belongsTo(Pais::class, 'documento_pais_id');
    }

    public function paisNif()
    {
        return $this->belongsTo(Pais::class, 'nif_pais_id');
    }

    public function es_usuario()
    {
        $usuario = User::where('persona_id', $this->persona_id)->first();
        return $usuario ? true : false;
    }

    public function direcciones()
    {
        return $this->morphMany(Direccion::class, 'direccionable');
    }

    public function domicilio()
    {
        return $this->morphOne(Direccion::class, 'direccionable')
            ->where('tipo_direccion_id', TipoDireccion::idDomicilio());
    }

    public function contactos()
    {
        return $this->morphMany(Contacto::class, 'contactable');
    }

    public function nacionalidad()
    {
        return $this->belongsTo(Pais::class, 'nacionalidad_id');
    }

    public function scopeEsAdulto($query)
    {
        $fechaAdulto = new \Carbon\Carbon(now());
        $fechaAdulto->subYears(18);
        return $query->where('fecha_nacimiento', '<=', $fechaAdulto);
    }

    protected function nombreIniciales(): Attribute
    {
        return Attribute::make(
            get: fn() =>
            trim($this->nombre ?? '') . ' ' . substr(ucfirst($this->apellido1 ?? ''), 0, 1) . ' ' . substr($this->apellido2 ?? '', 0, 1),
        );
    }

    protected function iniciales(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) =>
            strtoupper(substr($this->nombre ?? '', 0, 1) . substr($this->apellido1 ?? '', 0, 1)),
        );

    }

    private function getNombreApellidos($orden = 1)
    {
        $nombre    = $this->nombre;
        $apellido1 = $this->apellido1;
        $apellido2 = $this->apellido2 ?? '';

        return $orden == 1
            ? trim("$nombre $apellido1 $apellido2")
            : trim("$apellido1 $apellido2") . ", $nombre";
    }

    // public function getNombreYApellidosAttribute()
    // {
    //     return $this->getNombreApellidos(1);
    // }

    protected function nombreYApellidos(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->getNombreApellidos(1)
        );
    }

    protected function apellidosYNombre(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->getNombreApellidos(2),
        );
    }

    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(function () {
            if ($this->tipo_documento_id == TipoDocumentoIdentificativo::DOCUMENTO_CIF) {
                return $this->razon_social;
            }

            $orden = config('settings.nombrecompleto.apellidosnombre', env('LIST_NOMBRECOMPLETO', 1));
            return $this->getNombreApellidos($orden);
        });
    }

    protected function apellidos(): Attribute
    {
        return Attribute::make(
            get: fn() => trim(($this->attributes['apellido1'] ?? '') . ' ' . $this->attributes['apellido2']),
        );
    }

    /**
     * Excluye a las personas marcadas 'invisible' (p. ej. el superadmin): no pueden
     * salir en buscadores ni ser asignadas a ningún rol (propietario, proveedor…).
     * No se aplica como global scope a propósito: los listados de administración de
     * personas/usuarios sí necesitan poder verlas y gestionarlas.
     */
    public function scopeVisible($query)
    {
        return $query->where('invisible', false);
    }

    public function scopeBuscarNombreCompleto($q, ?string $search)
    {
        if (! $search) {
            return $q;
        }

        $terms = preg_split('/\s+/', trim($search));
        foreach ($terms as $term) {
            $q->where(function ($q2) use ($term) {
                $q2->orWhere('nombre', 'like', "%{$term}%")
                    ->orWhere('apellido1', 'like', "%{$term}%")
                    ->orWhere('apellido2', 'like', "%{$term}%");
            });
        }

        return $q;
    }

    protected function fechanacimientoFormated(): Attribute
    {

        return Attribute::get(fn() => isset($this->fecha_nacimiento)
                ? Carbon::parse($this->fecha_nacimiento)->format('d-m-Y')
                : null
        );
    }

    protected function edad(): Attribute
    {
        return Attribute::get(fn() => isset($this->attributes['fechanacimiento'])
                ? Carbon::parse($this->attributes['fechanacimiento'])->diffInYears()
                : null
        );
    }

    protected function generoNombre(): Attribute
    {
        return Attribute::get(function () {
            $generos = config('defines.personas.genero', []);
            return $generos[$this->attributes['genero']] ?? '';
        });
    }

    protected function esUsuario(): Attribute
    {
        return Attribute::get(fn() => $this->usuario()->exists());

        // $esUsuario = User::where('persona_id', $this->attributes['id'])->count();
        // return Attribute::make(
        //     get: fn() => $esUsuario === 1,
        // );

    }

    protected function esMayorEdad(): Attribute
    {
        return Attribute::get(fn() => ($this->edad ?? 0) >= K_EDAD_MAYOREDAD);
    }

    public function scopeActiva($query)
    {
        return $query->where('estado_id', EstadoPersona::PERSONA_ACTIVA);
    }

    protected function esBorrable(): Attribute
    {
        return Attribute::get(fn() => false); // !($this->EsSocio || $this->EsProfesor || $this->EsAlumno || $this->EsUsuario || $this->esContacto || $this->esTitularcb);
    }

    protected function nifCompleto(): Attribute
    {
        return Attribute::get(function () {
            $codigo = $this->paisNif?->codigo1;
            $prefijo = ($codigo && $codigo !== 'ES') ? $codigo . ' ' : '';
            return $prefijo . ($this->attributes['nif'] ?? '');
        });
    }

    protected function nif(): Attribute
    {
        return Attribute::make(
            get: fn()       => $this->attributes['nif'] ?? null,
            set: fn($value) => $value === '' ? null : strtoupper($value)
        );
    }
    public function scopeHombre(Builder $query): void
    {
        $query->where('genero', TipoGenero::GENERO_HOMBRE);
    }

    public function scopeMujer(Builder $query): void
    {
        $query->where('genero', TipoGenero::GENERO_MUJER);
    }

}
