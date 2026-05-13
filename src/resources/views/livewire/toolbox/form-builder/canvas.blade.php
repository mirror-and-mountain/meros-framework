<div id="meros-form-builder-canvas" class="flex-1 p-4 overflow-y-auto min-w-0">
    <h2 class="text-lg font-bold mb-4">Canvas</h2>
    <span wire:click="getFormStructureJson" class="cursor-pointer text-sm text-gray-500 hover:text-gray-700">Dump form structure JSON</span>
    <div id="meros-form-builder-dropzone" class="min-h-96">
        @if(empty($canvasRows))
            <div
                class="flex items-center justify-center h-64 border-2 border-dashed border-gray-300 rounded-lg text-gray-400 transition-colors"
                @dragover.prevent="$el.classList.add('border-blue-400', 'bg-blue-50', 'text-blue-500'); $el.classList.remove('border-gray-300', 'text-gray-400')"
                @dragleave="$el.classList.remove('border-blue-400', 'bg-blue-50', 'text-blue-500'); $el.classList.add('border-gray-300', 'text-gray-400')"
                @drop.prevent="$el.classList.remove('border-blue-400', 'bg-blue-50', 'text-blue-500'); $el.classList.add('border-gray-300', 'text-gray-400'); $store.formDrag.itemKind === 'group' ? $wire.addGroupToCanvas($store.formDrag.itemHandle ?? '') : $wire.addFieldToNewRow(-1, $store.formDrag.fieldType)"
            >
                <p class="text-sm">Drag fields or groups here to start building your form</p>
            </div>
        @else
            <div
                class="h-2 rounded-sm mb-1 transition-all duration-150"
                @dragover.prevent="($store.formDrag.itemKind === 'group' || $store.formDrag.itemKind === 'field' || $event.dataTransfer.types.includes('application/x-meros-group-row')) ? $store.formDrag.showRowGap($el) : null"
                @dragleave="$store.formDrag.hideRowGap($el)"
                @drop.prevent="$store.formDrag.hideRowGap($el); ($store.formDrag.itemKind === 'group' || $event.dataTransfer.types.includes('application/x-meros-group-row')) ? ($store.formDrag.itemKind === 'group' ? $wire.addGroupBeforeRow(0, $store.formDrag.itemHandle ?? '') : (() => { const from = Number($event.dataTransfer.getData('application/x-meros-group-row')); if (!Number.isNaN(from)) { $wire.moveGroupRowBefore(from, 0); } })()) : ($store.formDrag.sourceGroupRowIndex !== null ? $wire.moveFieldFromGroupToNewRow($store.formDrag.sourceGroupRowIndex, $store.formDrag.sourceGroupInnerRowIndex, $store.formDrag.sourceFieldIndex, -1) : ($store.formDrag.isCanvasDrag ? $wire.moveFieldToNewRow($store.formDrag.sourceRowIndex, $store.formDrag.sourceFieldIndex, -1) : $wire.addFieldToNewRow(-1, $store.formDrag.fieldType)))"
            ></div>

            @foreach($canvasRows as $canvasRow)
                @php
                    $rowIndex = $canvasRow['rowIndex'];
                    $isGroupRow = ($canvasRow['_type'] ?? null) === 'group';
                @endphp

                @if($isGroupRow)
                    @include('meros::livewire.toolbox.form-builder.canvas.group-row', [
                        'canvasRow' => $canvasRow,
                        'rowIndex' => $rowIndex,
                    ])
                @else
                    @include('meros::livewire.toolbox.form-builder.canvas.canvas-row', [
                        'canvasRow' => $canvasRow,
                        'rowIndex' => $rowIndex,
                    ])
                @endif

                <div
                    class="h-2 mb-1 rounded-sm transition-all duration-150"
                    @dragover.prevent="($store.formDrag.itemKind === 'group' || $store.formDrag.itemKind === 'field' || $event.dataTransfer.types.includes('application/x-meros-group-row')) ? $store.formDrag.showRowGap($el) : null"
                    @dragleave="$store.formDrag.hideRowGap($el)"
                    @drop.prevent="$store.formDrag.hideRowGap($el); ($store.formDrag.itemKind === 'group' || $event.dataTransfer.types.includes('application/x-meros-group-row')) ? ($store.formDrag.itemKind === 'group' ? $wire.addGroupBeforeRow({{ $rowIndex + 1 }}, $store.formDrag.itemHandle ?? '') : (() => { const from = Number($event.dataTransfer.getData('application/x-meros-group-row')); if (!Number.isNaN(from)) { $wire.moveGroupRowBefore(from, {{ $rowIndex + 1 }}); } })()) : ($store.formDrag.sourceGroupRowIndex !== null ? $wire.moveFieldFromGroupToNewRow($store.formDrag.sourceGroupRowIndex, $store.formDrag.sourceGroupInnerRowIndex, $store.formDrag.sourceFieldIndex, {{ $rowIndex }}) : ($store.formDrag.isCanvasDrag ? $wire.moveFieldToNewRow($store.formDrag.sourceRowIndex, $store.formDrag.sourceFieldIndex, {{ $rowIndex }}) : $wire.addFieldToNewRow({{ $rowIndex }}, $store.formDrag.fieldType)))"
                ></div>
            @endforeach
        @endif
    </div>
</div>
