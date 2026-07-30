<?php
namespace App\Models;

use App\Models\Estado;
use App\Models\Persona;
use App\Models\Contacto;
use App\Models\Direccion;
use App\Models\TiposDeTipos;
use App\Models\Traits\ConCopiaAlBorrar;
use App\Models\Traits\ConDocumentos;
use App\Models\Traits\ConHistorialEstado;
use Spatie\Valuestore\Valuestore;
use Illuminate\Database\Eloquent\Model;


class Socio extends Model
{

    use ConCopiaAlBorrar;
    use ConDocumentos;
    use ConHistorialEstado;
    //use \Modules\CoreAteneo\Http\Controllers\Traits\FacturarSocioTrait;

    // Coinciden con el catálogo estado_socios (EstadoSocio::SOCIO_*).
    const
    ESTADO_ACTIVO = 1,
    ESTADO_BAJA = 2;


    protected $fillable = [
        'persona_id',
        'numsocio',
        'fecha_alta',
        'contactoateneo_id',
        'forma_de_pago_id',
        'titularcb_id',
        'entidad_bancaria_id',
        'iban',
        'comentarios',
        'estado_id',
        'fecha_baja',
        'colaborador',
        'mes_ciclo_factura',
        'anualidad_socio_id',
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
        return $this->belongsTo(Persona::class)->where('estado_id', '<>', EstadoPersona::PERSONA_ANONIMA);
    }

    public function estado()
    {
        return $this->belongsTo(EstadoSocio::class);
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

    public function scopeActivo($query)
    {
        return $query->where('estado_id', EstadoSocio::SOCIO_ACTIVO);
    }

    public function contactos()
    {
        return $this->hasMany(Contacto::class, 'persona_id', 'persona_id')->activo();
    }

    public function contactoTelefono()
    {
        return $this->hasMany(Contacto::class, 'persona_id', 'persona_id')->activo()->whereIn('tipo_contacto_id', [K_CONTACTO_TELEFONO, K_CONTACTO_MOVIL]);
    }

    public function contactoEmail()
    {
        return $this->hasMany(Contacto::class, 'persona_id', 'persona_id')->activo()->where('tipo_contacto_id', K_CONTACTO_EMAIL);
    }

    public function direcciones()
    {
        return $this->hasMany(Direccion::class, 'persona_id', 'persona_id')->activo();
    }

    public function domicilios()
    {
        return $this->hasMany(Direccion::class, 'persona_id', 'persona_id')->activo()->where('tipo_direccion_id', K_DIRECCIONES_DOMICILIO);
    }

    public function contactoateneo()
    {
        return $this->belongsTo(Socio::class, 'contactoateneo_id');
    }

    public function titularcb()
    {
        return $this->belongsTo(Persona::class, 'titularcb_id');
    }

    // T-L9-L12: relación legacy al cajón genérico. Eliminar al apagar L9.
    public function entidadfinanciera()
    {
        return $this->belongsTo(TiposDeTipos::class, 'tipo_entidadfinanciera_id');
    }

    // T-L9-L12: relación legacy al cajón genérico. Eliminar al apagar L9.
    public function tipoFormadePago()
    {
        return $this->belongsTo(TiposDeTipos::class, 'tipo_formadepago_id');
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

    public function anualidadencurso()
    {
        // $corevaluestore = Valuestore::make(storage_path('app/' . config('coreateneo.coresettings.storage_settings_path', 'settings/'))
        //     . (config('coreateneo.coresettings.storage_settings_file', 'coresettings.json')));

        // $anualidadencurso = $corevaluestore->get('anualidad', '0');
        // return $this->hasOne(AnualidadSocio::class, 'socio_id')->where('anualidad_id', $anualidadencurso);
    }

    public function anualidades()
    {
        return $this->hasMany(AnualidadSocio::class, 'socio_id');
    }

    /** La cuota del socio (AnualidadSocio) de la anualidad en curso, si ya se le dio de alta. */
    public function cuotaEnCurso()
    {
        return $this->hasOne(AnualidadSocio::class, 'socio_id')
            ->where('anualidad_id', optional(Anualidad::enCurso())->id);
    }

    public function sociorizado()
    {
        return $this->hasMany(Alumno::class, 'socio_id');
    }

    public function getEsSocioAttribute()
    {
        $esSocio = Alumno::where('socio_id', $this->attributes['id'])->count();
        return $esSocio >= 1;
    }

    public function getEsContactoAteneoAttribute()
    {
        $esContactoAteneo = Socio::where('contactoateneo_id', $this->attributes['id'])->count();
        return $esContactoAteneo >= 1;
    }

    public function getEsSocioAlumnoAttribute()
    {
        $esSocioAlumno = Alumno::where('socio_id', $this->attributes['id'])->count();
        return $esSocioAlumno >= 1;
    }

    public function getRazonesBorrableAttribute()
    {
        $razones['alumno']         = [$this->esSocioAlumno, __('messages.por ser socio de alumno')];
        $razones['contactoateneo'] = [$this->esContactoAteneo, __('messages.por ser contacto')];
        $razones['documentos']     = [$this->documentos()->count() > 0, __('messages.por tener documentos')];

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

    public function scopeUltimoNumero($query)
    {
        return $query->orderBy('numsocio', 'DESC');
    }

    public function getNombreCompletoAttribute()
    {
        return $this->persona->nombreCompleto ?? '';
    }

    public function getDescripcionEstadoAttribute()
    {
        $descripcion = '';
        switch ($this->attributes['estado']) {
            case K_SOCIOS_ESTADO_ACTIVO:
                $descripcion = __('messages.EstadoActivo');
                break;
            case K_SOCIOS_ESTADO_BAJA:
                $descripcion = __('messages.EstadoBaja');
                break;
            case K_SOCIOS_ESTADO_TODOS:
                $descripcion = __('messages.EstadoTodos');
                break;
        }
        return $descripcion;
    }

    /** El IBAN se muestra en trozos de 4. Un socio puede no tenerlo (pago en efectivo). */
    public function getIbanAttribute()
    {
        $iban = $this->attributes['iban'] ?? null;

        return blank($iban) ? null : iban2trozosx4($iban);
    }

    public function generarFacturaSocioPrimeraMensualidad($mensualidadid)
    {
        if (! $this->anualidadencurso) {
            $anualidadEnCurso = $this->getAnualidadEnCurso();
            $anualidad        = Anualidad::find($anualidadEnCurso);
            $this->anualidades()->create([
                'anualidad_id' => $anualidad->id,
                'importe'      => $anualidad->importe]);
            $this->refresh();
        }
        if (! $this->anualidadencurso?->facturada) {
            return $this->_facturar($this->anualidadencurso->id, $mensualidadid);
        } else {
            return $this->anualidadencurso->id;
        }
    }

    public function getFechaaltaFormatedAttribute()
    {
        $fechaaltaf = new \Carbon\Carbon($this->attributes['fecha_alta']);
        return date_format($fechaaltaf, 'd-m-Y');
    }

    public function getFechabajaFormatedAttribute()
    {
        $fechabajaf = new \Carbon\Carbon($this->attributes['fecha_baja']);
        return date_format($fechabajaf, 'd-m-Y');
    }

    /** Estado de la cuota del socio en la anualidad en curso, para el listado. */
    public function getEstadoCuotaAttribute(): string
    {
        $cuota = $this->cuotaEnCurso;

        if (! $cuota) {
            return __('Sin generar');
        }

        if (! $cuota->factura_id) {
            return __('Sin facturar');
        }

        return match ($cuota->factura?->ultimoPago?->estado_id) {
            EstadoFacturaPago::PAGADO => __('Cobrada'),
            EstadoFacturaPago::ANULADO => __('Anulada'),
            default => __('Facturada, pendiente de cobro'),
        };
    }

    public function getMesCicloFacturaNombreAttribute()
    {
        $mes = $this->attributes['mes_ciclo_factura'] ?? null;

        return $mes ? ucfirst(\Carbon\Carbon::create()->month($mes)->translatedFormat('F')) : '';
    }

    // public function getIsAlDiaPagoAttribute(){

    //     $nif = $this->persona()->first()->nif;
    //     $facturas = Factura::with('ultimopago')->where('documentocliente', $nif)->get();
    //     $isAldia = true;
    //     foreach($facturas as $factura){
    //         $pendiente = $factura->ultimopago->accion == FacturasPago::ACCION_DEVUELTO;
    //         $isAldia = $isAldia && !$pendiente;
    //     }
    //     return $isAldia;
    // }

}
