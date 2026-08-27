<?php

namespace App\Models;

use App\Models\Traits\ConHistorialEstado;
use Illuminate\Database\Eloquent\Model;

/**
 * Cuentas maestra de las plantillas de arranque del plan de cuentas: nunca son las cuentas
 * reales de una empresa contable (eso sigue siendo CuentaContable), solo el catálogo del
 * que CuentaContable::copiarPlanGlobalA() copia al crear una empresa nueva.
 *
 * Tabla propia, no "cuenta_contables con empresa_contable_id nulo": así un borrado o un
 * update masivo aquí no puede rozar ni por accidente una cuenta real de una empresa.
 */
class CuentaContablePlantilla extends Model
{
    use ConHistorialEstado;

    const
    ESTADO_ACTIVO = 1,
    ESTADO_BAJA = 2;

    const
    PLANTILLA_COMUNIDAD = 'comunidad',
    PLANTILLA_SOCIEDAD = 'sociedad';

    /** Cifras de los códigos que solo agrupan: grupo, subgrupo y cuenta del PGC. */
    const CIFRAS_AGRUPACION = 3;

    protected $table = 'cuenta_contable_plantillas';

    protected $fillable = [
        'plantilla', 'tipo_cuenta_contable_id', 'cuenta_padre_id', 'codigo', 'nombre', 'estado_id',
    ];

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
        return $this->belongsTo(self::class, 'cuenta_padre_id');
    }

    public function subcuentas()
    {
        return $this->hasMany(self::class, 'cuenta_padre_id');
    }

    public function esAgrupacion(): bool
    {
        return strlen((string) $this->codigo) <= self::CIFRAS_AGRUPACION;
    }

    /**
     * La vista efectiva de una plantilla es ella + la común encima de la que se apoya
     * (igual que copiarPlanGlobalA): sin plantilla, solo la común. whereIn no vale para
     * mezclar null con un valor (SQL no compara NULL con IN), de ahí el whereNull +
     * orWhere explícitos.
     */
    public function scopeVistaEfectiva($query, ?string $plantilla)
    {
        return $query->where(
            fn ($q) => $q->whereNull('plantilla')->when($plantilla, fn ($q2) => $q2->orWhere('plantilla', $plantilla))
        );
    }

    /**
     * De qué cuenta cuelga $codigo: el ancestro más cercano que exista de verdad, buscando
     * en la vista efectiva de $plantilla (ella + la común).
     */
    public static function padreDe(string $codigo, ?string $plantilla): ?self
    {
        foreach (CuentaContable::codigosAncestros($codigo) as $ancestro) {
            $padre = self::vistaEfectiva($plantilla)->where('codigo', $ancestro)->first();

            if ($padre) {
                return $padre;
            }
        }

        return null;
    }

    /**
     * Recoloca el plan entero de una plantilla: cada cuenta vuelve a colgar del ancestro
     * más cercano que exista en su vista efectiva (ella + la común). Llamarlo tras sembrar
     * o editar la plantilla; si se llama para null (la común), solo recoloca la común.
     *
     * @return int cuentas que han cambiado de padre
     */
    public static function recolgarPlan(?string $plantilla): int
    {
        $cuentas     = self::vistaEfectiva($plantilla)->get(['id', 'codigo', 'cuenta_padre_id', 'plantilla']);
        $idPorCodigo = $cuentas->pluck('id', 'codigo');
        $movidas     = 0;

        foreach ($cuentas as $cuenta) {
            // La común no cuelga de nada de una plantilla concreta: solo se recoloca a
            // sí misma cuando $plantilla es null.
            if ($cuenta->plantilla === null && $plantilla !== null) {
                continue;
            }

            $padreId = null;

            foreach (CuentaContable::codigosAncestros($cuenta->codigo) as $ancestro) {
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
     * Borra TODAS las plantillas de golpe, para volver a sembrar desde cero. Seguro: esta
     * tabla solo lleva plantillas, nunca cuentas reales de una empresa.
     *
     * cuenta_padre_id es una FK autorreferenciada con onDelete('restrict'): primero se
     * sueltan todos los cuenta_padre_id (rompe la cadena) y luego se borra, para que un
     * DELETE en bloque no choque contra su propia jerarquía.
     */
    public static function borrarTodo(): void
    {
        self::query()->update(['cuenta_padre_id' => null]);
        self::query()->delete();
    }
}
