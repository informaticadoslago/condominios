<div>
<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="4xl">
    <x-slot name="title">
        {{ $itemId ? __('Modificar convocatoria de junta') : __('Nueva convocatoria de junta') }}
    </x-slot>

    <x-slot name="content">
        {{-- Los cuatro datos de la convocatoria en una fila (en pantalla estrecha, uno debajo de otro). --}}
        <div class="sm:flex gap-4">
            <div class="flex-1">
                <x-label for="cj-tipo" :value="__('Tipo')" />
                <x-select id="cj-tipo" class="block mt-1 w-full py-3" wire:model="tipo_junta_id">
                    @foreach ($tipos as $id => $descripcion)
                        <option value="{{ $id }}">{{ $descripcion }}</option>
                    @endforeach
                </x-select>
                <x-input-error for="tipo_junta_id" class="mt-2" />
            </div>
            <div class="flex-1">
                <x-label for="cj-fecha" :value="__('Fecha')" />
                <x-input id="cj-fecha" class="block mt-1 w-full" type="date" wire:model="fecha" />
                <x-input-error for="fecha" class="mt-2" />
            </div>
            <div class="flex-1">
                <x-label for="cj-hora-primera" :value="__('Primera convocatoria')" />
                {{-- live.blur: al salir del campo, el servidor recoloca la segunda (ver updatingHoraPrimeraConvocatoria). --}}
                <x-input id="cj-hora-primera" class="block mt-1 w-full" type="time" wire:model.live.blur="hora_primera_convocatoria" />
                <x-input-error for="hora_primera_convocatoria" class="mt-2" />
            </div>
            <div class="flex-1">
                <x-label for="cj-hora-segunda" :value="__('Segunda convocatoria')" />
                <x-input id="cj-hora-segunda" class="block mt-1 w-full" type="time" wire:model="hora_segunda_convocatoria" />
                <x-input-error for="hora_segunda_convocatoria" class="mt-2" />
            </div>
        </div>
        <p class="text-xs text-gray-500 mt-1">
            {{ __('La segunda convocatoria va de partida :minutos minutos después de la primera.', ['minutos' => \App\Models\ConvocatoriaJunta::MINUTOS_ENTRE_CONVOCATORIAS]) }}
        </p>

        <div class="mt-4">
            <x-label :value="__('Orden del día')" />
            {{-- Se arrastra solo desde las dos rayitas (con toda la fila arrastrable no se
                 podría seleccionar texto dentro del textarea). Soltar sobre un punto lo
                 coloca encima de él, como en el selector de columnas; la franja de debajo
                 del último lo manda al final. sobre = dónde se pinta la línea azul. --}}
            <div x-data="{ sobre: null }" class="mt-1">
                @foreach ($lineas as $i => $linea)
                    <div wire:key="linea-{{ $linea['clave'] }}" data-linea
                        x-on:dragover.prevent="sobre = '{{ $linea['clave'] }}'"
                        x-on:dragleave="if (sobre === '{{ $linea['clave'] }}') sobre = null"
                        x-on:drop.prevent="sobre = null; $wire.moverLineaAntesDe($event.dataTransfer.getData('text/plain'), '{{ $linea['clave'] }}')"
                        :class="sobre === '{{ $linea['clave'] }}' ? 'border-blue-500' : 'border-transparent'"
                        class="flex items-start gap-2 py-1 border-t-2 bg-white dark:bg-gray-800">
                        <span draggable="true" class="cursor-move px-1 pt-2" title="{{ __('Arrastrar para ordenar') }}"
                            x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $linea['clave'] }}'); $event.dataTransfer.setDragImage($el.closest('[data-linea]'), 0, 0)"
                            x-on:dragend="sobre = null">
                            <i class="fa-solid fa-grip-lines text-gray-400"></i>
                        </span>
                        <span class="pt-2 w-6 shrink-0 text-right text-gray-500">{{ $i + 1 }}.</span>
                        <div class="flex-1">
                            <textarea id="cj-linea-{{ $linea['clave'] }}" wire:model="lineas.{{ $i }}.texto" rows="2"
                                class="w-full rounded-xl border border-slate-300 bg-white px-2.5 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 dark:border-slate-700 dark:bg-slate-800/50 dark:focus-visible:outline-blue-600"></textarea>
                            <x-input-error for="lineas.{{ $i }}.texto" class="mt-1" />
                        </div>
                        <button type="button" class="px-2 pt-2 text-red-600 hover:text-red-700"
                            wire:click="quitarLinea('{{ $linea['clave'] }}')" title="{{ __('Quitar') }}">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                @endforeach

                @if (count($lineas))
                    <div class="h-4 border-t-2"
                        x-on:dragover.prevent="sobre = 'final'"
                        x-on:dragleave="if (sobre === 'final') sobre = null"
                        x-on:drop.prevent="sobre = null; $wire.moverLineaAntesDe($event.dataTransfer.getData('text/plain'), null)"
                        :class="sobre === 'final' ? 'border-blue-500' : 'border-transparent'"></div>
                @endif
            </div>

            {{-- Importar queda a la derecha, justo encima de Guardar. --}}
            <div class="flex justify-between items-center mt-1">
                <x-secondary-button type="button" id="btn-anadir-linea-orden-del-dia" wire:click="anadirLinea"
                    title="{{ __('Añadir punto') }}">
                    <i class="fa-solid fa-plus mr-1"></i>{{ __('Añadir punto') }}
                </x-secondary-button>
                <x-secondary-button type="button" id="btn-importar-orden-del-dia" wire:click="abrirImportar"
                    title="{{ __('Importar') }}">
                    <i class="fa-solid fa-file-import mr-1"></i>{{ __('Importar') }}
                </x-secondary-button>
            </div>
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>

{{-- Modal secundario, hermano del principal y no dentro de él: dentro quedaría encerrado
     en su caja (el cuadro del modal lleva transform y overflow-hidden). Los puntos leídos
     se añaden al formulario en memoria; se guardan con el Guardar principal. --}}
<x-dosl.dialog-modal wire:model.live="importarAbierto" maxWidth="2xl">
    <x-slot name="title">
        {{ __('Importar orden del día') }}
    </x-slot>

    <x-slot name="content">
        <x-label for="cj-importar-texto" :value="__('Pega el orden del día. Cada punto empieza por su número (1., 2.…) o va en su propia línea.')" />
        <textarea id="cj-importar-texto" wire:model="importarTexto" rows="14"
            class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-2.5 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 dark:border-slate-700 dark:bg-slate-800/50 dark:focus-visible:outline-blue-600"></textarea>
        <x-input-error for="importarTexto" class="mt-2" />
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar accion="$set('importarAbierto', false)">{{ __('Cancelar') }}</x-dosl.boton-cerrar>
        <button type="button" class="btn btn-guardar px-2" wire:click="importarLineas"
            title="{{ __('Importar') }}">{{ __('Importar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>
</div>
