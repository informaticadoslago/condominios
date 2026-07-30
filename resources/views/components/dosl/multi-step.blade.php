<div class="px-6 py-4">
    {{-- Cabecera --}}
    @isset($header)
        <div class="text-2xl font-medium text-gray-900 dark:text-gray-100 cursor-move select-none">
            {{ $header }}
        </div>
        @isset($subheader)
            <div class="text-sm font-normal text-gray-900 dark:text-gray-100">
                {{ $subheader }}
            </div>
        @endisset
    @endisset
    <form class="space-y-6" wire:submit="submit">
        {{-- Cuerpo --}}
        <div class="mt-4 text-sm text-gray-600 dark:text-gray-400">
            {{ $slot }}

            {{-- Pie --}}
            @isset($footer)
                <div class="px-6 py-4 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    {{ $footer }}
                </div>
            @endisset

        </div>

    </form>
</div>
