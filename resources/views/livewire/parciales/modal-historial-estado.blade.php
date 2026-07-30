<x-dosl.dialog-modal wire:model.live="historialAbierto" maxWidth="lg">
    <x-slot name="title">
        {{ __('Historial de estados') }}
    </x-slot>

    <x-slot name="content">
        @if ($historialTitulo)
            <div class="mb-3 text-base font-medium mayusculas">{{ $historialTitulo }}</div>
        @endif

        <ul class="divide-y">
            @foreach ($historialLineas as $linea)
                <li class="flex items-center justify-between py-2 {{ $linea['actual'] ? 'font-semibold' : '' }}">
                    <span>
                        {{ $linea['descripcion'] }}
                        @if ($linea['actual'])
                            <span class="ml-2 text-xs text-green-600">({{ __('actual') }})</span>
                        @endif
                    </span>
                    <span class="text-gray-500">{{ $linea['fecha'] }}</span>
                </li>
            @endforeach
        </ul>
    </x-slot>
</x-dosl.dialog-modal>
