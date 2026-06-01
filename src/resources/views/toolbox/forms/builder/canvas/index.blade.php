<div class="flex h-screen" x-show="currentTab === 'canvas'" x-transition.opacity>
    @include('meros::toolbox.forms.builder.canvas.sidebar')
    @if($editingRepeater !== null)
        @include('meros::toolbox.forms.builder.canvas.settings-repeater')
    @else
        <div id="meros-form-builder-canvas" class="flex-1 p-4 overflow-y-auto min-w-0" wire:key="form-builder-canvas">
            @include('meros::toolbox.forms.builder.canvas.header', ['sectionTitle' => 'Canvas'])
            <div id="meros-form-builder-dropzone" class="min-h-96">
                @if(empty($canvasRows))
                    {{-- Default drop zone for new elements --}}
                    <div
                        class="flex items-center justify-center h-64 border-2 border-dashed border-gray-300 rounded-lg text-gray-400 transition-colors"
                        @dragover.prevent="$store.formBuilder.showEmptyCanvasHighlight($el)"
                        @dragleave="$store.formBuilder.hideEmptyCanvasHighlight($el)"
                        @drop.prevent="$store.formBuilder.handleEmptyCanvasDrop($el)"
                    >
                        <p class="text-sm">Drag fields or groups here to start building your form</p>
                    </div>
                @else
                    {{-- Row gap for inserting new elements --}}
                    @include('meros::toolbox.forms.builder.canvas.row-drop-zone', [
                        'isGroupRow'      => false,
                        'isRepeaterField' => false,
                        'rowIndex'        => -1,
                    ])

                    @foreach($canvasRows as $rowIndex => $canvasRow)
                        @php 
                            $isGroupRow = ($canvasRow['type'] ?? null) === 'group';
                        @endphp

                        @if($isGroupRow)
                            @include('meros::toolbox.forms.builder.canvas.group', [
                                'groupPayload'  => $canvasRow['group'],
                                'groupRowIndex' => $rowIndex,
                            ])
                        @else
                            @include('meros::toolbox.forms.builder.canvas.row', [
                                'canvasRow' => $canvasRow,
                                'rowIndex'  => $rowIndex,
                            ])
                        @endif

                        {{-- Row gap for inserting new elements --}}
                        @include('meros::toolbox.forms.builder.canvas.row-drop-zone', [
                            'isGroupRow'      => false,
                            'isRepeaterField' => false,
                            'rowIndex'        => $rowIndex,
                        ])

                    @endforeach
                @endif
            </div>
        </div>
    @endif
    @include('meros::toolbox.forms.builder.canvas.settings-panel')
</div>