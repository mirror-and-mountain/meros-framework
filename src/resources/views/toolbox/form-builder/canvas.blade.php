<div id="meros-form-builder-canvas" class="flex-1 p-4 overflow-y-auto min-w-0">
    <h2 class="text-lg font-bold mb-4">Canvas</h2>
    <span wire:click="getFormStructureJson" class="cursor-pointer text-sm text-gray-500 hover:text-gray-700">Dump form structure JSON</span>
    <div id="meros-form-builder-dropzone" class="min-h-96">
        @if(empty($canvasRows))
            <div
                class="flex items-center justify-center h-64 border-2 border-dashed border-gray-300 rounded-lg text-gray-400 transition-colors"
                @dragover.prevent="$store.formDrag.showEmptyCanvasHighlight($el)"
                @dragleave="$store.formDrag.hideEmptyCanvasHighlight($el)"
                @drop.prevent="$store.formDrag.handleEmptyCanvasDrop($el, $wire)"
            >
                <p class="text-sm">Drag fields or groups here to start building your form</p>
            </div>
        @else
            <div
                class="h-2 rounded-sm mb-1 transition-all duration-150"
                @dragover.prevent="$store.formDrag.handleCanvasRowGapDragOver($event, $el)"
                @dragleave="$store.formDrag.hideRowGap($el)"
                @drop.prevent="$store.formDrag.handleCanvasRowGapDrop($event, $el, $wire, 0, -1)"
            ></div>

            @foreach($canvasRows as $canvasRow)
                @php
                    $rowIndex = $canvasRow['rowIndex'];
                    $isGroupRow = ($canvasRow['_type'] ?? null) === 'group';
                @endphp

                @if($isGroupRow)
                    @include('meros::toolbox.form-builder.canvas.group-row', [
                        'canvasRow' => $canvasRow,
                        'rowIndex' => $rowIndex,
                    ])
                @else
                    @include('meros::toolbox.form-builder.canvas.canvas-row', [
                        'canvasRow' => $canvasRow,
                        'rowIndex' => $rowIndex,
                    ])
                @endif

                <div
                    class="h-2 mb-1 rounded-sm transition-all duration-150"
                    @dragover.prevent="$store.formDrag.handleCanvasRowGapDragOver($event, $el)"
                    @dragleave="$store.formDrag.hideRowGap($el)"
                    @drop.prevent="$store.formDrag.handleCanvasRowGapDrop($event, $el, $wire, {{ $rowIndex + 1 }}, {{ $rowIndex }})"
                ></div>
            @endforeach
        @endif
    </div>
</div>
