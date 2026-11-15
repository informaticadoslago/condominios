<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="2xl">
    <x-slot name="title">
        {{ __('Importar notas') }}
    </x-slot>

    <x-slot name="content">
        @if (! $revisado)
            <x-label for="nt-importar-texto" :value="__('Pega aquí el CSV o el listado con las notas')" />
            <textarea id="nt-importar-texto" wire:model="texto" rows="14"
                class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"></textarea>
            <x-input-error for="texto" class="mt-2" />
        @else
            @if ($columnas && $columna === null)
                <p class="mb-3 text-sm">{{ __('Pulsa la cabecera de la columna que tiene las notas de esta prueba.') }}</p>
            @else
                <p class="mb-3 text-sm">
                    {{ __('Se han leído :total notas; se importarán :validos.', ['total' => count($filas), 'validos' => $validos]) }}
                    @if ($validos < count($filas))
                        <span class="text-red-600">{{ __('Las marcadas en rojo no se importan.') }}</span>
                    @endif
                </p>
            @endif
            <div class="max-h-96 overflow-auto">
                <table class="w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="py-2 px-3">{{ __('Alumno') }}</th>
                            @if ($columnas)
                                {{-- Listado copiado de la pantalla: todas sus columnas con notas; la de la prueba se elige pulsando su cabecera. --}}
                                @foreach ($columnas as $numero => $nombre)
                                    <th @class(['py-2 px-3 align-bottom', 'bg-indigo-100 dark:bg-indigo-900' => $columna === $numero])>
                                        <button type="button" id="btn-columna-notas-{{ $numero }}" wire:click="$set('columna', {{ $numero }})"
                                            class="text-left font-medium underline" title="{{ __('Tomar las notas de esta columna') }}">{{ $nombre }}</button>
                                    </th>
                                @endforeach
                            @else
                                <th class="py-2 px-3">{{ __('Calificación') }}</th>
                            @endif
                            @if (! $columnas || $columna !== null)
                                <th class="py-2 px-3">{{ __('Nota sobre') }} {{ \App\Models\Prueba::numero($prueba->puntuacion_maxima) }}</th>
                                <th class="py-2 px-3">{{ __('Nota actual') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($filas as $i => $fila)
                            <tr wire:key="importar-nota-{{ $columna }}-{{ $i }}" @class(['text-red-600' => $fila['error']])>
                                <td class="py-2 px-3">
                                    {{ $fila['alumno'] }}
                                    @if ($fila['error'])
                                        <div class="text-xs">{{ $fila['error'] }}</div>
                                    @endif
                                </td>
                                @if ($columnas)
                                    @foreach ($columnas as $numero => $nombre)
                                        <td @class(['py-2 px-3', 'bg-indigo-100 dark:bg-indigo-900 font-semibold' => $columna === $numero])>{{ \App\Models\Prueba::numero($fila['valores'][$numero] ?? null) }}</td>
                                    @endforeach
                                @else
                                    <td class="py-2 px-3">{{ \App\Models\Prueba::numero($fila['nota']) }}</td>
                                @endif
                                @if (! $columnas || $columna !== null)
                                    <td class="py-2 px-3">{{ \App\Models\Prueba::numero($fila['nota_prueba']) }}</td>
                                    <td class="py-2 px-3">{{ \App\Models\Prueba::numero($fila['nota_actual']) }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        @if (! $revisado)
            <button type="button" class="btn btn-guardar px-2" id="btn-revisar-importar-notas" wire:click="revisar"
                title="{{ __('Revisar') }}">{{ __('Revisar') }}</button>
        @else
            <x-dosl.boton-cerrar accion="volver">{{ __('Atrás') }}</x-dosl.boton-cerrar>
            @if ($validos)
                <button type="button" class="btn btn-guardar px-2" id="btn-confirmar-importar-notas" wire:click="importar"
                    title="{{ __('Importar') }}">{{ __('Importar') }}</button>
            @endif
        @endif
    </x-slot>
</x-dosl.dialog-modal>
