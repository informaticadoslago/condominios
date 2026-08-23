<x-botonera-page>
    <x-slot name="title">
        {{ __('Pruebas de correo') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Envía un ejemplo de cada plantilla, con datos reales, para revisar el aspecto') }}
    </x-slot>
    <x-slot name="botonera"></x-slot>

    <x-slot name="content">
        <div class="max-w-2xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <x-label for="destinatario" :value="__('Enviar a')" />
                <x-input id="destinatario" type="email" class="block mt-1 w-full" wire:model="destinatario" />
                <x-input-error for="destinatario" class="mt-1" />
            </div>

            <x-dosl.tabla>
                <table class="table-striped w-full table-auto text-sm text-left">
                    <tbody class="divide-y">
                        <tr>
                            <td class="px-6 py-4">{{ __('Aviso de cargo (remesa)') }}</td>
                            <td class="px-6 py-4 text-right">
                                <x-ui-button type="button" wire:click="enviarAvisoRemesa" wire:loading.attr="disabled">
                                    <i class="fa-solid fa-paper-plane mr-1"></i>{{ __('Enviar') }}
                                </x-ui-button>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4">{{ __('Aviso de pago (transferencia)') }}</td>
                            <td class="px-6 py-4 text-right">
                                <x-ui-button type="button" wire:click="enviarAvisoTransferencia" wire:loading.attr="disabled">
                                    <i class="fa-solid fa-paper-plane mr-1"></i>{{ __('Enviar') }}
                                </x-ui-button>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4">{{ __('Aviso de devolución') }}</td>
                            <td class="px-6 py-4 text-right">
                                <x-ui-button type="button" wire:click="enviarAvisoDevolucion" wire:loading.attr="disabled">
                                    <i class="fa-solid fa-paper-plane mr-1"></i>{{ __('Enviar') }}
                                </x-ui-button>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4">{{ __('Verificación de correo (propietario)') }}</td>
                            <td class="px-6 py-4 text-right">
                                <x-ui-button type="button" wire:click="enviarVerificacionPropietario" wire:loading.attr="disabled">
                                    <i class="fa-solid fa-paper-plane mr-1"></i>{{ __('Enviar') }}
                                </x-ui-button>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4">{{ __('Confirmación de correo (usuario)') }}</td>
                            <td class="px-6 py-4 text-right">
                                <x-ui-button type="button" wire:click="enviarConfirmacionUsuario" wire:loading.attr="disabled">
                                    <i class="fa-solid fa-paper-plane mr-1"></i>{{ __('Enviar') }}
                                </x-ui-button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </x-dosl.tabla>
        </div>
    </x-slot>
</x-botonera-page>
