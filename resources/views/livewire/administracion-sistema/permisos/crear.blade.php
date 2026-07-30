<x-dosl.dialog-modal wire:model.live="abrirCrear" class="backdrop-blur" maxwidth="7xl">
    <x-slot name="title">
        {{ __('Crear permiso') }}
    </x-slot>

    <x-slot name="content">        
        {{-- @if (count($errors) > 0)
            <div class="alert alert-danger">
                <strong>@lang('Whoops!')</strong> @lang(__('messages.There were some problems with your input'))<br><br>
                <ul>
                    @foreach ($errors->all() as $error)
                    {{ var_dump($error) }}
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif --}}


        <div class="flex mb-1 w-3xl">
            <div class="mt-4 w-full">
                <x-label for="input-crear-permiso" :value="__('Permiso')" />
                <x-input id="input-crear-permiso" class="block mt-1 w-full" type="text" name="nombre"
                    wire:model="nombre" autofocus />
                <x-input-error for="nombre" class="mt-2" />
                <div wire:dirty wire:target="nombre">Sin guardar</div>
            </div>
        </div>

    </x-slot>
    <x-slot name="footer">
        <button wire:click="close" class="btn btn-cancelar px-2 mr-4" tabindex="-1">
            {{ __('Cerrar') }}
        </button>
        <button type="button" class="btn btn-guardar px-2" id="btn-guardar-permiso" wire:click="guardar"
            title="Guardar" @if(!$activoGuardar) disabled @endif >{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>
