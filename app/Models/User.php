<?php
namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Lab404\Impersonate\Models\Impersonate;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Traits\ConHistorialEstado;
use App\Support\HabilidadToken;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use Impersonate;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasRoles;
    use ConHistorialEstado;

    // Alias para ConBajaPorEstado (convención común a toda la app); EstadoUsuario
    // tiene además INICIAL, que este trait no gestiona.
    const ESTADO_ACTIVO = EstadoUsuario::USUARIO_ACTIVO;
    const ESTADO_BAJA = EstadoUsuario::USUARIO_INACTIVO;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'login',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',

        'estado', // T-L9-L12 : Eliminar cuando se borre la columna de la BD
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function estado()
    {
        return $this->belongsTo(EstadoUsuario::class);
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
        return $query->where('estado_id', EstadoUsuario::USUARIO_ACTIVO);
    }

    public function scopeVisible($query)
    {
        return $query->whereIn('estado_id', [EstadoUsuario::USUARIO_ACTIVO, EstadoUsuario::USUARIO_INICIAL]);
    }

    public function isActive()
    {
        return $this->attributes['estado_id'] == EstadoUsuario::USUARIO_ACTIVO;
    }

    public function isInactive()
    {
        return $this->attributes['estado_id'] == EstadoUsuario::USUARIO_INACTIVO;
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(
            fn() => $this->persona?->nombreCompleto
        );
    }

    public function isSuperadmin()
    {
        return $this->hasRole(config('doslago.superadmin.nombre_rol'));
    }

    public function isNotSuperadmin()
    {
        return ! $this->isSuperadmin();
    }

    public function canImpersonate()
    {
        return $this->can('puede-impersonate');
    }

    public function canBeImpersonated()
    {
        return $this->isNotSuperadmin() && $this->isActive();
    }

    /**
     * Comunidades en las que este usuario puede entrar: todas si tiene el rol
     * "global", o solo aquellas cuyo rol puerta (Comunidad::nombreRol()) tenga.
     */
    public function comunidadesAccesibles()
    {
        if ($this->hasRole('global')) {
            return Comunidad::activa()->get();
        }

        $ids = $this->roles()
            ->where('name', 'like', 'comunidad-%')
            ->pluck('name')
            ->map(fn ($nombre) => (int) str_replace('comunidad-', '', $nombre));

        return Comunidad::activa()->whereIn('id', $ids)->get();
    }

    /**
     * Empresas contables en las que este usuario puede entrar: todas si tiene el
     * rol "global", o solo aquellas cuyo rol puerta (EmpresaContable::nombreRol())
     * tenga.
     */
    public function empresasContablesAccesibles()
    {
        if ($this->hasRole('global')) {
            return EmpresaContable::all();
        }

        $ids = $this->roles()
            ->where('name', 'like', 'empresa-contable-%')
            ->pluck('name')
            ->map(fn ($nombre) => (int) str_replace('empresa-contable-', '', $nombre));

        return EmpresaContable::whereIn('id', $ids)->get();
    }

    /**
     * Si puede operar por la API en esa empresa contable. Son dos cosas distintas y
     * hacen falta las dos: el ROL, que es quién es hoy este usuario y se le puede
     * quitar, y la HABILIDAD del token con el que llama, que es la empresa que eligió
     * al crearlo. Solo con la habilidad, quitarle el acceso no caducaría sus tokens
     * viejos; solo con el rol, un token filtrado abriría todas sus empresas.
     */
    public function puedeOperarEnEmpresaContable(int $empresaContableId): bool
    {
        if (! $this->empresasContablesAccesibles()->contains('id', $empresaContableId)) {
            return false;
        }

        return $this->tokenCan(EmpresaContable::habilidadTokenPara($empresaContableId));
    }

    /**
     * Lo anterior y además que el token pueda escribir. Todo lo que no es un GET pasa
     * por aquí: un token de solo lectura consulta la contabilidad de su empresa, pero
     * no le mete un asiento ni da de alta nada.
     */
    public function puedeEscribirEnEmpresaContable(int $empresaContableId): bool
    {
        return $this->puedeOperarEnEmpresaContable($empresaContableId)
            && $this->tokenCan(HabilidadToken::ESCRIBIR);
    }

}
