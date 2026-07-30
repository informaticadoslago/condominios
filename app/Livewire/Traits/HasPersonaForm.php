<?php

namespace App\Livewire\Traits;

trait HasPersonaForm
{
    public function updatedTipoDocumentoId($value)
    {
        $this->es_tipo_documento_cif = TipoDocumentoIdentificativo::isTipoDocumento($value, TipoDocumentoIdentificativo::TIPO_JURIDICA);
    }

    public function updatedDocumentoIdentificativo($value)
    {
        $this->activoGuardar = true;
        $persona = Persona::where('documento_identificativo', $value)->first();
        if ($persona) {
            if ($persona->es_fisica()) {
                $this->nombre = $persona->nombre;
                $this->apellido1 = $persona->apellido1;
                $this->apellido2 = $persona->apellido2;
            } else {
                $this->razon_social = $persona->razon_social;
                $this->nombre_comercial = $persona->nombre_comercial;
            }
            $this->persona = $persona;
            // if ($persona->es_cliente()) {
            //     $clienteId = $this->persona->cliente->id;
            //     $this->activoGuardar = false;
            //     $this->dispatch('swalConfirmEditCliente', [
            //         'icon'               => "error",
            //         'title'              => __('Verificacion persona'),
            //         'text'               => __('Ya es cliente'),
            //         'footer'             => __('Si desea modificar los datos pulse \'Editar\''),
            //         'showCancelButton'   => true,
            //         'confirmButtonColor' => "#3085d6",
            //         'cancelButtonColor'  => "#d33",
            //         'confirmButtonText'  => __('Editar'),
            //         'cancelButtonText'   => __('Volver'),
            //         'id'                 => $persona->cliente->id,
            //     ]);

            // }
        }

    }


}
