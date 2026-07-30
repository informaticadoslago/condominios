<x-dosl.dialog-modal wire:model.live="abrirImpersonar" class="backdrop-blur" maxWidth="lg">
    <x-slot name="title">
        {{ __('Cambiar de identidad') }}
    </x-slot>

    <x-slot name="subtitulo">
        {{ __('Al continuar se pedirá tu contraseña.') }}
    </x-slot>

    <x-slot name="content">
        <div class="mt-2">
            <x-label for="select-impersonar-usuario" :value="__('Usuario')" />
            <x-select id="select-impersonar-usuario" class="block mt-1 w-full mayusculas" name="usuario"
                wire:model="usuarioId">
                <option value="">{{ __('--') }}</option>
                @foreach ($usuarios as $usuario)
                    <option value="{{ $usuario->id }}">{{ $usuario->nombreCompleto }} ({{ $usuario->login }})</option>
                @endforeach
            </x-select>
            <x-input-error for="usuarioId" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar accion="close">{{ __('Cancelar') }}</x-dosl.boton-cerrar>
        <button type="button" class="btn btn-guardar px-2" id="btn-impersonar" wire:click="impersonar"
            title="{{ __('Impersonar') }}">{{ __('Impersonar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>
