@props([
    'id',
    'maxWidth',
    'closeOnClickAway' => true,
    'closeOnEscape' => false,
    'showCloseButton' => true,
    'closeButtonLabel' => __('Cerrar modal'),
    'destroyOnClose' => false,
    'draggable' => false,
    'modal2l' => false,
    'fullscreen' => false,
])

@php
    $id = $id ?? md5($attributes->wire('model'));

    // A pantalla completa no tiene sentido arrastrar ni centrar.
    if ($fullscreen) {
        $draggable = false;
    }

    $maxWidth = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        '4xl' => 'sm:max-w-4xl',
        '5xl' => 'sm:max-w-5xl',
        '6xl' => 'sm:max-w-6xl',
        '7xl' => 'sm:max-w-7xl',
        'full' => 'sm:max-w-none',
    ][$maxWidth ?? '5xl'];
@endphp

<div x-data="{
    show: @entangle($attributes->wire('model')),
    draggable: {{ $draggable ? 'true' : 'false' }},
    dragging: false,
    offsetX: 0,
    offsetY: 0,
    posX: null,
    posY: null,
    startDrag(e) {
        if (!this.draggable) return;
        this.dragging = true;
        const rect = this.$refs.dialog.getBoundingClientRect();
        this.offsetX = e.clientX - rect.left;
        this.offsetY = e.clientY - rect.top;
        document.body.style.userSelect = 'none';
    },
    endDrag() {
        this.dragging = false;
        document.body.style.userSelect = 'auto';
    },
    onDrag(e) {
        if (!this.dragging) return;
        let x = e.clientX - this.offsetX;
        let y = e.clientY - this.offsetY;
        const el = this.$refs.dialog;
        const w = el.offsetWidth,
            h = el.offsetHeight;
        const vw = window.innerWidth,
            vh = window.innerHeight;
        x = Math.max(8, Math.min(x, vw - w - 8));
        y = Math.max(8, Math.min(y, vh - h - 8));
        this.posX = x;
        this.posY = y;
    },
    centerDialog() {
        const el = this.$refs.dialog;

        // modal2l: posición inicial fija + tamaño dinámico
        if ({{ $modal2l ? 'true' : 'false' }}) {
            el.style.position = 'fixed';
            el.style.top = '56px';
            el.style.left = '256px';
            el.style.width = (window.innerWidth - 256 - 16) + 'px'; // margen derecho 16px
            el.style.height = (window.innerHeight - 56 - 16) + 'px'; // margen inferior 16px
            {{-- // posX/posY solo para drag
            if (this.posX === null) this.posX = 256;
            if (this.posY === null) this.posY = 56; --}}
            return;
        }

        // Modales normales: centrado
        if (this.posX !== null || this.posY !== null) return;
        const w = el.offsetWidth,
            h = el.offsetHeight;
        const vw = window.innerWidth,
            vh = window.innerHeight;
        this.posX = Math.max(8, (vw - w) / 2);
        this.posY = Math.max(8, (vh - h) / 2);
    },
    clampToViewport() {
        // Si el contenido crece (p.ej. tras 'Comprobar'), re-encajar el diálogo
        // en pantalla sin perder la posición arrastrada.
        const el = this.$refs.dialog;
        if (!el || this.posX === null || this.posY === null) return;
        this.posX = Math.max(8, Math.min(this.posX, window.innerWidth - el.offsetWidth - 8));
        this.posY = Math.max(8, Math.min(this.posY, window.innerHeight - el.offsetHeight - 8));
    },
    init() {
        if (this.draggable && this.$refs.dialog) {
            new ResizeObserver(() => { if (this.show && !this.dragging) this.clampToViewport() })
                .observe(this.$refs.dialog);
        }
    }

}" x-on:mousemove.window="onDrag($event)" x-on:mouseup.window="endDrag()"
    x-on:close.stop="show = false" @if ($closeOnEscape)
    x-on:keydown.escape.window="show = false"
    @endif
    @if (!$destroyOnClose)
        x-show="show" style="display:none;"
    @endif
    x-effect="if(show){$nextTick(()=>centerDialog())} else { dragging=false }"
    id="{{ $id }}"
    class="jetstream-modal fixed inset-0 z-50 {{ $fullscreen ? 'overflow-hidden' : 'overflow-y-auto px-4 py-20 sm:px-0' }}"
    aria-modal="true"
    role="dialog"
    >
    {{-- Backdrop --}}
    <div x-show="show" class="fixed inset-0 bg-gray-500 dark:bg-gray-900 opacity-75"
        @if ($closeOnClickAway) @click.self="show = false" @endif></div>

    {{-- Dialog --}}
    <div x-show="show" x-ref="dialog"
        :class="draggable ? 'fixed' : '{{ $fullscreen ? 'fixed inset-0' : 'relative sm:mx-auto' }}'"
        :style="draggable
            ?
            ('left:' + (posX ?? 0) + 'px; top:' + (posY ?? 0) + 'px; position:fixed;' + (
                {{ $modal2l ? 'true' : 'false' }} && '{{ $maxWidth }}'
                === 'sm:max-w-full' ? ';width:' + (window.innerWidth - 56 - 16) + 'px;height:' + (window
                    .innerHeight - 256 - 16) + 'px;' : '')) :
            ({{ $modal2l ? 'true' : 'false' }} && '{{ $maxWidth }}'
                === 'sm:max-w-full' ? 'width:' + (window.innerWidth - 56 - 16) + 'px;height:' + (window.innerHeight -
                    256 - 16) + 'px;' : '')"
        class="bg-white dark:bg-gray-800 shadow-xl transform transition-all flex flex-col overflow-hidden {{ $fullscreen ? 'w-full h-full rounded-none' : 'mb-6 rounded-lg sm:w-full ' . $maxWidth }}"
        x-trap.inert.noscroll="show" x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
        {{-- Header draggable --}}
        <div class="cursor-move bg-gray-100 dark:bg-gray-700 px-4 py-2 font-medium select-none relative"
            @mousedown="startDrag($event)">
            {{ $title ?? '' }}

            @if ($showCloseButton)
                <button type="button" @click="show = false"
                    class="absolute right-3 top-3 p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-600 focus:outline-none"
                    title="{{ $closeButtonLabel }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
            @endif
        </div>

        {{-- Body scrollable (mantener scroll como en tu versión original) --}}
        <div class="px-4 py-4 overflow-y-auto {{ $fullscreen ? 'flex-1' : '' }}"
            @style(['max-height: calc(100vh - 140px)' => ! $fullscreen]) x-ref="body">
            {{ $slot }}
        </div>
    </div>
</div>
