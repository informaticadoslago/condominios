<?php

namespace App\Models;

use App\Models\Traits\ConDocumentos;
use Illuminate\Database\Eloquent\Model;

/**
 * Orden de domiciliación firmada por el titular de una cuenta a favor de una comunidad.
 *
 * No se crea solo: la referencia (RUM) y la fecha de firma se escriben a mano al
 * registrar el papel firmado, porque las dos son hechos del mundo real. El RUM es
 * `P19` + NIF del titular de la cuenta + un contador que avanza; ver
 * RegistrarMandatoSepa para las comprobaciones.
 *
 * Va casado con UNA cuenta de por vida. Si esa cuenta se deja de usar, el mandato muere
 * con ella: no se recicla para otra, se firma uno nuevo con el contador siguiente.
 *
 * Como es de la cuenta y no del inmueble, sirve para todos los inmuebles de esa
 * comunidad que paguen con ella, sin duplicarlo.
 */
class MandatoSepa extends Model
{
    use ConDocumentos;

    protected $table = 'mandatos_sepa';

    /** Prefijo con el que empieza toda referencia de mandato. */
    public const PREFIJO = 'P19';

    protected $fillable = [
        'comunidad_id',
        'cuenta_bancaria_id',
        'referencia',
        'fecha_firma',
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
