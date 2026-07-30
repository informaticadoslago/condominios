<?php
namespace App\Models;

use App\Models\Foto;
use App\Models\Persona;
use App\Models\Contacto;
use App\Models\Direccion;
use App\Models\EstadoAlumno;
use App\Models\TiposDeTipos;
use App\Models\Traits\ConDocumentos;
use App\Models\Traits\ConHistorialEstado;
use App\Models\Traits\ConCopiaAlBorrar;
use Illuminate\Database\Eloquent\Model;


class Alumno extends Model
{

    use ConCopiaAlBorrar;
    use ConDocumentos;
    use ConHistorialEstado;

    // T-L9-L12: constantes de clase (antes K_ALUMNOS_ESTADO_*) tomadas de app/defines.php (L9)
    // Coinciden con el catálogo estado_alumnos (EstadoAlumno::ALUMNO_*).
    const
    ESTADO_TODOS  = 0,
    ESTADO_ACTIVO = 1,
    ESTADO_BAJA   = 2;

    protected $fillable = [
        'persona_id',
        'fecha_alta',
        'fecha_baja',
        'socio_id', 'titularcb_id',
        'relacionsocio',
        'forma_de_pago_id',
        'entidad_bancaria_id',
        'preferido_contacto_id',
        'iban', 'comentarios',
        'estado_id',
    ];
    protected $casts = [
        'fecha_alta' => 'date:d-m-Y',
        'fecha_baja' => 'date:d-m-Y',
    ];

    // T-L9-L12: columnas legacy (quedan en su DEFAULT; L9 congelado no las lee). Eliminar al apagar L9.
    protected $hidden = ['estado', 'tipo_formadepago_id', 'tipo_entidadfinanciera_id', 'fechaalta', 'fechabaja'];

    protected $appends = ['nombreCompleto'];

    protected $with = ['persona'];

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'persona_id')->where('estado_id', '<>', EstadoPersona::PERSONA_ANONIMA);
    }

    public function estado()
    {
        return $this->belongsTo(EstadoAlumno::class, 'estado_id');
    }

    // T-L9-L12: Eliminar cuando se borre la columna de la BD
    // Forzar que $alumno->estado siempre devuelva la relación
    public function getEstadoAttribute()
    {
        if ($this->relationLoaded('estado')) {
            return $this->getRelation('estado');
        }

        return $this->getRelationValue('estado');
    }

    // Si necesitas acceder al valor antiguo ocasionalmente
    // T-L9-L12: Eliminar cuando se borre la columna de la BD
    public function getEstadoObsoletoAttribute()
    {
        return $this->attributes['estado'];
    }

    // Descripción del estado (para la lista). Usa la relación ya cargada
    // (with('estado')) para no provocar N+1.
    public function getDescripcionEstadoAttribute()
    {
        return $this->estado?->descripcion ?? '';
    }

    public function contactos()
    {
        return $this->hasMany(Contacto::class, 'persona_id', 'persona_id')->activo();
    }

    public function contactoPreferido()
    {
        return $this->belongsTo(Contacto::class, 'preferido_contacto_id');
    }

    public function direcciones()
    {
        return $this->hasMany(Direccion::class, 'persona_id', 'persona_id')->activo();
    }

    public function socio()
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }

    public function socioactivo()
    {
        return $this->belongsTo(Socio::class, 'socio_id')->activo();
    }

    public function titularcb()
    {
        return $this->belongsTo(Persona::class, 'titularcb_id');
    }

    // T-L9-L12: relación legacy al cajón genérico. Eliminar al apagar L9.
    public function tipoFormadePago() // mismo nombre que en socios
    {
        return $this->belongsTo(TiposDeTipos::class, 'tipo_formadepago_id');
    }

    // T-L9-L12: relación legacy al cajón genérico. Eliminar al apagar L9.
    public function entidadfinanciera()
    {
        return $this->belongsTo(TiposDeTipos::class, 'tipo_entidadfinanciera_id');
    }

    // Catálogos propios (L12): la columna legacy queda en su DEFAULT (L9 congelado).
    public function formaDePago()
    {
        return $this->belongsTo(FormaDePago::class, 'forma_de_pago_id');
    }

    public function entidadBancaria()
    {
        return $this->belongsTo(EntidadBancaria::class, 'entidad_bancaria_id');
    }

    public function matriculacion()
    {
        return $this->hasMany(Matriculacion::class);
    }

    public function matriculacionCurso($cursoid)
    {
        return $this->hasMany(Matriculacion::class)->where('curso_id', $cursoid)->first();
    }

    public function grupos()
    {
        return $this->hasMany(AlumnoGrupo::class);
    }

    public function familiares()
    {
        return $this->hasMany(AlumnoFamiliar::class, 'alumno_id');
    }

    public function relacionsociotipo()
    {
        return $this->belongsTo(TiposDeTipos::class, 'relacionsocio');
    }

    public function scopeActivo($query)
    {
        return $query->where('alumnos.estado_id', EstadoAlumno::ALUMNO_ACTIVO);
    }

    public function fotoFicha()
    {
        return $this->hasOne(Foto::class, 'persona_id', 'persona_id');
    }

    public function getFechaaltaEsAttribute()
    {
        return date_format(new \Carbon\Carbon($this->attributes['fecha_alta']), 'd-m-Y');
    }

    public function getFechabajaEsAttribute()
    {
        return date_format(new \Carbon\Carbon($this->attributes['fecha_baja']), 'd-m-Y');
    }

    /** El IBAN se muestra en trozos de 4. Un alumno puede no tenerlo (pago en efectivo). */
    public function getIbanAttribute()
    {
        $iban = $this->attributes['iban'] ?? null;

        return blank($iban) ? null : iban2trozosx4($iban);
    }

    public function getRazonesBorrableAttribute()
    {
        $razones['matriculacion'] = [$this->matriculacion()->count() > 0, __('messages.por estar matriculado')];
        $razones['familiares']    = [$this->familiares()->count() > 0, __('messages.por estar relacionado con familiares')];
        $razones['grupos']        = [$this->grupos()->count() > 0, __('messages.por estar o haber estado en algún grupo')];
        $razones['documentos']    = [$this->documentos()->count() > 0, __('messages.por tener documentos')];

        $borrable = true;
        foreach ($razones as $razon) {
            $borrable = ! $razon[0] && $borrable;
            if (! $borrable) {break;}
        }
        $razones['borrable'] = [$borrable, $borrable ? __('messages.Es borrable') : __('messages.No es borrable')];
        return $razones;
    }

    public function getEsBorrableAttribute()
    {
        $razones = $this->razonesBorrable;
        return $razones['borrable'][0];
    }

    public function getEsBajaAttribute()
    {
        return $this->estado_id == EstadoAlumno::ALUMNO_BAJA;
    }

    public function getNombreCompletoAttribute()
    {
        return $this->persona->nombreCompleto ?? '';
    }

}
