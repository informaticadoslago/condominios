@props([
    'source' => 'buscarCargos', // método Livewire del padre: buscarCargos(string $q, int $limit): array
    'limit' => 8, // nº máximo de resultados que pedirá
    'items', // nombre de la propiedad del padre con la lista (p.ej. "cargos")
    'placeholder' => null,
    'name' => null,

    // --- Modo valor/etiqueta (opcional) ---
    // Por defecto cada opción de $items es un string suelto: wire:model recibe y
    // muestra ese mismo string (comportamiento de toda la vida, sin tocar).
    // Si se indica valorCampo, cada opción es un array asociativo; wire:model sigue
    // recibiendo el texto a mostrar (etiquetaCampo), y valorModel es la propiedad
    // del padre que recibe el valor real (valorCampo) — típicamente un id.
    'valorCampo' => null,
    'etiquetaCampo' => null,
    'valorModel' => null,

    // Clave de esta instancia cuando hay varias iguales en la misma pantalla (p.ej.
    // una fila de una tabla repetida): aísla los resultados de cada fila dentro de
    // $items[$clave] en vez de compartir un único array para todas las filas.
    'clave' => null,
])

@php
    // Nombre de la propiedad enlazada con wire:model (ej.: "cargo")
    $wireModel = optional($attributes->wire('model'))->value();

    $modoValorEtiqueta = $valorCampo !== null;

    // Ruta de la propiedad de resultados: "items" a secas, o "items.clave" si hay clave.
    $rutaItems = $items.($clave !== null ? '.'.$clave : '');

    // Leer la lista del Livewire padre (p.ej. $this->cargos, o $this->cargos[$clave])
    $lista = [];
    try {
        if (isset($__livewire)) {
            $lista = (array) ($__livewire->{$items} ?? []);
            if ($clave !== null) {
                $lista = (array) ($lista[$clave] ?? []);
            }
        }
    } catch (\Throwable $e) {
        $lista = [];
    }

    $ultimoIndice = max(count($lista) - 1, 0);
@endphp

{{-- resaltado: índice de la opción marcada con el teclado (-1 = ninguna). Es
     puramente de cliente (Alpine); se resetea en cada tecleo nuevo porque la
     lista de opciones cambia. Flechas para moverse, Enter/Tab para elegir la
     resaltada — antes solo se podía elegir con el ratón/toque. --}}
<div class="relative w-full" x-data="{ resaltado: -1 }">
    <input type="text" name="{{ $name }}" placeholder="{{ $placeholder }}"
        {{ $attributes->whereStartsWith('wire:model') }}
        wire:input.debounce.200ms="{{ $source }}($event.target.value, {{ (int) $limit }}{{ $clave !== null ? ', '.\Illuminate\Support\Js::from($clave) : '' }})"
        x-on:input="resaltado = -1"
        x-on:keydown.arrow-down.prevent="resaltado = Math.min(resaltado + 1, {{ $ultimoIndice }})"
        x-on:keydown.arrow-up.prevent="resaltado = Math.max(resaltado - 1, -1)"
        x-on:keydown.enter.prevent="resaltado > -1 && $refs.opciones?.children[resaltado]?.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }))"
        x-on:keydown.tab="resaltado > -1 && $refs.opciones?.children[resaltado]?.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }))"
        wire:keydown.escape.prevent="$set('{{ $rutaItems }}', [])"
        wire:keydown.tab ="$set('{{ $rutaItems }}', [])"
        wire:blur ="$set('{{ $rutaItems }}', [])"
        autocomplete="off"
        class="w-full rounded-lg border border-gray-300 h-14 px-5 text-xl outline-none focus:ring focus:ring-indigo-200" />


    @if (!empty($lista))
        <div x-ref="opciones" class="absolute z-50 mt-1 max-h-64 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow">
            @foreach ($lista as $i => $opcion)
                <button type="button"
                    :class="resaltado === {{ $i }} ? 'bg-gray-100 dark:bg-zinc-700' : ''"
                    @if ($wireModel) {{-- Usar mousedown/touchstart para ganar a blur --}}
                        @if ($modoValorEtiqueta)
                            wire:mousedown.prevent="$set('{{ $wireModel }}', @js($opcion[$etiquetaCampo] ?? '')); @if ($valorModel) $set('{{ $valorModel }}', @js($opcion[$valorCampo] ?? null)); @endif $set('{{ $rutaItems }}', [])"
                            wire:touchstart.prevent="$set('{{ $wireModel }}', @js($opcion[$etiquetaCampo] ?? '')); @if ($valorModel) $set('{{ $valorModel }}', @js($opcion[$valorCampo] ?? null)); @endif $set('{{ $rutaItems }}', [])"
                        @else
                            wire:mousedown.prevent="$set('{{ $wireModel }}', @js($opcion)); $set('{{ $rutaItems }}', [])"
                            wire:touchstart.prevent="$set('{{ $wireModel }}', @js($opcion)); $set('{{ $rutaItems }}', [])"
                        @endif
                    @endif
                    class="block w-full px-3 py-2 text-left text-sm hover:bg-gray-100 dark:hover:bg-zinc-700"
                    role="option">{{ $modoValorEtiqueta ? ($opcion[$etiquetaCampo] ?? '') : $opcion }}</button>
            @endforeach
        </div>
    @endif
</div>
