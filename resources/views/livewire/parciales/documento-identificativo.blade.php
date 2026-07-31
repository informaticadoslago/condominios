<div class="flex w-full">
    <div class="mt-2 w-1/4">
        <x-label for="select-crear-documento-pais" :value="__('Pais')" />
        <x-select id="select-crear-documento-pais" class="block mt-1 w-full mayusculas" name="documento-pais"
            wire:model.live.fill="formulario.documento_pais_id">
            @foreach ($paises as $pais)
                <option value="{{ $pais->id }}">{{ $pais->nombre }}</option>
            @endforeach
        </x-select>
        <x-input-error for="formulario.documento_pais_id" class="mt-2" />
    </div>

    <div class="mt-2 ml-2 w-1/5">
        <x-label for="select-crear-tipo-doc" :value="__('Tipo documento')" />
        <x-select id="select-crear-tipo-doc" class="block mt-1 w-full mayusculas" name="tipo_documento"
            wire:model.live.fill="formulario.tipo_documento_id">
            @foreach ($formulario->tipo_documento_identificativos as $tipo_documento_identificativo)
                <option value="{{ $tipo_documento_identificativo->id }}"
                    @if($tipo_documento_identificativo->id == $formulario->tipo_documento_id) selected @endif>{{ $tipo_documento_identificativo->nombre }}</option>
            @endforeach
        </x-select>
        <x-input-error for="formulario.tipo_documento_id" class="mt-2" />
    </div>

    <div class="mt-2 ml-2 w-[55%]">
        <x-label for="input-crear-documento-identificativo" :value="__('Documento Id.')" />
        <x-input id="input-crear-documento-identificativo" class="block mt-1 w-full mayusculas" type="text"
            name="documento-identificativo" wire:model.live="formulario.documento_identificativo" autofocus />
        <x-input-error for="formulario.documento_identificativo" class="mt-2" />
    </div>
</div>
