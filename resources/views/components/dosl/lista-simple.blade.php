@props(['botonera' => null, 'footer' => null, 'justifycenter' => true, 'bordercolor'=>'bg-black'])

<div class="bg-gray-100 dark:bg-white overflow-hidden shadow-sm sm:rounded-lg">
    <div class="{{ $bordercolor }} p-1">
        <div >
            <div class="flex w-full justify-between bg-gray-500 dark:bg-yellow-50">
                <h5 class="py-1 ml-2 text-white font-bold dark:text-black align-middle">
                    {{ $title }}
                </h5>
                @isset($subtitulo)
                    <span>{{ $subtitulo }}</span>
                @endisset
                <div class="">
                    {{ $botonera }}
                </div>
            </div>
            <div class="bg-white text-black">
            {{ $content }}
            </div>
        </div>
        <div class="flex flex-row justify-center px-6 py-4 bg-gray-100 dark:bg-gray-800 text-end">
            {{ $footer }}
        </div>

    </div>
</div>
