@props(['accion' => 'cerrar'])

{{-- Botón Cerrar de un modal. Siempre fuera del orden de tabulación (tabindex="-1"):
     tabulando se recorren los campos y se llega a Guardar, sin tropezar con Cerrar.
     El modal se cierra con Escape o con el ratón. --}}
<button type="button" wire:click="{{ $accion }}" tabindex="-1"
    {{ $attributes->merge(['class' => 'btn btn-cerrar px-2 mr-3']) }}>
    {{ $slot->isEmpty() ? __('Cerrar') : $slot }}
</button>
