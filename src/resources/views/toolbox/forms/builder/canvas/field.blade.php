@if(($scope ?? 'top') === 'group')
    @php
        $groupRowHasSpace   = count($groupRowFields) < 3;
        $groupRowHasSpaceJs = $groupRowHasSpace ? 'true' : 'false';
        $groupZoneActiveJs  = "\$store.formBuilder.isDragging && \$store.formBuilder.itemKind === 'field' && !(\$store.formBuilder.sourceGroupRowIndex === {$groupRowIndex} && \$store.formBuilder.sourceGroupInnerRowIndex === {$groupRowInnerIndex} && \$store.formBuilder.sourceFieldIndex === {$groupFieldIndex}) && ((\$store.formBuilder.sourceGroupRowIndex === {$groupRowIndex} && \$store.formBuilder.sourceGroupInnerRowIndex === {$groupRowInnerIndex}) || {$groupRowHasSpaceJs})";
    @endphp
    <div
        class="relative flex-1 min-w-0 bg-white border border-gray-200 rounded-md shadow-sm p-3"
        x-data="{ dragging: false }"
        :class="dragging ? 'ring-2 ring-blue-400 border-blue-400 opacity-60' : ''"
        data-field-location="group-{{ $groupRowIndex }}-{{ $groupRowInnerIndex }}-{{ $groupFieldIndex }}"
        wire:key="{{ $groupField->getId() }}"
    >
        <div
            class="absolute inset-y-0 left-0 w-2/5 z-10 flex items-center rounded-l-md"
            :style="{{ $groupZoneActiveJs }} ? 'pointer-events:auto' : 'pointer-events:none'"
            @dragover.prevent="$store.formBuilder.showInsertMarker($el)"
            @dragleave="$store.formBuilder.hideInsertMarker($el)"
            @drop.prevent="$store.formBuilder.handleGroupFieldInsertDrop($el, {{ $groupRowIndex }}, {{ $groupRowInnerIndex }}, {{ $groupFieldIndex }})"
        >
            <div class="ml-2 w-0.5 h-3/4 bg-blue-400 rounded-md" style="opacity:0;transition:opacity 0.15s, height 0.15s, box-shadow 0.15s"></div>
        </div>

        <div
            class="absolute inset-y-0 right-0 w-2/5 z-10 flex items-center justify-end rounded-r-md"
            :style="{{ $groupZoneActiveJs }} ? 'pointer-events:auto' : 'pointer-events:none'"
            @dragover.prevent="$store.formBuilder.showInsertMarker($el)"
            @dragleave="$store.formBuilder.hideInsertMarker($el)"
            @drop.prevent="$store.formBuilder.handleGroupFieldInsertDrop($el, {{ $groupRowIndex }}, {{ $groupRowInnerIndex }}, {{ $groupFieldIndex + 1 }})"
        >
            <div class="mr-2 w-0.5 h-3/4 bg-blue-400 rounded-md" style="opacity:0;transition:opacity 0.15s, height 0.15s, box-shadow 0.15s"></div>
        </div>

        <div
            class="relative z-20 mb-2 flex items-center justify-between gap-3 rounded-md border border-dashed border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-500 cursor-move active:cursor-grabbing select-none transition-colors hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600"
            draggable="true"
            title="Drag to move field"
            @dragstart.stop="dragging = true; $store.formBuilder.startGroupCanvasDrag({{ $groupRowIndex }}, {{ $groupRowInnerIndex }}, {{ $groupFieldIndex }}); $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', 'group-field-{{ $groupField->getId() }}')"
            @dragend.stop="dragging = false; $store.formBuilder.endDrag()"
        >
            <div class="flex items-center gap-2 min-w-0">
                <span class="text-sm leading-none">⠿</span>
                <span class="truncate font-medium">Move field: {{ $groupField->getLabel() }}</span>
            </div>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="shrink-0 text-gray-300 hover:text-blue-500 transition-colors text-lg leading-none cursor-pointer"
                    title="Field settings"
                    aria-label="Field settings"
                    aria-description="Opens the settings panel for this field"
                    aria-controls="field-settings-panel"
                    @click.stop="$store.formBuilder.editField(null, {{ $groupFieldIndex }}, {{ $groupRowIndex }}, {{ $groupRowInnerIndex }})"
                    @mousedown.stop
                >
                    @include('meros::toolbox.svgs.settings')
                </button>
                @if($groupField->getType() === 'repeater')
                    <button
                        type="button"
                        class="shrink-0 text-gray-300 hover:text-blue-500 transition-colors text-lg leading-none cursor-pointer"
                        title="Edit repeater fields"
                        aria-label="Edit repeater fields"
                        aria-description="Opens the repeater field editor for this repeater field"
                        @click.stop="$store.formBuilder.openRepeaterFieldSettings(null, {{ $groupFieldIndex }}, {{ $groupRowIndex }}, {{ $groupRowInnerIndex }})"
                        @mousedown.stop
                    >
                        @include('meros::toolbox.svgs.wrench')
                    </button>
                @endif
                <button
                    type="button"
                    class="shrink-0 text-gray-300 hover:text-blue-500 transition-colors text-xl leading-none cursor-pointer"
                    title="Field conditions"
                    aria-label="Field conditions"
                    aria-description="Opens the field conditions panel for this field"
                    @click.stop="$store.formBuilder.editFieldConditions(null, {{ $groupFieldIndex}}, {{ $groupRowIndex }}, {{ $groupRowInnerIndex }})"
                    @mousedown.stop
                >
                    @include('meros::toolbox.svgs.conditions-eye')
                </button>
                <button
                    type="button"
                    class="shrink-0 text-gray-300 hover:text-red-500 transition-colors text-lg leading-none cursor-pointer"
                    title="Remove field"
                    aria-label="Remove field"
                    aria-description="Removes this field from the form"
                    @click.stop="$store.formBuilder.removeField(null, {{ $groupFieldIndex }}, {{ $groupRowIndex }}, {{ $groupRowInnerIndex }})"
                    @mousedown.stop
                >
                    @include('meros::toolbox.svgs.remove')
                </button>
            </div>
        </div>
        {!! $groupField->render() !!}
    </div>
@else
    @php
        $rowHasSpace = count($rowFields) < 3;
        $rowHasSpaceJs = $rowHasSpace ? 'true' : 'false';
        $zoneActiveJs = "\$store.formBuilder.isDragging && !(\$store.formBuilder.isCanvasDrag && \$store.formBuilder.sourceRowIndex === {$fieldRowIndex} && \$store.formBuilder.sourceFieldIndex === {$fieldIndex}) && (\$store.formBuilder.isCanvasDrag ? (\$store.formBuilder.sourceRowIndex === {$fieldRowIndex} || {$rowHasSpaceJs}) : {$rowHasSpaceJs})";
    @endphp
    <div
        x-data="{ dragging: false }"
        :class="dragging ? 'ring-2 ring-blue-400 border-blue-400 opacity-60' : ''"
        class="relative flex-1 min-w-0 bg-white border border-gray-200 rounded-md shadow-sm"
        data-field-location="{{ $fieldRowIndex }}-{{ $fieldIndex }}"
        wire:key="{{ $field->getId() }}"
    >
        <div
            class="absolute inset-y-0 left-0 w-2/5 z-10 flex items-center rounded-l-md"
            :style="{{ $zoneActiveJs }} ? 'pointer-events:auto' : 'pointer-events:none'"
            @dragover.prevent="$store.formBuilder.showInsertMarker($el)"
            @dragleave="$store.formBuilder.hideInsertMarker($el)"
            @drop.prevent="$store.formBuilder.handleTopFieldInsertDrop($el, {{ $fieldRowIndex }}, {{ $fieldIndex }})"
        >
            <div class="ml-2 w-0.5 h-3/4 bg-blue-400 rounded-md" style="opacity:0;transition:opacity 0.15s, height 0.15s, box-shadow 0.15s"></div>
        </div>

        <div
            class="absolute inset-y-0 right-0 w-2/5 z-10 flex items-center justify-end rounded-r-md"
            :style="{{ $zoneActiveJs }} ? 'pointer-events:auto' : 'pointer-events:none'"
            @dragover.prevent="$store.formBuilder.showInsertMarker($el)"
            @dragleave="$store.formBuilder.hideInsertMarker($el)"
            @drop.prevent="$store.formBuilder.handleTopFieldInsertDrop($el, {{ $fieldRowIndex }}, {{ $fieldIndex + 1 }})"
        >
            <div class="mr-2 w-0.5 h-3/4 bg-blue-400" style="opacity:0;transition:opacity 0.15s, height 0.15s, box-shadow 0.15s"></div>
        </div>

        <div class="p-4">
            <div
                class="relative z-20 mb-3 flex items-center justify-between gap-3 rounded-md border border-dashed border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-500 cursor-move active:cursor-grabbing select-none transition-colors hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600"
                draggable="true"
                title="Drag to move field"
                aria-label="Move field"
                aria-description="Drag to move this field to a different position in the form"
                :aria-grabbed="dragging ? 'true' : 'false'"
                @dragstart.stop="dragging = true; $store.formBuilder.startCanvasDrag({{ $fieldRowIndex }}, {{ $fieldIndex }}); $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', 'field-{{ $field->getId() }}')"
                @dragend.stop="dragging = false; $store.formBuilder.endDrag()"
            >
                <div class="flex items-center gap-2 min-w-0">
                    <span class="text-sm leading-none">⠿</span>
                    <span class="truncate font-medium">Move field: {{ $field->getLabel() }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="shrink-0 text-gray-300 hover:text-blue-500 transition-colors text-xl leading-none cursor-pointer"
                        title="Field settings"
                        aria-label="Field settings"
                        aria-description="Opens the settings panel for this field"
                        aria-controls="field-settings-panel"
                        @click.stop="$store.formBuilder.editField({{ $fieldRowIndex }}, {{ $fieldIndex }})"
                        @mousedown.stop
                    >
                        @include('meros::toolbox.svgs.settings')
                    </button>
                    @if($field->getType() === 'repeater')
                        <button
                            type="button"
                            class="shrink-0 text-gray-300 hover:text-blue-500 transition-colors text-xl leading-none cursor-pointer"
                            title="Edit repeater fields"
                            aria-label="Edit repeater fields"
                            aria-description="Opens the repeater field editor for this repeater field"
                            @click.stop="$store.formBuilder.openRepeaterFieldSettings({{ $fieldRowIndex }}, {{ $fieldIndex }})"
                            @mousedown.stop
                        >
                            @include('meros::toolbox.svgs.wrench')
                        </button>
                    @endif
                    <button
                        type="button"
                        class="shrink-0 text-gray-300 hover:text-blue-500 transition-colors text-xl leading-none cursor-pointer"
                        title="Field conditions"
                        aria-label="Field conditions"
                        aria-description="Opens the field conditions panel for this field"
                        @click.stop="$store.formBuilder.editFieldConditions({{ $fieldRowIndex }}, {{ $fieldIndex }})"
                        @mousedown.stop
                    >
                        @include('meros::toolbox.svgs.conditions-eye')
                    </button>
                    <button
                        type="button"
                        class="shrink-0 text-gray-300 hover:text-red-500 transition-colors text-xl leading-none cursor-pointer"
                        title="Remove field"
                        aria-label="Remove field"
                        aria-description="Removes this field from the form"
                        @click.stop="$store.formBuilder.removeField({{ $fieldRowIndex }}, {{ $fieldIndex }})"
                        @mousedown.stop
                    >
                        @include('meros::toolbox.svgs.remove')
                    </button>
                </div>
            </div>
            {!! $field->render() !!}
        </div>
    </div>
@endif
