<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="2xl">
    <x-slot name="title">
        {{ __('Importar alumnos') }}
    </x-slot>

    <x-slot name="content">
        @if (! $revisado)
            <x-label for="al-importar-texto" :value="__('Pega aquí el listado de alumnos')" />
            <textarea id="al-importar-texto" wire:model="texto" rows="14"
                class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"></textarea>
            <x-input-error for="texto" class="mt-2" />
        @else
            <p class="mb-3 text-sm">
                {{ __('Se han leído :total alumnos; se importarán :validos.', ['total' => count($filas), 'validos' => $validos]) }}
                @if ($validos < count($filas))
                    <span class="text-red-600">{{ __('Los marcados en rojo no se importan.') }}</span>
                @endif
            </p>
            <div class="max-h-96 overflow-y-auto">
                <table class="w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="py-2 px-3">{{ __('Primer apellido') }}</th>
                            <th class="py-2 px-3">{{ __('Segundo apellido') }}</th>
                            <th class="py-2 px-3">{{ __('Nombre') }}</th>
                            <th class="py-2 px-3">{{ __('Fecha de nacimiento') }}</th>
                            <th class="py-2 px-3">{{ __('Grupo') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($filas as $i => $fila)
                            <tr wire:key="importar-{{ $i }}" @class(['text-red-600' => $fila['error']])>
                                <td class="py-2 px-3">{{ $fila['apellido1'] }}</td>
                                <td class="py-2 px-3">{{ $fila['apellido2'] }}</td>
                                <td class="py-2 px-3">{{ $fila['nombre'] }}</td>
                                <td class="py-2 px-3">
                                    {{ $fila['fecha_nacimiento'] ? \Illuminate\Support\Carbon::parse($fila['fecha_nacimiento'])->format('d/m/Y') : '' }}
                                </td>
                                <td class="py-2 px-3">
                                    {{ $fila['grupo'] }}
                                    @if ($fila['error'])
                                        <div class="text-xs">{{ $fila['error'] }}</div>
                                    @endif
                                </td>
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
            <button type="button" class="btn btn-guardar px-2" id="btn-revisar-importar-alumnos" wire:click="revisar"
                title="{{ __('Revisar') }}">{{ __('Revisar') }}</button>
        @else
            <x-dosl.boton-cerrar accion="volver">{{ __('Atrás') }}</x-dosl.boton-cerrar>
            @if ($validos)
                <button type="button" class="btn btn-guardar px-2" id="btn-confirmar-importar-alumnos" wire:click="importar"
                    title="{{ __('Importar') }}">{{ __('Importar') }}</button>
            @endif
        @endif
    </x-slot>
</x-dosl.dialog-modal>
