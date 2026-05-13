<aside
    x-data="{
        open: false,
        panelWidth: 384,
        isResizing: false,
        resizeStartX: 0,
        resizeStartWidth: 384,
        startResize(event) {
            this.isResizing = true;
            this.resizeStartX = event.clientX;
            this.resizeStartWidth = this.panelWidth;

            const onMouseMove = (moveEvent) => {
                if (!this.isResizing) {
                    return;
                }

                const nextWidth = this.resizeStartWidth - (moveEvent.clientX - this.resizeStartX);
                this.panelWidth = Math.max(320, Math.min(760, nextWidth));
            };

            const onMouseUp = () => {
                this.isResizing = false;
                window.removeEventListener('mousemove', onMouseMove);
                window.removeEventListener('mouseup', onMouseUp);
            };

            window.addEventListener('mousemove', onMouseMove);
            window.addEventListener('mouseup', onMouseUp);
        }
    }"
    x-init="$nextTick(() => { open = true })"
    x-show="open"
    x-cloak
    x-transition:enter="transition ease-out duration-250"
    x-transition:enter-start="transform translate-x-8 opacity-0"
    x-transition:enter-end="transform translate-x-0 opacity-100"
    class="relative shrink-0 border-l border-gray-300 bg-white p-4 overflow-y-auto"
    :style="`width: ${panelWidth}px`"
>
    <div
        class="absolute top-0 left-0 h-full w-1.5 cursor-col-resize hover:bg-blue-200 transition-colors"
        @mousedown.prevent="startResize($event)"
        title="Drag to resize panel"
    ></div>

    <div class="flex items-start justify-between gap-4 mb-5">
        <div>
            <h2 class="text-lg font-bold text-gray-900">{{ $title ?? 'Settings' }}</h2>
            @if(!empty($subtitle ?? null))
                <p class="text-sm text-gray-500 mt-1">{{ $subtitle }}</p>
            @endif
        </div>
        @if(!empty($closeAction ?? null))
            <button type="button" wire:click="{{ $closeAction }}" class="text-sm text-gray-500 hover:text-gray-800 cursor-pointer">Close</button>
        @endif
    </div>

    {{ $slot }}
</aside>
