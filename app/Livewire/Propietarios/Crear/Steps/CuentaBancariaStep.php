<?php

namespace App\Livewire\Propietarios\Crear\Steps;

use App\Livewire\Propietarios\Crear\CrearPropietarioStep;
use App\Models\Borrador;
use App\Models\Estado;
use App\Models\EntidadBancaria;
use App\Models\Pais;
use App\Models\PersonaComunidad;
use App\Models\Propietario;
use App\Models\TipoContacto;
use App\Models\TipoDireccion;
use App\Rules\IsIBANRule;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;

class CuentaBancariaStep extends CrearPropietarioStep
{
    #[Locked]
    public ?int $propietarioId = null;

    public ?string $iban = null;
    public ?int $entidad_bancaria_id = null;
    public ?string $entidad_bancaria_texto = '';
    public ?string $alias = null;

    public bool $cargado = false;

    /** Resultados del buscador de entidad bancaria (ver x-dosl.input-autocomplete). */
    public array $resultadosEntidadesBancarias = [];

    public function stepInfo(): array
    {
        return ['label' => __('Cuenta bancaria')];
    }

    public function mount()
    {
        if ($this->cargado) {
            return;
        }
        $this->cargado = true;

        if ($this->propietarioId && ! $this->iban) {
            $cuenta = Propietario::find($this->propietarioId)?->cuentasBancarias->first();

            if ($cuenta) {
                $this->iban                = $cuenta->iban;
                $this->entidad_bancaria_id = $cuenta->entidad_bancaria_id;
                $this->alias               = $cuenta->alias;
                $this->entidad_bancaria_texto = $cuenta->entidadBancaria
                    ? $cuenta->entidadBancaria->codigo.' - '.$cuenta->entidadBancaria->descripcion
                    : '';
            }
        }
    }

    /** Buscador del autocompletado de entidad bancaria: por código o nombre. */
    public function buscarEntidadesBancarias(string $q, int $limit = 8): void
    {
        $q = trim($q);

        $this->resultadosEntidadesBancarias = $q === '' ? [] : EntidadBancaria::activo()
            ->where(function ($query) use ($q) {
                $query->where('codigo', 'like', "%{$q}%")->orWhere('descripcion', 'like', "%{$q}%");
            })
            ->orderBy('descripcion')
            ->limit($limit)
            ->get()
            ->map(fn ($e) => ['valor' => $e->id, 'etiqueta' => "{$e->codigo} - {$e->descripcion}"])
            ->all();
    }

    protected function rules()
    {
        return [
            'iban'                 => ['nullable', 'string', new IsIBANRule()],
            'entidad_bancaria_id'  => ['nullable', 'exists:entidades_bancarias,id', 'required_with:iban'],
            'alias'                => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function validationAttributes()
    {
        return [
            'iban'                => __('IBAN'),
            'entidad_bancaria_id' => __('entidad bancaria'),
        ];
    }

    private function borradorActual(): ?Borrador
    {
        $borradorId = session($this->claveBorrador());

        return $borradorId ? Borrador::delUsuario()->deTipo(Borrador::TIPO_PROPIETARIO)->find($borradorId) : null;
    }

    /** "Terminar": graba persona, dirección, contactos y cuenta bancaria de golpe. */
    public function terminar()
    {
        $cuenta = $this->validate();

        $borrador = $this->borradorActual();
        if (! $borrador || empty($borrador->payload['datos'])) {
            $this->addError('iban', __('Faltan los datos fiscales. Vuelve al primer paso.'));

            return;
        }

        $datos      = $borrador->payload['datos'];
        $direccion  = $borrador->payload['direccion'] ?? [];
        $contactos  = $borrador->payload['contactos'] ?? [];

        DB::transaction(function () use ($datos, $direccion, $contactos, $cuenta) {
            if ($this->propietarioId) {
                $propietario = Propietario::findOrFail($this->propietarioId);
                $persona     = $propietario->persona;
                $persona->update($datos['datosPersona']);
            } elseif (! empty($datos['personaExistente']) && ! empty($datos['persona_comunidad_id'])) {
                $persona     = PersonaComunidad::findOrFail($datos['persona_comunidad_id']);
                $propietario = Propietario::firstOrCreate(['persona_comunidad_id' => $persona->id]);
            } else {
                $persona     = PersonaComunidad::create($datos['datosPersona']);
                $propietario = Propietario::firstOrCreate(['persona_comunidad_id' => $persona->id]);
            }

            if (array_filter($direccion)) {
                $persona->direcciones()->updateOrCreate(
                    ['tipo_direccion_id' => TipoDireccion::idDomicilio()],
                    $direccion + ['pais_id' => Pais::ESPAÑA, 'estado_id' => Estado::ESTADO_ACTIVO],
                );
            }

            if (! empty($contactos['telefono'])) {
                $persona->contactos()->updateOrCreate(
                    ['tipo_contacto_id' => TipoContacto::MOVIL],
                    ['descripcion' => __('Teléfono'), 'valor' => $contactos['telefono'], 'estado_id' => Estado::ESTADO_ACTIVO],
                );
            }

            if (! empty($contactos['email'])) {
                $persona->contactos()->updateOrCreate(
                    ['tipo_contacto_id' => TipoContacto::EMAIL],
                    ['descripcion' => __('Email'), 'valor' => $contactos['email'], 'estado_id' => Estado::ESTADO_ACTIVO],
                );
            }

            if (! empty($cuenta['iban'])) {
                $cuentaExistente = $propietario->cuentasBancarias->first();

                if ($cuentaExistente) {
                    $cuentaExistente->update($cuenta);
                } else {
                    $propietario->cuentasBancarias()->create($cuenta);
                }
            }

            $this->propietarioId = $propietario->id;
        });

        $borrador->delete();
        session()->forget($this->claveBorrador());

        if ($this->embebido) {
            $propietario = Propietario::with('persona')->find($this->propietarioId);
            $this->dispatch(
                'propietario-creado',
                id: $propietario->id,
                nombre: ($propietario->persona->documento_identificativo ?? '').' — '.$propietario->persona->nombreCompleto,
            );
        }

        $this->salir();
    }

    public function render()
    {
        return view('livewire.propietarios.crear.steps.cuenta-bancaria-step');
    }
}
