<div 
    id="meros-form-builder-sidebar" 
    class="relative shrink-0 bg-gray-100 p-4 overflow-y-auto border-r border-gray-300"
    x-data="{ 
        sidebarWidth: 320,
        isResizing: false,
        resizeStartX: 0,
        resizeStartWidth: 320,
        toggleCategory(category) {
            this.openCategory = this.openCategory === category ? null : category;
        },
        startResize(event) {
            this.isResizing = true;
            this.resizeStartX = event.clientX;
            this.resizeStartWidth = this.sidebarWidth;

            const onMouseMove = (moveEvent) => {
                if (!this.isResizing) {
                    return;
                }

                const nextWidth = this.resizeStartWidth + (moveEvent.clientX - this.resizeStartX);
                this.sidebarWidth = Math.max(260, Math.min(520, nextWidth));
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
    :style="`width: ${sidebarWidth}px`"
>
    <div>
        <h2 class="text-lg font-bold mb-4">Form Settings</h2>
    </div>

    <div class="flex flex-col gap-2">
        <div 
            class="p-4 bg-gray-200 hover:bg-gray-300 rounded transition-colors cursor-pointer" 
            :class="settingsPage === 'general' ? 'bg-gray-300' : 'bg-gray-200 hover:bg-gray-300'"
            @click="settingsPage = 'general'">
            General Settings
        </div>

        <div 
            class="p-4 bg-gray-200 hover:bg-gray-300 rounded transition-colors cursor-pointer" 
            :class="settingsPage === 'actions' ? 'bg-gray-300' : 'bg-gray-200 hover:bg-gray-300'"
            @click="settingsPage = 'actions'">
            Actions
        </div>
    </div>

    <div
        class="absolute top-0 right-0 h-full w-1.5 cursor-col-resize hover:bg-blue-200 transition-colors"
        @mousedown.prevent="startResize($event)"
        title="Drag to resize sidebar"
    ></div>
</div>