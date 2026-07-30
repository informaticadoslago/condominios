<x-dosl.dialog-modal wire:model.live="abrirModificarPersona" class="backdrop-blur" maxWidth="7xl">
    <x-slot name="title">
        {{ __('Persona') }}
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" id="editar-guardar-persona" wire:click="guardar"
            title="Guardar">{{ __('Guardar') }}</button>
    </x-slot>


    <x-slot name="content">

        <div class="mt-4 w-2/5">
            <x-label for="input-editar-fecha-alta" :value="__('Fecha de alta')" />
            <x-input-ro id="input-editar-fecha-alta" class="block mt-1 w-full" type="date" name="fecha-alta"
                wire:model="formulario.fecha_alta" />            
        </div>

        @include('livewire.parciales.documento-identificativo')
        @include('livewire.parciales.nombre-razon-social')
        
        <div class="flex w-full">
            <div class="mt-2 ml-2 w-1/5">
                <x-label for="select-editar-tipo-doc" :value="__('Genero')" />
                <x-select id="select-editar-tipo-doc" class="block mt-1 w-full mayusculas" name="genero"
                    wire:model.live.fill="formulario.genero_id">
                    @foreach ($generos as $genero)
                        <option value="{{ $genero->id }}" @if ($genero->id == $formulario->genero_id) selected @endif>
                            {{ $genero->nombre }}</option>
                    @endforeach
                </x-select>
                <x-input-error for="formulario.genero_id" class="mt-2" />
            </div>
            <div class="mt-2 ml-2 w-2/5">
                <x-label for="input-editar-fecha-nacimiento" :value="$formulario->es_tipo_documento_cif ? __('Fecha de creación') : __('Fecha de nacimiento')" />
                <x-input id="input-editar-fecha-nacimiento" class="block mt-1 w-full" type="date"
                    name="fecha-nacimiento" wire:model="formulario.fecha_nacimiento" />
                <x-input-error for="formulario.fecha_nacimiento" class="mt-2" />
            </div>
        </div>

        {{-- @include('livewire.sistema.direcciones.crear') --}}

        {{-- @if ($esSuperadmin && !$formulario->es_tipo_documento_cif)
            <div class="mt-4">
                <x-2l.toggle color="verdedosl" label="{{ __('Crear usuario Administrador') }}"
                    wire:model.live="incluirAdministrador" />
                <div class="@if (!$incluirAdministrador) hidden @else visible @endif">
                    @include('livewire.sistema.usuarios.parciales.correoylogin')
                </div>
            </div>
        @endif --}}
        <div class="mt-4">
            <div class="flex w-full max-w-full flex-col gap-1 text-slate-700 dark:text-slate-300">
                <label for="textArea" class="w-fit pl-0.5 text-sm">{{ __('Alergias') }}</label>
                <textarea id="editar-alergias-alimentos" wire:model.live="formulario.alergias_alimentos"
                    class="w-full rounded-xl border border-slate-300 bg-slate-100 px-2.5 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 disabled:cursor-not-allowed disabled:opacity-75 dark:border-slate-700 dark:bg-slate-800/50 dark:focus-visible:outline-blue-600"
                    rows="3" placeholder="Texto para alergias..."></textarea>
            </div>

            <x-input-error for="formulario.alergias_alimentos" class="mt-2" />
        </div>

        <div class="mt-4">
            <div class="flex w-full max-w-full flex-col gap-1 text-slate-700 dark:text-slate-300">
                <label for="editar-observaciones" class="w-fit pl-0.5 text-sm">{{ __('Observaciones') }}</label>
                <textarea id="editar-observaciones" wire:model.live="formulario.observaciones"
                    class="w-full rounded-xl border border-slate-300 bg-slate-100 px-2.5 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 disabled:cursor-not-allowed disabled:opacity-75 dark:border-slate-700 dark:bg-slate-800/50 dark:focus-visible:outline-blue-600"
                    rows="3" placeholder="Texto para observaciones..."></textarea>
            </div>

            <x-input-error for="formulario.observaciones" class="mt-2" />
        </div>


    </x-slot>

</x-dosl.dialog-modal>
