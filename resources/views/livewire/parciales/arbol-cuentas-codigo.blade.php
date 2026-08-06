{{-- Celda del código en las listas de cuentas contables: sangría por nivel y
     chevron para plegar/desplegar la rama. Con la lista filtrada o buscada ($arbol
     = false) se pinta el código pelado, sin sangría ni chevron. --}}
@if ($arbol)
    <span class="inline-block align-middle" style="width: {{ $item->nivel_arbol * 1.25 }}rem"></span>
    @if ($item->subcuentas_count)
        <button type="button" wire:click="alternarRama({{ $item->id }})"
            class="inline-block w-4 mr-1 text-gray-500 hover:text-gray-800"
            title="{{ in_array($item->id, $expandido, true) ? __('Plegar') : __('Desplegar') }}">
            <i class="fa-solid fa-chevron-{{ in_array($item->id, $expandido, true) ? 'down' : 'right' }}"></i>
        </button>
    @else
        <span class="inline-block w-4 mr-1"></span>
    @endif
@endif
{{ $item->codigo }}
