<x-dosl.dialog-modal wire:model.live="abrirEditar" class="backdrop-blur" maxWidth="4xl">
    <x-slot name="title">
        {{ __('Modificar usuario') }}
    </x-slot>

    <x-slot name="content">
        <x-dosl.datos-persona :persona="$usuario?->persona" :generos="$generos" />

        <hr class="my-4" />

        {{-- 2) Datos de acceso --}}
        <strong>{{ __('Datos de acceso') }}:</strong>
        <div class="flex -mx-2">
            <div class="mt-2 px-2 flex-1">
                <x-label for="input-editar-login" :value="__('Login')" />
                <x-input id="input-editar-login" class="block mt-1 w-full" type="text"
                    name="login" wire:model="login" autocomplete="off" />
                <x-input-error for="login" class="mt-2" />
            </div>

            <div class="mt-2 px-2 flex-1">
                <x-label :value="__('Estado')" />
                <x-input class="block mt-1 w-full bg-gray-100 dark:bg-gray-700" type="text"
                    :value="$usuario?->estado?->descripcion" readonly tabindex="-1" />
            </div>
        </div>

        <div class="flex -mx-2">
            <div class="mt-2 px-2 w-1/2">
                <x-label for="input-editar-email" :value="__('Email')" />
                <x-input id="input-editar-email" class="block mt-1 w-full" type="email"
                    name="email" wire:model="email" autocomplete="off" />
                <x-input-error for="email" class="mt-2" />
            </div>
        </div>

        <div class="mt-6 px-2">
            <strong>{{ __('Roles') }}:</strong>
            <div class="flex mt-1">
                <table class="table-auto">
                    <tbody>
                        @foreach ($rolesDisponibles->chunk(3) as $chunk)
                            <tr>
                                @foreach ($chunk as $rol)
                                    <td class="m-2 px-2">
                                        <label>
                                            <input type="checkbox" class="name" wire:model="roles"
                                                value="{{ $rol->name }}" />
                                            {{ $rol->name }}
                                        </label>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-input-error for="roles" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar accion="close" />
        <button type="button" class="btn btn-guardar px-2" id="btn-modificar-usuario" wire:click="guardar"
            title="Guardar" @if (!$activoGuardar) disabled @endif>{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>
