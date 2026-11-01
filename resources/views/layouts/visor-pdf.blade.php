{{-- Visor de PDF para el iPhone con la web instalada en la pantalla de inicio: ahí un PDF
     abierto en otra pestaña ocupa la app entera sin ningún botón para volver. Lo abre
     resources/js/visor-pdf.js; en el PC y en Safari normal los PDF se abren como siempre.
     Se enseña y oculta con el atributo hidden (gana a las clases flex). --}}
<div id="visor-pdf" hidden class="fixed inset-0 z-[200] flex flex-col bg-gray-200 dark:bg-zinc-900"
    style="padding-top: env(safe-area-inset-top); padding-bottom: env(safe-area-inset-bottom);"
    data-error="{{ __('No se ha podido abrir el documento.') }}">
    <div class="shrink-0 flex items-center justify-between gap-2 px-3 py-2 bg-white dark:bg-zinc-800 border-b border-gray-300 dark:border-zinc-700">
        <x-secondary-button type="button" data-visor-pdf="cerrar" title="{{ __('Cerrar') }}">
            <i class="fa-solid fa-xmark mr-1"></i>{{ __('Cerrar') }}
        </x-secondary-button>
        <x-secondary-button type="button" data-visor-pdf="compartir" hidden title="{{ __('Imprimir / Compartir') }}">
            <i class="fa-solid fa-print mr-1"></i>{{ __('Imprimir / Compartir') }}
        </x-secondary-button>
    </div>
    <div data-visor-pdf="cargando" hidden class="flex-1 flex items-center justify-center text-sm text-gray-600 dark:text-gray-300">
        <i class="fa-solid fa-spinner fa-spin mr-2"></i>{{ __('Cargando…') }}
    </div>
    <div data-visor-pdf="paginas" class="flex-1 overflow-y-auto p-2 space-y-2"></div>
</div>
