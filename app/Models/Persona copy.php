<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Persona extends Model
{

    use Notifiable, LogsActivity;

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
    ];
    protected $dates = [
        'fecha_nacimiento',
    ];

    protected $appends = ['iniciales', 'nombreIniciales'];
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

    public static function listNombreCompleto(){
        return config('settings.list_nombre_completo', 1);
    } 



    public function estado()
    {
        return $this->belongsTo(Estado::class);
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

    public function es_usuario()
    {
        $usuario = User::where('persona_id', $this->persona_id)->first();
        return $usuario ? true : false;
    }

    // public function direcciones()
    // {
    //     return $this->hasMany(Direccion::class)->activo();
    // }

    // public function direccionesTodas()
    // {
    //     return $this->hasMany(Direccion::class);
    // }

    // public function domicilios()
    // {
    //     return $this->hasMany(Direccion::class)
    //         ->where('tipo_direccion_id', K_DIRECCIONES_DOMICILIO)
    //         ->activo();
    // }

    public function nacionalidad()
    {
        return $this->belongsTo(Pais::class, 'nacionalidad_id');
    }

    public function scopeFullname($query)
    {
        $orden = config('settings.nombrecompleto.apellidosnombre', env('LIST_NOMBRECOMPLETO', 1)); //{1=Nombre+apellidos, 2=apellidos,Nombre}
        if ($orden == 1) {
            return $query->addSelect(DB::raw('concat(nombre,\' \',apellido1,\' \',apellido2) as fullname'));
        } else {
            return $query->addSelect(DB::raw('concat(apellido1,\' \',apellido2,\' \',nombre) as fullname'));
        }
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
            trim($this->nombre ?? '') . ' ' . substr(ucfirst($this->apellido1 ??''), 0, 1) . ' ' . substr($this->apellido2 ?? '', 0, 1),
        );
    }

    protected function iniciales(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) =>
            strtoupper(substr($this->nombre ??'',0,1) . substr($this->apellido1 ?? '', 0, 1)),
        );

        // substr($attributes['nombre']??'',0,1) . substr($attributes['apellido1'], 0, 1),
    }

    private function getNombreApellidos($orden = 1)
    {
        return $orden == 1 ? $this->attributes['nombre'] . ' ' . $this->attributes['apellido1'] . ' ' . $this->attributes['apellido2']
            : $this->attributes['apellido1'] . ' ' . ($this->attributes['apellido2'] ?: '') . ', ' . $this->attributes['nombre'];
    }

    protected function nombrePersona(): Attribute
    {
        return Attribute::make(
            get: (function ($value, $attributes) {
                $orden = config('settings.nombrecompleto.apellidosnombre', env('LIST_NOMBRECOMPLETO', 1)); //{1=Nombre+apellidos, 2=apellidos,Nombre}
                if ($this->tipo_documento_id == K_TIPOS_CIF) {
                    return $attributes['razon_social'];
                } else {
                    return $this->getNombreApellidos($orden);
                }
            }),
        );
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

    static function sqlNombreCompleto($withAlias = false){
        if (Persona::listNombreCompleto() == 2){
            return "CONCAT_WS(' ', TRIM(apellido1), TRIM(apellido2), ' ', TRIM(nombre))".($withAlias ? " AS nombre_completo" : ""); 
        } else {
            return "CONCAT_WS(' ', TRIM(nombre), TRIM(apellido1), TRIM(apellido2))".($withAlias ? " AS nombre_completo" : ""); 
        }
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
        return $query->where('estado', K_PERSONAS_ESTADO_ACTIVO);
    }

    protected function esBorrable(): Attribute
    {
        return Attribute::get(fn() => false); // !($this->EsSocio || $this->EsProfesor || $this->EsAlumno || $this->EsUsuario || $this->esContacto || $this->esTitularcb);
    }

    protected function nifCompleto(): Attribute
    {
        return Attribute::get(function () {
            $prefijo = ($this->paisNif->codigo1 ?? '') !== 'ES' ? ($this->paisNif->codigo1 . ' ') : '';
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
