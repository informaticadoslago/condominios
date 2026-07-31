<div class="p-4 sm:p-6">
    <div class="max-w-5xl mx-auto">
        <h1 class="mb-4 text-2xl font-medium">
            {{ $inmuebleId ? __('Editar inmueble') : __('Nuevo inmueble') }}
        </h1>

        @livewire('inmuebles.crear.crear-inmueble',
            ['inmuebleId' => $inmuebleId],
            key('inmueble-' . ($inmuebleId ?? 'nuevo')))
    </div>
</div>
