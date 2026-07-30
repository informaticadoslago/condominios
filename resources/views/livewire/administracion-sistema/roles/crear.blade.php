<x-dosl.dialog-modal wire:model.live="abrirCrear" class="backdrop-blur">
    <x-slot name="title">
        {{ __('Roles') }}        
    </x-slot>

    <x-slot name="content">
        <div class=" mb-1 flex">
            <div class="mt-4">
                <x-label for="input-crear-rol" :value="__('Rol')" />
                <x-input id="input-crear-rol" class="block mt-1 w-full" type="text" name="nombre" autofocus
                    wire:model="nombre" />
                <x-input-error for="nombre" class="mt-2" />
                <div wire:dirty wire:target="nombre">Sin guardar</div>
            </div>
        </div>
        <div class="mt-4">
            <strong>{{ __('Permisos') }}:</strong>
            <br />
            <div class="flex">
                
                <table class="table-auto">
                    <tbody>
                        @foreach ($permisos->chunk(3) as $chunk)
                        <tr>
                            @foreach ($chunk as $value)
                                <td class="m-2">
                                    <label> <input type="checkbox" wire:model="permisos_rol" class="name"
                                            value="{{ $value->name }}" />
                                        {{ $value->name }}</label>
                                </td>                
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <x-input-error for="permisos_rol" class="mt-2" />
            </div>
        </div>

    </x-slot>
    <x-slot name="footer">
        <x-dosl.boton-cerrar accion="close" />
        <button type="button" class="btn btn-guardar px-2" id="btn-guardar-rol" wire:click="guardar" title="Guardar"
            @if (!$activoGuardar) disabled @endif>{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>
