<x-botonera-page>
    <x-slot name="title">
        {{ __('Copias de seguridad') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Copias de seguridad de base de datos y ficheros') }}
    </x-slot>
    <x-slot name="botonera">
        <x-ui-button type="button" class="btn btn-nuevo" wire:click="crear" wire:loading.attr="disabled"
            title="{{ __('Crear copia de seguridad') }}">
            <i class="fa-solid fa-plus"></i> {{ __('Crear copia de seguridad') }}
        </x-ui-button>
    </x-slot>

    <x-slot name="content">
        <x-dosl.tabla>
            @if ($backupRunning)
                <div wire:poll.3s></div>
            @endif

            @if ($backupRunning || count($backups))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="py-3 px-6">{{ __('Ubicación') }}</th>
                            <th class="py-3 px-6">{{ __('Fecha') }}</th>
                            <th class="py-3 px-6 text-right">{{ __('Tamaño') }}</th>
                            <th class="py-3 px-6 text-right">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @if ($backupRunning)
                            <tr>
                                <td class="px-6 py-4"><i class="fa-solid fa-spinner fa-spin"></i> {{ $backups->first()['disco'] ?? config('backup.backup.destination.disks')[0] }}</td>
                                <td class="px-6 py-4">{{ now()->format('d/m/Y H:i') }}</td>
                                <td class="px-6 py-4 text-right">---</td>
                                <td class="px-6 py-4 text-right text-zinc-400 italic">{{ __('En proceso...') }}</td>
                            </tr>
                        @endif
                        @foreach ($backups as $backup)
                            <tr wire:key="{{ $backup['disco'].'-'.$backup['fichero'] }}">
                                <td class="px-6 py-4">{{ $backup['disco'] }}</td>
                                <td class="px-6 py-4">{{ \Carbon\Carbon::createFromTimestamp($backup['fecha'])->format('d/m/Y H:i') }}</td>
                                <td class="px-6 py-4 text-right">{{ round($backup['tamano'] / 1048576, 2) }} MB</td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <a class="btn-editar inline-flex" href="{{ route('sysadmin.backups.download', ['fichero' => $backup['fichero']]) }}"
                                        title="{{ __('Descargar') }}">
                                        <i class="fa-solid fa-download"></i>
                                    </a>
                                    <x-ui-button type="button" class="ml-1 bg-red-600 hover:bg-red-700 text-white"
                                        wire:click="borrar('{{ $backup['disco'] }}', '{{ $backup['fichero'] }}')"
                                        wire:confirm="{{ __('Esto no se puede deshacer. ¿Borrar esta copia de seguridad?') }}"
                                        title="{{ __('Borrar') }}">
                                        <i class="fa-solid fa-trash"></i>
                                    </x-ui-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="py-3 px-6">{{ __('No se encontraron resultados.') }}</div>
            @endif
        </x-dosl.tabla>
    </x-slot>
</x-botonera-page>
