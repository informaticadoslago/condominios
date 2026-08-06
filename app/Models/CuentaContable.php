<?php

namespace App\Models;

use App\Models\Traits\ConHistorialEstado;
use Illuminate\Database\Eloquent\Model;

class CuentaContable extends Model
{
    use ConHistorialEstado;

    const
    ESTADO_ACTIVO = 1,
    ESTADO_BAJA = 2;

    protected $table = 'cuenta_contables';

    protected $fillable = [
        'empresa_contable_id', 'tipo_cuenta_contable_id', 'cuenta_padre_id', 'codigo', 'nombre',
        'sujeto_tipo', 'sujeto_id', 'estado_id',
    ];

    public function empresaContable()
    {
        return $this->belongsTo(EmpresaContable::class);
    }

    public function tipoCuentaContable()
    {
        return $this->belongsTo(TipoCuentaContable::class);
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }

    public function cuentaPadre()
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_padre_id');
    }

    public function subcuentas()
    {
        return $this->hasMany(CuentaContable::class, 'cuenta_padre_id');
    }

    /** Las cuentas de una empresa contable, o las maestras (sin empresa) si se pide null. */
    public function scopeDeEmpresa($query, ?int $empresaContableId)
    {
        return $empresaContableId === null
            ? $query->whereNull('empresa_contable_id')
            : $query->where('empresa_contable_id', $empresaContableId);
    }

    /** Cifras de los códigos que solo agrupan: grupo, subgrupo y cuenta del PGC. */
    const CIFRAS_AGRUPACION = 3;

    /**
     * Códigos de los que puede colgar una cuenta, del más cercano al más lejano.
     *
     * El PGC numera en tres niveles —grupo (1 cifra), subgrupo (2) y cuenta (3)— y de ahí
     * para abajo ya es cosa nuestra. Los tres niveles del PGC se guardan con su código
     * corto («7», «75», «750») y las cuentas con los 8 dígitos, porque si no chocarían:
     * la cuenta 750 y la 7500 se escribirían las dos «75000000».
     *
     *   75000000 → 750, 75, 7   (7500 y 7501 son hermanas, las dos cuelgan de 750)
     *   75000001 → 75000000, 750, 75, 7
     *   12900000 → 129, 12, 1   (sin la fila 129 se queda colgando del subgrupo 12)
     *
     * Los niveles del PGC son opcionales: si no está creado el de tres cifras, la cuenta
     * cuelga del subgrupo, y así sucesivamente (ver padreDe).
     */
    public static function codigosAncestros(string $codigo): array
    {
        $codigo    = substr(preg_replace('/\D/', '', $codigo), 0, 8);
        $ancestros = [];

        if (strlen($codigo) > self::CIFRAS_AGRUPACION) {
            $codigo = str_pad($codigo, 8, '0');

            // La subcuenta, de su cuenta de 4 cifras.
            if (substr($codigo, 4) !== '0000') {
                $ancestros[] = substr($codigo, 0, 4).'0000';
            }
        }

        // Y de ahí hacia arriba, los niveles del PGC que le correspondan.
        for ($cifras = min(strlen($codigo), self::CIFRAS_AGRUPACION + 1) - 1; $cifras >= 1; $cifras--) {
            $ancestros[] = substr($codigo, 0, $cifras);
        }

        return $ancestros;
    }

    /** Grupo (1 cifra), subgrupo (2) o cuenta del PGC (3): agrupan, no se apunta en ellos. */
    public function esAgrupacion(): bool
    {
        return strlen((string) $this->codigo) <= self::CIFRAS_AGRUPACION;
    }

    /**
     * De qué cuenta cuelga $codigo: el ancestro más cercano que exista de verdad. Si
     * falta el padre (hay 43110000 pero nadie creó 43100000), cuelga del abuelo; y si no
     * hay ninguno, es raíz.
     */
    public static function padreDe(string $codigo, ?int $empresaContableId): ?self
    {
        foreach (self::codigosAncestros($codigo) as $ancestro) {
            $padre = self::deEmpresa($empresaContableId)->where('codigo', $ancestro)->first();

            if ($padre) {
                return $padre;
            }
        }

        return null;
    }

    /**
     * Recoloca el plan entero: cada cuenta vuelve a colgar del ancestro más cercano que
     * exista. Hay que llamarlo después de crear o cambiar de código una cuenta, porque
     * una cuenta intermedia creada más tarde se lleva consigo a los nietos que hasta
     * entonces colgaban del abuelo (y borrarla se los devuelve).
     *
     * @return int cuentas que han cambiado de padre
     */
    public static function recolgarPlan(?int $empresaContableId): int
    {
        $cuentas     = self::deEmpresa($empresaContableId)->get(['id', 'codigo', 'cuenta_padre_id']);
        $idPorCodigo = $cuentas->pluck('id', 'codigo');
        $movidas     = 0;

        foreach ($cuentas as $cuenta) {
            $padreId = null;

            foreach (self::codigosAncestros($cuenta->codigo) as $ancestro) {
                if ($idPorCodigo->has($ancestro)) {
                    $padreId = $idPorCodigo[$ancestro];
                    break;
                }
            }

            if ($cuenta->cuenta_padre_id !== $padreId) {
                self::whereKey($cuenta->id)->update(['cuenta_padre_id' => $padreId]);
                $movidas++;
            }
        }

        return $movidas;
    }

    /**
     * Copia (no enlaza) el plan de cuentas global a una empresa contable recién
     * creada: si el plan global cambia después, las cuentas ya copiadas no se
     * ven afectadas. La jerarquía se recalcula por código con codigosAncestros(),
     * igual que hacen CuentasContables\Formulario y PlanDeCuentas\Formulario.
     */
    public static function copiarPlanGlobalA(EmpresaContable $empresaContable): void
    {
        $nuevoIdPorCodigo = [];

        foreach (self::whereNull('empresa_contable_id')->orderBy('codigo')->get() as $global) {
            $padreId = null;

            // Van en orden de código, así que los ancestros ya están copiados.
            foreach (self::codigosAncestros($global->codigo) as $ancestro) {
                if (isset($nuevoIdPorCodigo[$ancestro])) {
                    $padreId = $nuevoIdPorCodigo[$ancestro];
                    break;
                }
            }

            $nueva = self::create([
                'empresa_contable_id'     => $empresaContable->id,
                'tipo_cuenta_contable_id' => $global->tipo_cuenta_contable_id,
                'cuenta_padre_id'         => $padreId,
                'codigo'                  => $global->codigo,
                'nombre'                  => $global->nombre,
                'estado_id'               => $global->estado_id,
            ]);

            $nuevoIdPorCodigo[$global->codigo] = $nueva->id;
        }
    }
}
