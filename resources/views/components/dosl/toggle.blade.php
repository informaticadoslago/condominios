@props([
    'colorOn' => 'bg-verdedosl-500',
    'colorOff' => 'bg-gray-300',
    'width' => 48,
    'height' => 28,
    'disabled' => false,
    'label' => 'Sí/No',
])

<div class="flex items-center space-x-2">
    <div
        x-data="{
            value: @entangle($attributes->wire('model')),
            dragging: false,
            startX: 0,
            knobX: 0,
            maxX: {{ $width }} - {{ $height }},
            init() {
                this.knobX = (this.value === true || this.value === 'Si') ? this.maxX : 0;
            },
            startDrag(event) {
                if(@json($disabled)) return;
                this.dragging = true;
                this.startX = event.type.startsWith('touch') ? event.touches[0].clientX : event.clientX;
            },
            doDrag(event) {
                if(!this.dragging) return;
                let clientX = event.type.startsWith('touch') ? event.touches[0].clientX : event.clientX;
                let delta = clientX - this.startX;
                let newX = this.value ? this.maxX + delta : delta;
                this.knobX = Math.min(Math.max(0, newX), this.maxX);
            },
            endDrag() {
                if(!this.dragging) return;
                this.dragging = false;
                this.value = this.knobX > this.maxX / 2;
                this.knobX = this.value ? this.maxX : 0;
            }
        }"
        x-init="init()"
        x-effect="knobX = (value === true || value === 'Si') ? maxX : 0"
        class="relative inline-block select-none cursor-pointer rounded-full transition-colors duration-200 ease-in-out"
        :class="value ? '{{ $colorOn }}' : '{{ $colorOff }}'"
        :style="'width: {{ $width }}px; height: {{ $height }}px; opacity: '+(@json($disabled) ? '0.5' : '1')"
        @click="if(!@json($disabled) && !dragging) value = !value; knobX = value ? maxX : 0"
        @mousemove.window="doDrag($event)"
        @mouseup.window="endDrag"
        @touchmove.window.prevent="doDrag($event)"
        @touchend.window="endDrag"
    >
        <div
            class="absolute top-0 left-0 bg-white shadow rounded-full transition-transform duration-200"
            :style="'width: {{ $height }}px; height: {{ $height }}px; transform: translateX(' + knobX + 'px)'"
            @mousedown="startDrag"
            @touchstart.prevent="startDrag"
        ></div>
    </div>

    <span class="select-none text-gray-700">{{ $label }}</span>
</div>
