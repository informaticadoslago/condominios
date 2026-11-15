<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="lg">
    <x-slot name="title">
        {{ $itemId ? __('Modificar alumno') : __('Nuevo alumno') }}
    </x-slot>

    <x-slot name="content">
        {{-- Alta: quien ya está en otra asignatura del horario se elige aquí y no se vuelve a teclear. --}}
        @if (! $itemId && $delHorario->isNotEmpty())
            <div class="mb-4">
                <x-label for="al-existente" :value="__('Alumno que ya está en el horario')" />
                <x-select id="al-existente" class="block mt-1 w-full py-3" wire:model.live="alumnoExistenteId">
                    <option value="">{{ __('-- Alumno nuevo --') }}</option>
                    @foreach ($delHorario as $alumno)
                        <option value="{{ $alumno->id }}">
                            {{ $alumno->nombreCompleto }} ({{ $alumno->fecha_nacimiento->format('d/m/Y') }})
                        </option>
                    @endforeach
                </x-select>
            </div>
        @endif
        @if ($itemId)
            <p class="mb-4 text-sm text-gray-500">
                {{ __('El nombre, los apellidos y la fecha de nacimiento cambian en todas las asignaturas del alumno; el grupo, solo en esta.') }}
            </p>
        @endif
        <div>
            <x-label for="al-nombre" :value="__('Nombre')" />
            <x-input id="al-nombre" class="block mt-1 w-full" type="text" wire:model="nombre" maxlength="50" autofocus />
            <x-input-error for="nombre" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-label for="al-apellido1" :value="__('Primer apellido')" />
            <x-input id="al-apellido1" class="block mt-1 w-full" type="text" wire:model="apellido1" maxlength="50" />
            <x-input-error for="apellido1" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-label for="al-apellido2" :value="__('Segundo apellido')" />
            <x-input id="al-apellido2" class="block mt-1 w-full" type="text" wire:model="apellido2" maxlength="50" />
            <x-input-error for="apellido2" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-label for="al-fecha" :value="__('Fecha de nacimiento')" />
            <x-input id="al-fecha" class="block mt-1 w-full" type="date" wire:model="fecha_nacimiento" />
            <x-input-error for="fecha_nacimiento" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-label for="al-grupo" :value="__('Grupo')" />
            <x-input id="al-grupo" class="block mt-1 w-full uppercase" type="text" wire:model="grupo" maxlength="10" placeholder="A" />
            <x-input-error for="grupo" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>
