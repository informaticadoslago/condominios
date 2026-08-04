<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="2xl">
    <x-slot name="title">
        {{ $formulario->comunidad?->exists ? __('Modificar comunidad') : __('Nueva comunidad') }}
    </x-slot>

    <x-slot name="content">
        <div x-data="{ pestaña: 'generales' }">
            <div class="flex items-center gap-4 text-sm border-b mb-4">
                <button type="button" @click="pestaña = 'generales'"
                    class="px-2 py-2 font-semibold border-b-2"
                    x-bind:class="pestaña === 'generales' ? 'border-indigo-600 text-indigo-700 dark:text-indigo-300' : 'border-transparent text-gray-500'">
                    {{ __('Datos generales') }}
                </button>
                <button type="button" @click="pestaña = 'financieros'"
                    class="px-2 py-2 font-semibold border-b-2"
                    x-bind:class="pestaña === 'financieros' ? 'border-indigo-600 text-indigo-700 dark:text-indigo-300' : 'border-transparent text-gray-500'">
                    {{ __('Datos financieros') }}
                </button>
            </div>

            <div x-show="pestaña === 'generales'">
                <div class="mt-1">
                    <x-label for="com-nombre" :value="__('Nombre')" />
                    <x-input id="com-nombre" class="block mt-1 w-full mayusculas" type="text" wire:model="formulario.nombre"
                        forzar-may autofocus />
                    <x-input-error for="formulario.nombre" class="mt-2" />
                </div>
                <div class="mt-3">
                    <x-label for="com-cif" :value="__('CIF')" />
                    <x-input id="com-cif" class="block mt-1 w-full mayusculas" type="text" wire:model="formulario.cif"
                        forzar-may />
                    <x-input-error for="formulario.cif" class="mt-2" />
                </div>
            </div>

            <div x-show="pestaña === 'financieros'" x-cloak>
                <p class="text-sm text-gray-500 mb-3">{{ __('Cuenta bancaria y datos de acreedor que pide el banco para generar la remesa de recibos.') }}</p>

                <div class="flex w-full gap-4">
                    <div class="w-2/3">
                        <x-label for="com-iban" :value="__('IBAN')" />
                        <x-input id="com-iban" class="block mt-1 w-full mayusculas" type="text"
                            wire:model="formulario.iban" />
                        <x-input-error for="formulario.iban" class="mt-2" />
                    </div>
                    <div class="w-1/3">
                        <x-label :value="__('Entidad bancaria')" />
                        <div class="mt-1">
                            <x-dosl.input-autocomplete
                                wire:model="formulario.entidad_bancaria_texto"
                                source="buscarEntidadesBancarias"
                                items="resultadosEntidadesBancarias"
                                valorCampo="valor"
                                etiquetaCampo="etiqueta"
                                valorModel="formulario.entidad_bancaria_id"
                                placeholder="{{ __('Código o nombre...') }}" />
                        </div>
                        <x-input-error for="formulario.entidad_bancaria_id" class="mt-2" />
                    </div>
                </div>

                <div class="mt-3 flex w-full gap-4">
                    <div class="w-1/5">
                        <x-label for="com-sufijo" :value="__('Sufijo')" />
                        <x-input id="com-sufijo" class="block mt-1 w-full" type="text" inputmode="numeric"
                            pattern="[0-9]{3}" maxlength="3" wire:model="formulario.sufijo" />
                        <x-input-error for="formulario.sufijo" class="mt-2" />
                    </div>
                    <div class="w-4/5">
                        <x-label for="com-acreedor" :value="__('Identificador de acreedor SEPA')" />
                        <x-input id="com-acreedor" class="block mt-1 w-full mayusculas" type="text"
                            wire:model="formulario.identificador_acreedor_sepa" placeholder="ES00000A00000000" />
                        <x-input-error for="formulario.identificador_acreedor_sepa" class="mt-2" />
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>
