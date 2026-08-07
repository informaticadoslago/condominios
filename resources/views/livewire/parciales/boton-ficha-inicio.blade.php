{{-- Fija esta línea como ficha de entrada en el dashboard. Requiere el trait
     ConFichaInicio en el componente. Recibe $id y $url (la de la ficha).
     :disabled y no @disabled: la directiva dentro de la etiqueta de un componente
     Blade descuadra la plantilla al compilarla. --}}
<x-button type="button" class="bg-sky-600 hover:bg-sky-700 text-white ml-1"
    wire:click="fijarEnInicio({{ $id }})"
    :disabled="$this->estaEnInicio($url)"
    title="{{ $this->estaEnInicio($url) ? __('Ya está en el inicio') : __('Ver al inicio') }}">
    <i class="fa-solid fa-house"> </i>
</x-button>
