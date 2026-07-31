<?php
namespace App\Livewire\Traits;

use App\Models\Pais;
use App\Models\Persona;
use App\Models\TipoDocumentoIdentificativo;
use App\Rules\IsCifRule;
use App\Rules\IsNieRule;
use App\Rules\IsNifRule;

/**
 * Titular de una cuenta bancaria. Siempre es una PERSONA: puede estar ya en el sistema
 * (se busca) o no estarlo, y entonces se da de alta ahí mismo, con su documento y su
 * fecha de nacimiento. Sin titular no hay recibo.
 *
 * Lo usan los formularios que piden datos bancarios (pago del alumno, pago propio de una
 * matriculación…). La persona nueva no se crea al teclearla: se crea al grabar (lo hace
 * GrabaAlumno::resolverTitular con lo que quedó en el estado).
 */
trait ConTitularCuenta
{
    // Titular ya existente (persona elegida en el buscador).
    public ?int $titularcb_id = null;
    public string $titularBusqueda = '';
    public string $titularNombre = '';
    public array $titularResultados = [];

    // Alta inline de un titular que todavía no está en el sistema.
    public bool $titularNuevo = false;
    public bool $titularComprobado = false;
    public ?int $titular_documento_pais_id = null;
    public ?int $titular_tipo_documento_id = null;
    public ?string $titular_documento_identificativo = null;
    public ?string $titular_nombre = null;
    public ?string $titular_apellido1 = null;
    public ?string $titular_apellido2 = null;
    public ?string $titular_fecha_nacimiento = null;
    public ?int $titular_genero_id = null;

    public function updatedTitularBusqueda()
    {
        $busqueda = trim($this->titularBusqueda);

        if (mb_strlen($busqueda) < 2) {
            $this->titularResultados = [];

            return;
        }

        $this->titularResultados = Persona::visible()->where(fn ($persona) => $persona
            ->buscarNombreCompleto($busqueda)
            ->orWhere('documento_identificativo', 'like', "%{$busqueda}%"))
            ->limit(8)->get()
            ->map(fn (Persona $persona) => [
                'id' => $persona->id,
                'texto' => ($persona->documento_identificativo ?? '') . ' — ' . $persona->nombreCompleto,
            ])->all();
    }

    public function seleccionarTitular($personaId)
    {
        $persona = Persona::find($personaId);

        if (! $persona) {
            return;
        }

        $this->titularcb_id = $persona->id;
        $this->titularNombre = ($persona->documento_identificativo ?? '') . ' — ' . $persona->nombreCompleto;
        $this->titularBusqueda = '';
        $this->titularResultados = [];
        $this->titularNuevo = false;
    }

    public function quitarTitular()
    {
        $this->titularcb_id = null;
        $this->titularNombre = '';
        $this->titularBusqueda = '';
        $this->titularResultados = [];
    }

    public function nuevoTitular()
    {
        $this->quitarTitular();

        $this->titularNuevo = true;
        $this->titularComprobado = false;
        $this->titular_documento_pais_id = Pais::porDefecto();
        $this->titular_tipo_documento_id = TipoDocumentoIdentificativo::DOCUMENTO_NIF;
        $this->titular_documento_identificativo = null;
        $this->titular_nombre = null;
        $this->titular_apellido1 = null;
        $this->titular_apellido2 = null;
        $this->titular_fecha_nacimiento = null;
        $this->titular_genero_id = null;

        $this->resetErrorBag();
    }

    public function cancelarNuevoTitular()
    {
        $this->titularNuevo = false;
        $this->titularComprobado = false;

        $this->resetErrorBag();
    }

    /** Comprueba el documento: si la persona ya existe, se vincula; si no, se deja crearla. */
    public function comprobarTitular()
    {
        $rules = [
            'titular_documento_pais_id' => ['required', 'exists:paises,id'],
            'titular_tipo_documento_id' => ['required', 'exists:tipo_documento_identificativos,id'],
            'titular_documento_identificativo' => ['required', 'string', 'max:40'],
        ];

        if ($this->titular_documento_pais_id == Pais::ESPAÑA) {
            if ($this->titular_tipo_documento_id == TipoDocumentoIdentificativo::DOCUMENTO_NIF) {
                $rules['titular_documento_identificativo'][] = new IsNifRule();
            } elseif ($this->titular_tipo_documento_id == TipoDocumentoIdentificativo::DOCUMENTO_NIE) {
                $rules['titular_documento_identificativo'][] = new IsNieRule();
            } elseif ($this->titular_tipo_documento_id == TipoDocumentoIdentificativo::DOCUMENTO_CIF) {
                $rules['titular_documento_identificativo'][] = new IsCifRule();
            }
        }

        $this->validate($rules);

        $persona = Persona::visible()->where('documento_identificativo', $this->titular_documento_identificativo)->first();

        if ($persona) {
            $this->seleccionarTitular($persona->id);

            return;
        }

        $this->titularComprobado = true;
    }

    /** Lo que hay que exigir del titular cuando se cobra por recibo. */
    protected function reglasTitular(): array
    {
        if (! $this->titularNuevo) {
            return ['titularcb_id' => ['required', 'exists:personas,id']];
        }

        // Titular que no estaba en el sistema: se crea, así que hacen falta sus datos.
        return [
            'titularComprobado' => ['accepted'],
            'titular_documento_identificativo' => ['required', 'string', 'max:40'],
            'titular_nombre' => ['required', 'string', 'max:100'],
            'titular_apellido1' => ['required', 'string', 'max:100'],
            'titular_apellido2' => ['nullable', 'string', 'max:100'],
            'titular_fecha_nacimiento' => ['required', 'date', 'before_or_equal:today'],
            'titular_genero_id' => ['nullable', 'exists:tipo_generos,id'],
        ];
    }
}
