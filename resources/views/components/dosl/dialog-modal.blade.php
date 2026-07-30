@props(['id' => null, 'maxWidth' => null, 'fullscreen' => false])

<x-dosl.modal :id="$id" :maxWidth="$maxWidth" :draggable="true" :fullscreen="$fullscreen" {{ $attributes }}>
    <div class="flex flex-col {{ $fullscreen ? 'min-h-full' : '' }}">
        <div class="px-6 py-4 {{ $fullscreen ? 'flex-1' : '' }}">
            <div class="text-2xl font-medium text-gray-900 dark:text-gray-100 cursor-move select-none"
                 @mousedown="startDrag($event)">
                {{ $title }}
            </div>

            @isset($subtitulo)
                <div class="text-sm font-normal text-gray-900 dark:text-gray-100">
                    {{ $subtitulo }}
                </div>
            @endisset

            <div class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                {{ $content }}
            </div>
        </div>

        @isset($footer)
            <div class="flex flex-row justify-end px-6 py-4 bg-gray-100 dark:bg-gray-800 text-end">
                {{ $footer }}
            </div>
        @endisset
    </div>
</x-dosl.modal>
