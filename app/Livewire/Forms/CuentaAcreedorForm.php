<?php
namespace App\Livewire\Forms;

use App\Models\EmpresaAcreedor;
use App\Rules\IsIBANRule;
use Livewire\Form;

class CuentaAcreedorForm extends Form
{
    public ?EmpresaAcreedor $cuenta = null;

    public int $empresa_id = 0;
    public int $cuenta_id  = 0;

    public ?string $nombrecuenta   = null;
    public ?string $nombreacreedor = null;
    public ?string $ibanacreedor   = null;
    public ?string $bicacreedor    = null;
    public ?string $idsimple       = null;
    public ?string $idcompleto     = null;
    // Valores SEPA por defecto (mismos que L9)
    public ?string $iso              = 'pain.008.001.02';
    public ?string $tipo             = 'RCUR';
    public ?string $plazo            = 'INTERMEDIO';
    public $mindiasejecucion         = 3;

    public function rules()
    {
        return [
            'nombrecuenta'     => ['required', 'string', 'max:30'],
            'nombreacreedor'   => ['required', 'string', 'max:30'],
            'ibanacreedor'     => ['nullable', new IsIBANRule()],
            'bicacreedor'      => ['nullable', 'string', 'max:191'],
            'idsimple'         => ['nullable', 'numeric', 'max:99999'],
            'idcompleto'       => ['nullable', 'string', 'max:20'],
            'iso'              => ['nullable', 'string', 'max:15'],
            'tipo'             => ['nullable', 'string', 'max:20'],
            'plazo'            => ['nullable', 'string', 'max:20'],
            'mindiasejecucion' => ['nullable', 'numeric', 'max:99'],
        ];
    }

    public function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'max'      => 'Máxima longitud de :attribute = :max',
        ];
    }

    public function setCuenta(EmpresaAcreedor $cuenta)
    {
        $this->cuenta           = $cuenta;
        $this->cuenta_id        = $cuenta->id;
        $this->empresa_id       = $cuenta->empresa_id;
        $this->nombrecuenta     = $cuenta->nombrecuenta;
        $this->nombreacreedor   = $cuenta->nombreacreedor;
        $this->ibanacreedor     = $cuenta->ibanacreedor;
        $this->bicacreedor      = $cuenta->bicacreedor;
        $this->idsimple         = $cuenta->idsimple;
        $this->idcompleto       = $cuenta->idcompleto;
        $this->iso              = $cuenta->iso;
        $this->tipo             = $cuenta->tipo;
        $this->plazo            = $cuenta->plazo;
        $this->mindiasejecucion = $cuenta->mindiasejecucion;
    }

    private function datos(): array
    {
        return [
            'nombrecuenta'     => $this->nombrecuenta,
            'nombreacreedor'   => $this->nombreacreedor,
            'ibanacreedor'     => $this->ibanacreedor,
            'bicacreedor'      => $this->bicacreedor,
            'idsimple'         => $this->idsimple,
            'idcompleto'       => $this->idcompleto,
            'iso'              => $this->iso,
            'tipo'             => $this->tipo,
            'plazo'            => $this->plazo,
            'mindiasejecucion' => $this->mindiasejecucion ?: 0,
        ];
    }

    public function store(): EmpresaAcreedor
    {
        return EmpresaAcreedor::create(array_merge($this->datos(), [
            'empresa_id' => $this->empresa_id,
            'moneda'     => 'EUR',
            'estado'     => EmpresaAcreedor::ESTADO_ACTIVA,
        ]));
    }

    public function update(): EmpresaAcreedor
    {
        $this->cuenta->update($this->datos());

        return $this->cuenta;
    }

    public function resetForm(int $empresaId)
    {
        $this->reset();
        $this->empresa_id = $empresaId;
        $this->resetValidation();
    }
}
