<?php

namespace App\Livewire\Traits;

use App\Models\Pais;
use App\Models\Provincia;

trait WithDireccion
{
public $paises;
    public bool $pais_is_spain = false;

    public $provincias;

    public function updatedFormularioPaisId($value): bool
    {
        return $this->pais_is_spain = ($value == Pais::ESPAÑA);
    }

    protected function setPaises()
    {
        $this->paises = Pais::activo()->ordenGrupo()->get();
    }

    protected function setProvincias(): void
    {
        $this->provincias = Provincia::ordenaPorNombre()->get();
    }

    public function updatedFormularioCodigoPostal($value){                         
        if(($this->pais_is_spain) && strlen($value)==5){                        
            $provincia = Provincia::where('codigo',substr($value, 0, 2))->first();
            if($provincia){
                $this->formulario->provincia_id = $provincia->id;
                $this->updatedFormularioProvinciaId($provincia->id);
            }
        }
    }

    public function updatedFormularioProvinciaId($value): void
    {
        $this->formulario->setMunicipios();
        $this->formulario->municipio_id = $this->formulario->municipios[0]->id;

    }

}
