<div>
    <div class="flex items-center justify-between mb-2">
        <h3 class="text-lg font-semibold">{{ __('Cuentas bancarias (acreedor)') }}</h3>
        <x-button type="button" class="btn btn-nuevo" wire:click="nuevaCuenta" title="{{ __('Añadir cuenta') }}">
            <i class="fa-solid fa-plus"> </i>{{ __('Nueva cuenta') }}
        </x-button>
    </div>

    <x-dosl.tabla>
        @if (count($this->cuentas))
            <table class="table-striped w-full table-auto text-sm text-left">
                <thead class="font-medium border-b">
                    <tr>
                        <th class="py-2 px-4">{{ __('Nombre cuenta') }}</th>
                        <th class="py-2 px-4">{{ __('Acreedor') }}</th>
                        <th class="py-2 px-4">{{ __('IBAN') }}</th>
                        <th class="py-2 px-4">{{ __('Accion') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($this->cuentas as $cuenta)
                        <tr wire:key="cuenta-{{ $cuenta->id }}">
                            <td class="py-2 px-4">{{ $cuenta->nombrecuenta }}</td>
                            <td class="py-2 px-4">{{ $cuenta->nombreacreedor }}</td>
                            <td class="py-2 px-4">{{ $cuenta->ibanacreedor }}</td>
                            <td class="py-2 px-4 whitespace-nowrap">
                                <x-button type="button" class="btn-editar" wire:click="editarCuenta({{ $cuenta->id }})"
                                    title="{{ __('Modificar') }}">
                                    <i class="fa-solid fa-pen"> </i>
                                </x-button>
                                <x-button type="button" class="btn btn-danger"
                                    wire:click="borrarCuenta({{ $cuenta->id }})"
                                    wire:confirm="{{ __('¿Dar de baja esta cuenta bancaria?') }}"
                                    title="{{ __('Dar de baja') }}">
                                    <i class="fa-solid fa-trash"> </i>
                                </x-button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="py-3 px-4">{{ __('No hay cuentas bancarias.') }}</div>
        @endif
    </x-dosl.tabla>

    {{-- Modal alta/edición --}}
    <x-dosl.dialog-modal wire:model.live="abrirCuenta" class="backdrop-blur" maxWidth="4xl">
        <x-slot name="title">
            {{ __('Cuenta bancaria de acreedor') }}
        </x-slot>

        <x-slot name="footer">
            <x-dosl.boton-cerrar />
            <button type="button" class="btn btn-guardar px-2" wire:click="guardarCuenta"
                title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
        </x-slot>

        <x-slot name="content">
            <div class="flex w-full gap-4">
                <div class="mt-2 w-1/2">
                    <x-label for="cuenta-nombrecuenta" :value="__('Nombre cuenta')" />
                    <x-input id="cuenta-nombrecuenta" class="block mt-1 w-full" type="text"
                        wire:model="formulario.nombrecuenta" />
                    <x-input-error for="formulario.nombrecuenta" class="mt-2" />
                </div>
                <div class="mt-2 w-1/2">
                    <x-label for="cuenta-nombreacreedor" :value="__('Nombre acreedor')" />
                    <x-input id="cuenta-nombreacreedor" class="block mt-1 w-full" type="text"
                        wire:model="formulario.nombreacreedor" />
                    <x-input-error for="formulario.nombreacreedor" class="mt-2" />
                </div>
            </div>

            <div class="flex w-full gap-4">
                <div class="mt-2 w-3/4">
                    <x-label for="cuenta-iban" :value="__('IBAN')" />
                    <x-input id="cuenta-iban" class="block mt-1 w-full mayusculas" type="text"
                        wire:model="formulario.ibanacreedor" />
                    <x-input-error for="formulario.ibanacreedor" class="mt-2" />
                </div>
                <div class="mt-2 w-1/4">
                    <x-label for="cuenta-bic" :value="__('BIC')" />
                    <x-input id="cuenta-bic" class="block mt-1 w-full mayusculas" type="text"
                        wire:model="formulario.bicacreedor" />
                    <x-input-error for="formulario.bicacreedor" class="mt-2" />
                </div>
            </div>

            <div class="flex w-full gap-4">
                <div class="mt-2 w-1/3">
                    <x-label for="cuenta-idsimple" :value="__('Id')" />
                    <x-input id="cuenta-idsimple" class="block mt-1 w-full" type="text"
                        wire:model="formulario.idsimple" />
                    <x-input-error for="formulario.idsimple" class="mt-2" />
                </div>
                <div class="mt-2 w-2/3">
                    <x-label for="cuenta-idcompleto" :value="__('Id completo acreedor')" />
                    <x-input id="cuenta-idcompleto" class="block mt-1 w-full" type="text"
                        wire:model="formulario.idcompleto" />
                    <x-input-error for="formulario.idcompleto" class="mt-2" />
                </div>
            </div>

            <div class="flex w-full gap-4">
                <div class="mt-2 w-1/4">
                    <x-label for="cuenta-iso" :value="__('ISO')" />
                    <x-input id="cuenta-iso" class="block mt-1 w-full" type="text" wire:model="formulario.iso" />
                    <x-input-error for="formulario.iso" class="mt-2" />
                </div>
                <div class="mt-2 w-1/4">
                    <x-label for="cuenta-tipo" :value="__('Tipo')" />
                    <x-input id="cuenta-tipo" class="block mt-1 w-full" type="text" wire:model="formulario.tipo" />
                    <x-input-error for="formulario.tipo" class="mt-2" />
                </div>
                <div class="mt-2 w-1/4">
                    <x-label for="cuenta-plazo" :value="__('Plazo')" />
                    <x-input id="cuenta-plazo" class="block mt-1 w-full" type="text" wire:model="formulario.plazo" />
                    <x-input-error for="formulario.plazo" class="mt-2" />
                </div>
                <div class="mt-2 w-1/4">
                    <x-label for="cuenta-mindias" :value="__('Mín. días ejecución')" />
                    <x-input id="cuenta-mindias" class="block mt-1 w-full" type="number"
                        wire:model="formulario.mindiasejecucion" />
                    <x-input-error for="formulario.mindiasejecucion" class="mt-2" />
                </div>
            </div>
        </x-slot>
    </x-dosl.dialog-modal>
</div>
