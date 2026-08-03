<?php

namespace App\Services\Comunidades;

use App\Models\ApunteContable;
use App\Models\AsientoContable;
use App\Models\Comunidad;
use App\Models\ComunidadDirectivo;
use App\Models\ConceptoPresupuesto;
use App\Models\Contacto;
use App\Models\CuentaBancaria;
use App\Models\Direccion;
use App\Models\Documento;
use App\Models\EjercicioContable;
use App\Models\Empresa;
use App\Models\FormaPagoInmueble;
use App\Models\GrupoDeReparto;
use App\Models\HistorialEstado;
use App\Models\Inmueble;
use App\Models\Persona;
use App\Models\PersonaComunidad;
use App\Models\Presupuesto;
use App\Models\Propietario;
use App\Models\Proveedor;
use App\Models\Titularidad;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Borra una comunidad y absolutamente todo lo que cuelga de ella (inmuebles,
 * propietarios, proveedores, cuentas bancarias, presupuestos, contabilidad,
 * documentos/facturas adjuntas...). Es un borrado real, no hay soft deletes en el
 * proyecto: no hay marcha atrás salvo por las copias que deja 'sine_nomines' de los
 * modelos con ConCopiaAlBorrar (Documento, Contacto, Direccion).
 *
 * No hay ON DELETE CASCADE a nivel de BD para este árbol (salvo
 * facturas_proveedores -> documentos/proveedores), así que el orden de borrado lo
 * marca este servicio: de las hojas hacia la comunidad. Documento/Contacto/Direccion
 * se borran fila a fila (no con un DELETE masivo) porque su borrado físico de
 * fichero y su copia en sine_nomines solo saltan con los eventos de Eloquent.
 */
class ComunidadEliminador
{
    public function eliminar(Comunidad $comunidad): void
    {
        $inmuebleIds = $comunidad->inmuebles()->pluck('id');

        $personaComunidadIds = PersonaComunidad::where('comunidad_id', $comunidad->id)->pluck('id');
        $propietarioIds      = Propietario::whereIn('persona_comunidad_id', $personaComunidadIds)->pluck('id');
        $proveedorIds        = Proveedor::whereIn('persona_comunidad_id', $personaComunidadIds)->pluck('id');

        $presupuestoIds = Presupuesto::where('comunidad_id', $comunidad->id)->pluck('id');
        $ejercicioIds   = EjercicioContable::where('comunidad_id', $comunidad->id)->pluck('id');
        $asientoIds     = AsientoContable::whereIn('ejercicio_contable_id', $ejercicioIds)->pluck('id');

        $personaId = $comunidad->persona_id;

        // 1. Historial de estados de presupuestos/propietarios/proveedores.
        HistorialEstado::where(fn ($q) => $q->where('estadoable_type', Presupuesto::class)->whereIn('estadoable_id', $presupuestoIds))
            ->orWhere(fn ($q) => $q->where('estadoable_type', Propietario::class)->whereIn('estadoable_id', $propietarioIds))
            ->orWhere(fn ($q) => $q->where('estadoable_type', Proveedor::class)->whereIn('estadoable_id', $proveedorIds))
            ->delete();

        // 2. Conceptos de presupuesto.
        ConceptoPresupuesto::whereIn('presupuesto_id', $presupuestoIds)->delete();

        // 3-4. Contabilidad.
        ApunteContable::whereIn('asiento_contable_id', $asientoIds)->delete();
        AsientoContable::whereIn('id', $asientoIds)->delete();

        // 5. Documentos de proveedores: borrado fila a fila (dispara el evento que borra el
        // fichero físico y copia en sine_nomines). facturas_proveedores cae con ella por el
        // ON DELETE CASCADE de la FK documento_id.
        $documentoIds = Documento::where('documentable_type', Proveedor::class)
            ->whereIn('documentable_id', $proveedorIds)
            ->pluck('id');
        Documento::destroy($documentoIds);

        // 6-8. Inmuebles: histórico de titularidad, forma de pago y grupos de reparto.
        Titularidad::whereIn('inmueble_id', $inmuebleIds)->delete();
        FormaPagoInmueble::whereIn('inmueble_id', $inmuebleIds)->delete();
        DB::table('inmueble_grupo_de_reparto')->whereIn('inmueble_id', $inmuebleIds)->delete();

        // 9. Cuentas bancarias (de la comunidad, de sus propietarios y de sus proveedores).
        CuentaBancaria::where(fn ($q) => $q->where('titular_type', Comunidad::class)->where('titular_id', $comunidad->id))
            ->orWhere(fn ($q) => $q->where('titular_type', Propietario::class)->whereIn('titular_id', $propietarioIds))
            ->orWhere(fn ($q) => $q->where('titular_type', Proveedor::class)->whereIn('titular_id', $proveedorIds))
            ->orWhereIn('persona_comunidad_id', $personaComunidadIds)
            ->delete();

        // 10-11. Contactos y direcciones de las personas de la comunidad: fila a fila, mismo motivo que Documento.
        $contactoIds = Contacto::where('contactable_type', PersonaComunidad::class)
            ->whereIn('contactable_id', $personaComunidadIds)
            ->pluck('id');
        Contacto::destroy($contactoIds);

        $direccionIds = Direccion::where('direccionable_type', PersonaComunidad::class)
            ->whereIn('direccionable_id', $personaComunidadIds)
            ->pluck('id');
        Direccion::destroy($direccionIds);

        // 12-14. Directivos, propietarios, proveedores.
        ComunidadDirectivo::where('comunidad_id', $comunidad->id)->delete();
        Propietario::whereIn('persona_comunidad_id', $personaComunidadIds)->delete();
        Proveedor::whereIn('persona_comunidad_id', $personaComunidadIds)->delete();

        // 15-19. Resto de tablas colgadas directamente de la comunidad.
        EjercicioContable::where('comunidad_id', $comunidad->id)->delete();
        Inmueble::where('comunidad_id', $comunidad->id)->delete();
        Presupuesto::where('comunidad_id', $comunidad->id)->delete();
        PersonaComunidad::where('comunidad_id', $comunidad->id)->delete();
        GrupoDeReparto::where('comunidad_id', $comunidad->id)->delete();

        // 20-21. Rol 'comunidad-{id}' (Comunidad::booted() lo crea al alta; model_has_roles
        // cae solo, tiene ON DELETE CASCADE hacia roles).
        Role::where('name', $comunidad->nombreRol())->delete();

        // 22. La comunidad en sí.
        $comunidad->delete();

        // 23. Su persona (CIF/razón social) dedicada en exclusiva a esta comunidad: solo se
        // borra si, de verdad, no la usa nadie más (no debería, pero es una tabla global).
        if ($personaId
            && ! User::where('persona_id', $personaId)->exists()
            && ! Empresa::where('persona_id', $personaId)->exists()) {
            Persona::where('id', $personaId)->delete();
        }
    }
}
