<?php

namespace App\Models;

use App\Models\Traits\ConDocumentos;
use App\Models\Traits\ConHistorialEstado;
use Illuminate\Database\Eloquent\Model;

/**
 * Orden de domiciliación firmada por el titular de una cuenta a favor de una comunidad.
 *
 * No se crea solo: la referencia (RUM) y la fecha de firma se escriben a mano al
 * registrar el papel firmado, porque las dos son hechos del mundo real. El RUM es
 * `P19` + NIF del titular de la cuenta + un contador que avanza; ver
 * RegistrarMandatoSepa para las comprobaciones.
 *
 * Va casado con UNA cuenta mientras está ACTIVO: no se edita si está mal tecleado, se
 * cancela (RegistrarMandatoSepa::cancelar()) y se registra uno nuevo con el contador
 * siguiente — el RUM cancelado no se recicla para otra cuenta ni se reutiliza.
 *
 * Como es de la cuenta y no del inmueble, sirve para todos los inmuebles de esa
 * comunidad que paguen con ella, sin duplicarlo.
 */
class MandatoSepa extends Model
{
    use ConDocumentos;
    use ConHistorialEstado;

    const
        ESTADO_ACTIVO = 1,
        ESTADO_CANCELADO = 2;

    protected $table = 'mandatos_sepa';

    /** Prefijo con el que empieza toda referencia de mandato. */
    public const PREFIJO = 'P19';

    protected $fillable = [
        'comunidad_id',
        'cuenta_bancaria_id',
        'referencia',
        'fecha_firma',
        'estado_id',
    ];

    protected $casts = [
        'fecha_firma' => 'date',
    ];

    public function comunidad()
    {
        return $this->belongsTo(Comunidad::class);
    }

    public function cuentaBancaria()
    {
        return $this->belongsTo(CuentaBancaria::class);
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }

    /**
     * Siempre RCUR: decisión tomada: todos los adeudos van como recurrentes. FRST y LAST
     * dan problemas en algunas entidades y desde el cambio de esquema SEPA de 2016 no
     * hace falta distinguir el primero.
     */
    public function secuencia(): string
    {
        return 'RCUR';
    }
}
