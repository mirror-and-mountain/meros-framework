<div 
    id="meros-form-builder-sidebar" 
    class="relative shrink-0 h-full bg-slate-200 p-4 pb-25 overflow-x-hidden overflow-y-auto overscroll-contain border-r border-slate-300"
    x-data="panelsidebar('{{ array_key_first($fieldCategories) }}')"
    :style="`width: ${sidebarWidth}px`"
>
    <div>
        <h2 class="text-lg font-bold mb-4 text-slate-900">Form Elements</h2>
    </div>

    @if($mode === 'public-form' && $screen === 'canvas-main')
        <div class="mb-6 border-b border-slate-300 pb-4">
            <h3 class="text-md font-semibold mb-2 text-slate-800">Field Groups</h3>

            <div
                class="mb-2 p-2 bg-white border border-slate-300 rounded shadow-sm text-slate-800 cursor-grab active:cursor-grabbing flex items-center select-none transition-colors duration-150 motion-reduce:transition-none hover:bg-blue-50 hover:border-blue-500"
                draggable="true"
                data-item-kind="group"
                data-item-handle="untitled_section"
                data-item-label="Untitled Section"
                @dragstart="handleDragStart($event, $el.dataset.itemKind, $el.dataset.itemHandle)"
            >
                <div class="flex justify-between items-center w-full">
                    Blank Section
                    <span class="drag-handle text-slate-600">⠿</span>
                </div>
            </div>

            @foreach ($fieldGroups as $handle => $label)
                <div
                    class="mb-2 p-2 bg-white border border-slate-300 rounded shadow-sm text-slate-800 cursor-grab active:cursor-grabbing flex items-center select-none transition-colors duration-150 motion-reduce:transition-none hover:bg-blue-50 hover:border-blue-500"
                    draggable="true"
                    data-item-kind="group"
                    data-item-handle="{{ $handle }}"
                    data-item-label="{{ $handle }}"
                    @dragstart="handleDragStart($event, $el.dataset.itemKind, $el.dataset.itemHandle)"
                >
                    <div class="flex justify-between items-center w-full">
                        {{ $label }}
                        <span class="drag-handle text-slate-600">⠿</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div id="field-types">
        @foreach($fieldCategories as $category => $fields)
            <div 
                id="meros-form-builder-category-{{ $category }}" 
                class="flex justify-between items-center cursor-pointer rounded px-2 py-2 mb-1 text-slate-800 hover:bg-slate-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-1 transition-colors duration-150 motion-reduce:transition-none"
                :class="openCategory === '{{ $category }}' ? 'bg-slate-300' : ''"
                @click.stop="toggleCategory('{{ $category }}')"
            >
                <h3 class="text-md font-semibold leading-tight">{{ ucfirst($category) }}</h3>
                <span 
                    class="text-sm text-slate-600 transition-transform duration-300 motion-reduce:transition-none"
                    :class="openCategory === '{{ $category }}' ? 'rotate-90' : ''"
                >   
                    ▶
                </span>
            </div>
             <div 
                id="meros-form-builder-fields-{{ $category }}" 
                class="mb-4 transition-all duration-300 ease-in-out overflow-hidden"
                :class="openCategory === '{{ $category }}' ? 'max-h-96 overflow-y-auto pr-1' : 'max-h-0 overflow-hidden'"
            >
                @foreach ($fields as $field)
                    @if(($screen === 'canvas-main' || $screen === 'canvas-rules-editor') || ($screen === 'canvas-repeater-editor' && $field['handle'] !== 'repeater'))
                        <div 
                            class="mb-2 p-2 bg-white border border-slate-300 rounded shadow-sm text-slate-800 cursor-grab active:cursor-grabbing flex items-center select-none transition-colors duration-150 motion-reduce:transition-none hover:bg-blue-50 hover:border-blue-500"
                            draggable="true"
                            data-item-kind="field"
                            data-item-handle="{{ $field['handle'] }}"
                            data-item-label="{{ $field['label'] }}"
                            @dragstart="handleDragStart($event, $el.dataset.itemKind, $el.dataset.itemHandle)"
                            wire:key="field-type-{{ $field['handle'] }}"
                        >
                            @if ($field['icon'] !== '')
                                <span class="mr-2 text-slate-600">
                                    @include('meros::forms.field-icons.' . $field['icon'])
                                </span>
                            @endif
                            <div class="flex justify-between items-center w-full">
                                {{ $field['label'] }}
                                <span class="drag-handle text-slate-600">⠿</span>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endforeach
    </div>

    <div
        class="absolute top-0 right-0 h-full w-1.5 cursor-col-resize bg-slate-300/60 hover:bg-blue-300 transition-colors duration-150 motion-reduce:transition-none"
        @mousedown.prevent="startResize($event)"
        title="Drag to resize sidebar"
    ></div>
</div>