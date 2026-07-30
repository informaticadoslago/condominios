<div class="mt-4 w-2/5">
    <x-label for="input-empresa-fecha-alta" :value="__('Fecha de alta')" />
    <x-input id="input-empresa-fecha-alta" class="block mt-1 w-full" type="date" name="fecha-alta"
        wire:model="formulario.fecha_alta" />
    <x-input-error for="formulario.fecha_alta" class="mt-2" />
</div>

@include('livewire.parciales.documento-identificativo')
@include('livewire.parciales.nombre-razon-social')

@unless ($formulario->es_tipo_documento_cif)
    <div class="flex w-full">
        <div class="mt-2 w-1/5">
            <x-label for="select-empresa-genero" :value="__('Genero')" />
            <x-select id="select-empresa-genero" class="block mt-1 w-full mayusculas" name="genero"
                wire:model.live.fill="formulario.genero_id">
                @foreach ($generos as $genero)
                    <option value="{{ $genero->id }}" @if ($genero->id == $formulario->genero_id) selected @endif>
                        {{ $genero->nombre }}</option>
                @endforeach
            </x-select>
            <x-input-error for="formulario.genero_id" class="mt-2" />
        </div>
        <div class="mt-2 ml-2 w-2/5">
            <x-label for="input-empresa-fecha-nacimiento" :value="__('Fecha de nacimiento / constitución')" />
            <x-input id="input-empresa-fecha-nacimiento" class="block mt-1 w-full" type="date"
                name="fecha-nacimiento" wire:model="formulario.fecha_nacimiento" />
            <x-input-error for="formulario.fecha_nacimiento" class="mt-2" />
        </div>
    </div>
@endunless

<div class="mt-4">
    <div class="flex w-full max-w-full flex-col gap-1 text-slate-700 dark:text-slate-300">
        <label for="ta-empresa-comentarios" class="w-fit pl-0.5 text-sm">{{ __('Comentarios') }}</label>
        <textarea id="ta-empresa-comentarios" wire:model="formulario.comentarios"
            class="w-full rounded-xl border border-slate-300 bg-white px-2.5 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 disabled:cursor-not-allowed disabled:opacity-75 dark:border-slate-700 dark:bg-slate-800/50 dark:focus-visible:outline-blue-600"
            rows="3" placeholder="Comentarios..."></textarea>
    </div>
    <x-input-error for="formulario.comentarios" class="mt-2" />
</div>
