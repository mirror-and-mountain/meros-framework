<div id="meros-form-builder-repeater-settings-{{ $editingRepeaterID }}" class="flex-1 h-full p-4 pb-25 overflow-y-auto overscroll-contain min-w-0" x-data="{ fieldDragging: false }" wire:key="form-builder-repeater-settings-{{ $editingRepeaterID }}" wire:transition>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold">Editing Repeater: {{ $editingRepeater->getLabel() }}</h2>
        @include('meros::toolbox.forms.builder.canvas.action-button', [
            'buttonText' => 'Update and Close',
            'action'     => '$store.formBuilder.activeField = null; $wire.closeEditingRepeater()',
        ])
    </div>
    <div class="space-y-4">
        {!! $editingRepeater->render(false, false) !!}
        <div>
            @php
                $fields = $editingRepeater->getFields();
            @endphp
            @if($fields->isEmpty())
                {{-- Default drop zone for new elements --}}
                <div
                    class="flex items-center justify-center h-64 border-2 border-dashed border-gray-300 rounded-lg text-gray-400 transition-colors"
                    @dragover.prevent="$store.formBuilder.showEmptyCanvasHighlight($el)"
                    @dragleave="$store.formBuilder.hideEmptyCanvasHighlight($el)"
                    @drop.prevent="$store.formBuilder.handleEmptyRepeaterCanvasDrop($el)"
                >
                    <p class="text-sm">Drag fields here to start building your repeater</p>
                </div>
            @else
                <button 
                    type="button" 
                    class="cursor-pointer py-2 px-4 mb-4 bg-blue-600 text-white rounded hover:bg-blue-700 active:bg-blue-800 font-medium text-sm transition-colors"
                    @click="$store.formBuilder.updateRepeaterDefaultValue('{{ $editingRepeaterID }}')"
                >
                        Save As Default Value
                </button>
                @foreach($fields as $fieldIndex => $field)
                    @php
                        $id = $field->getID();
                        $name = $field->getName();
                        $label = $field->getLabel();
                        $helpText = $field->getHelpText();
                    @endphp

                    @if($fieldIndex === 0)
                        @include('meros::toolbox.forms.builder.canvas.row-drop-zone', [
                            'isGroupRow'      => false,
                            'isRepeaterField' => true,
                            'repeaterId'      => $editingRepeaterID,
                            'fieldId'         => $id,
                            'newPosition'     => 0,
                        ])
                    @endif

                    <div class="mb-3 p-4 bg-gray-100 rounded-md shadow-sm border border-gray-200" wire:key="repeater-field-{{ $id }}">
                        <div
                            class="relative z-20 mb-3 flex items-center justify-between gap-3 rounded-md border border-dashed border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-500 cursor-move active:cursor-grabbing select-none transition-colors hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600"
                            draggable="true"
                            title="Drag to move field"
                            aria-label="Move field"
                            aria-description="Drag to move this field to a different position in the repeater"
                            :aria-grabbed="fieldDragging ? 'true' : 'false'"
                            @dragstart.stop="fieldDragging = true; $event.dataTransfer.effectAllowed = 'move'; $store.formBuilder.startRepeaterFieldCanvasDrag('{{ $editingRepeaterID }}', '{{ $id }}', {{ $fieldIndex }});"
                            @dragend.stop="fieldDragging = false; $store.formBuilder.endDrag();"
                        >
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="text-sm leading-none">⠿</span>
                                <span class="truncate font-medium">Move field: {{ $label }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                {{-- Move Field Up Button --}}
                                <button
                                    type="button"
                                    class="shrink-0 text-gray-300 hover:text-blue-500 transition-colors text-xl leading-none"
                                    title="Move field up"
                                    wire:click="moveRepeaterField('{{ $editingRepeaterID }}', '{{ $id }}', {{ $fieldIndex - 1}})"
                                >
                                    @include('meros::toolbox.svgs.move-up')
                                </button>

                                {{-- Move Field Down Button --}}
                                <button
                                    type="button"
                                    class="shrink-0 text-gray-300 hover:text-blue-500 transition-colors text-xl leading-none"
                                    title="Move field down"
                                    wire:click="moveRepeaterField('{{ $editingRepeaterID }}', '{{ $id }}', {{ $fieldIndex + 1}})"
                                >
                                    @include('meros::toolbox.svgs.move-down')
                                </button>

                                {{-- Edit Field Button --}}
                                <button
                                    type="button"
                                    class="shrink-0 text-gray-300 hover:text-blue-500 transition-colors text-xl leading-none"
                                    title="Field settings"
                                    @click.stop="$store.formBuilder.editRepeaterField('{{ $editingRepeaterID }}', '{{ $id }}', {{ $fieldIndex }})"
                                >
                                    @include('meros::toolbox.svgs.settings')
                                </button>

                                {{-- Remove Field Button --}}
                                <button
                                    type="button"
                                    class="shrink-0 text-gray-300 hover:text-red-500 transition-colors text-xl leading-none"
                                    title="Remove field"
                                    wire:click="removeRepeaterField('{{ $editingRepeaterID }}', '{{ $id }}')"
                                >
                                    @include('meros::toolbox.svgs.remove')
                                </button>
                            </div>
                        </div>
                        @php 
                            $field->attribute('data-is-repeater-field', 'true');
                        @endphp
                        {!! $field->render() !!}
                    </div>
                    @include('meros::toolbox.forms.builder.canvas.row-drop-zone', [
                        'isGroupRow'      => false,
                        'isRepeaterField' => true,
                        'repeaterId'      => $editingRepeaterID,
                        'fieldId'         => $id,
                        'newPosition'     => $fieldIndex + 1,
                    ])
                @endforeach
            @endif
        </div>
    </div>
</div>