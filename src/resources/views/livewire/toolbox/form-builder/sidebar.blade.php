<div 
    id="meros-form-builder-sidebar" 
    class="relative shrink-0 bg-gray-100 p-4 overflow-y-auto border-r border-gray-300"
    x-data="{ 
        openCategory: '{{ array_key_first($fieldCategories) }}',
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
        <h2 class="text-lg font-bold mb-4">Form Elements</h2>
    </div>

    <div class="mb-6 border-b border-gray-300 pb-4">
        <h3 class="text-md font-semibold mb-2">Form Groups</h3>

        <div
            class="mb-2 p-2 bg-white rounded shadow cursor-grab active:cursor-grabbing flex items-center select-none"
            draggable="true"
            data-item-kind="group"
            data-item-handle=""
            data-item-label="Untitled Section"
            @dragstart="$store.formDrag.startDrag($el.dataset.itemKind, $el.dataset.itemHandle, $el.dataset.itemLabel); $event.dataTransfer.effectAllowed = 'copy'"
            @dragend="$store.formDrag.endDrag()"
        >
            <div class="flex justify-between items-center w-full">
                Blank Section
                <span class="drag-handle">⠿</span>
            </div>
        </div>

        @foreach ($fieldGroups as $group)
            <div
                class="mb-2 p-2 bg-white rounded shadow cursor-grab active:cursor-grabbing flex items-center select-none"
                draggable="true"
                data-item-kind="group"
                data-item-handle="{{ $group['handle'] }}"
                data-item-label="{{ $group['label'] }}"
                @dragstart="$store.formDrag.startDrag($el.dataset.itemKind, $el.dataset.itemHandle, $el.dataset.itemLabel); $event.dataTransfer.effectAllowed = 'copy'"
                @dragend="$store.formDrag.endDrag()"
            >
                <div class="flex justify-between items-center w-full">
                    {{ $group['label'] }}
                    <span class="drag-handle">⠿</span>
                </div>
            </div>
        @endforeach
    </div>

    <div>
    @foreach ($fieldCategories as $category => $fields)
        <div 
            id="meros-form-builder-category-{{ $category }}" 
            class="flex justify-between items-center cursor-pointer" 
            @click="toggleCategory('{{ $category }}')"
        >
            <h3 class="text-md font-semibold my-3">{{ ucfirst($category) }}</h3>
            <span 
                class="text-sm text-gray-500 transition-transform duration-300" 
                :class="openCategory === '{{ $category }}' ? 'rotate-90' : ''"
            >
                ▶
            </span>
        </div>
        <div 
            id="meros-form-builder-fields-{{ $category }}" 
            class="mb-4 transition-all duration-300 ease-in-out overflow-hidden"
            :class="openCategory === '{{ $category }}' ? 'max-h-96' : 'max-h-0'"
        >
        @foreach ($fields as $field)
            <div 
                class="mb-2 p-2 bg-white rounded shadow cursor-grab active:cursor-grabbing flex items-center select-none"
                draggable="true"
                data-item-kind="field"
                data-item-handle="{{ $field['handle'] }}"
                data-item-label="{{ $field['label'] }}"
                @dragstart="$store.formDrag.startDrag($el.dataset.itemKind, $el.dataset.itemHandle, $el.dataset.itemLabel); $event.dataTransfer.effectAllowed = 'copy'"
                @dragend="$store.formDrag.endDrag()"
            >
                @if ($field['icon'] !== '')
                    <span class="mr-2">@include('meros::livewire.toolbox.form-builder.field-icons.' . $field['icon'])</span>
                @endif
                <div class="flex justify-between items-center w-full">
                    {{ $field['label'] }}
                    <span class="drag-handle">⠿</span>
                </div>
            </div>
        @endforeach
        </div>
    @endforeach
    </div>

    <div
        class="absolute top-0 right-0 h-full w-1.5 cursor-col-resize hover:bg-blue-200 transition-colors"
        @mousedown.prevent="startResize($event)"
        title="Drag to resize sidebar"
    ></div>
</div>