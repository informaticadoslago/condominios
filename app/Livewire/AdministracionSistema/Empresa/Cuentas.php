<?php
namespace App\Livewire\AdministracionSistema\Empresa;

use App\Livewire\Forms\CuentaAcreedorForm;
use App\Models\EmpresaAcreedor;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Cuentas extends Component
{
    public int $empresaId;
    public bool $abrirCuenta = false;

    public CuentaAcreedorForm $formulario;

    public function mount(int $empresaId)
    {
        $this->empresaId = $empresaId;
    }

    #[Computed]
    public function cuentas()
    {
        return EmpresaAcreedor::where('empresa_id', $this->empresaId)
            ->activa()
            ->orderBy('id')
            ->get();
    }

    public function nuevaCuenta()
    {
        $this->formulario->resetForm($this->empresaId);
        $this->abrirCuenta = true;
    }

    public function editarCuenta(int $cuentaId)
    {
        $cuenta = EmpresaAcreedor::where('empresa_id', $this->empresaId)->find($cuentaId);
        if (! $cuenta) {
            return;
        }
        $this->formulario->setCuenta($cuenta);
        $this->abrirCuenta = true;
    }

    public function guardarCuenta()
    {
        $this->formulario->validate();

        if ($this->formulario->cuenta_id) {
            $this->formulario->update();
        } else {
            $this->formulario->store();
        }

        unset($this->cuentas); // refresca el computed
        $this->cerrar();
    }

    public function borrarCuenta(int $cuentaId)
    {
        // Mejora L12: en vez del borrado físico de L9, damos de baja por estado
        // (evita romper las FK de facturas_remesas que cuelgan de la cuenta).
        $cuenta = EmpresaAcreedor::where('empresa_id', $this->empresaId)->find($cuentaId);
        if ($cuenta) {
            $cuenta->update(['estado' => EmpresaAcreedor::ESTADO_BAJA]);
            unset($this->cuentas);
        }
    }

    public function cerrar()
    {
        $this->abrirCuenta = false;
        $this->formulario->resetForm($this->empresaId);
    }

    public function render()
    {
        return view('livewire.administracion-sistema.empresa.cuentas');
    }
}
