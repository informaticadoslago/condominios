<div class="flex items-center justify-around text-sm border-b pb-3 mb-4">
    @foreach ($steps as $index => $step)
        <div @class(['px-2 py-1 tracking-wide font-semibold', 'text-gray-500' => ! $step->isCurrent()])>
            {{-- Solo los pasos YA visitados son clicables (vuelven atrás); los futuros
                 no, para no saltarse su validación. --}}
            <button type="button"
                @class([
                    'uppercase',
                    'text-lg font-bold text-indigo-700 dark:text-indigo-300' => $step->isCurrent(),
                    'text-indigo-500 hover:underline' => $step->isPrevious(),
                ])
                @if ($step->isPrevious()) wire:click="mostrarDesdeCabecera('{{ $step->stepName }}')" @endif>
                {{ $index + 1 }}. {{ $step->label }}
            </button>
        </div>
    @endforeach
</div>
