@if(($scope ?? 'top') === 'group')
    @php
        $groupRowHasSpace = count($groupRowFields) < 3;
        $groupRowHasSpaceJs = $groupRowHasSpace ? 'true' : 'false';
        $groupZoneActiveJs = "\$store.formDrag.isDragging && \$store.formDrag.itemKind === 'field' && !(\$store.formDrag.sourceGroupRowIndex === {$groupRowIndex} && \$store.formDrag.sourceGroupInnerRowIndex === {$groupRowInnerIndex} && \$store.formDrag.sourceFieldIndex === {$groupFieldIndex}) && ((\$store.formDrag.sourceGroupRowIndex === {$groupRowIndex} && \$store.formDrag.sourceGroupInnerRowIndex === {$groupRowInnerIndex}) || {$groupRowHasSpaceJs})";
    @endphp
    <div
        class="relative flex-1 min-w-0 bg-white border border-gray-200 rounded-md shadow-sm p-3"
        x-data="{ dragging: false }"
        :class="dragging ? 'ring-2 ring-blue-400 border-blue-400 opacity-60' : ''"
        data-field-location="group-{{ $groupRowIndex }}-{{ $groupRowInnerIndex }}-{{ $groupFieldIndex }}"
        wire:key="{{ $groupField->getId() }}-v{{ $fieldVersions[$groupField->getId()] ?? 0 }}"
    >
        <div
            class="absolute inset-y-0 left-0 w-2/5 z-10 flex items-center rounded-l-md"
            :style="{{ $groupZoneActiveJs }} ? 'pointer-events:auto' : 'pointer-events:none'"
            @dragover.prevent="$store.formDrag.showInsertMarker($el)"
            @dragleave="$store.formDrag.hideInsertMarker($el)"
            @drop.prevent="$store.formDrag.handleGroupFieldInsertDrop($el, $wire, {{ $groupRowIndex }}, {{ $groupRowInnerIndex }}, {{ $groupFieldIndex }})"
        >
            <div class="ml-2 w-0.5 h-3/4 bg-blue-400 rounded-md" style="opacity:0;transition:opacity 0.15s, height 0.15s, box-shadow 0.15s"></div>
        </div>

        <div
            class="absolute inset-y-0 right-0 w-2/5 z-10 flex items-center justify-end rounded-r-md"
            :style="{{ $groupZoneActiveJs }} ? 'pointer-events:auto' : 'pointer-events:none'"
            @dragover.prevent="$store.formDrag.showInsertMarker($el)"
            @dragleave="$store.formDrag.hideInsertMarker($el)"
            @drop.prevent="$store.formDrag.handleGroupFieldInsertDrop($el, $wire, {{ $groupRowIndex }}, {{ $groupRowInnerIndex }}, {{ $groupFieldIndex + 1 }})"
        >
            <div class="mr-2 w-0.5 h-3/4 bg-blue-400 rounded-md" style="opacity:0;transition:opacity 0.15s, height 0.15s, box-shadow 0.15s"></div>
        </div>

        <div
            class="relative z-20 mb-2 flex items-center justify-between gap-3 rounded-md border border-dashed border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-500 cursor-move active:cursor-grabbing select-none transition-colors hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600"
            draggable="true"
            title="Drag to move field"
            @dragstart.stop="dragging = true; $store.formDrag.startGroupCanvasDrag({{ $groupRowIndex }}, {{ $groupRowInnerIndex }}, {{ $groupFieldIndex }}); $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', 'group-field-{{ $groupField->getId() }}')"
            @dragend.stop="dragging = false; $store.formDrag.endDrag()"
        >
            <div class="flex items-center gap-2 min-w-0">
                <span class="text-sm leading-none">⠿</span>
                <span class="truncate font-medium">Move field: {{ $groupField->getLabel() }}</span>
            </div>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    wire:click="editGroupField({{ $groupRowIndex }}, {{ $groupRowInnerIndex }}, {{ $groupFieldIndex }})"
                    class="shrink-0 text-gray-300 hover:text-blue-500 transition-colors text-lg leading-none"
                    title="Field settings"
                    @mousedown.stop
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5">
                        <path fill-rule="evenodd" d="M11.828 2.25c-.916 0-1.699.663-1.85 1.567l-.091.549a.798.798 0 0 1-.517.608 7.45 7.45 0 0 0-.478.198.798.798 0 0 1-.796-.064l-.453-.324a1.875 1.875 0 0 0-2.416.2l-.243.243a1.875 1.875 0 0 0-.2 2.416l.324.453a.798.798 0 0 1 .064.796 7.448 7.448 0 0 0-.198.478.798.798 0 0 1-.608.517l-.55.092a1.875 1.875 0 0 0-1.566 1.849v.344c0 .916.663 1.699 1.567 1.85l.549.091c.281.047.508.25.608.517.06.162.127.321.198.478a.798.798 0 0 1-.064.796l-.324.453a1.875 1.875 0 0 0 .2 2.416l.243.243c.648.648 1.67.733 2.416.2l.453-.324a.798.798 0 0 1 .796-.064c.157.071.316.137.478.198.267.1.47.327.517.608l.092.55c.15.903.932 1.566 1.849 1.566h.344c.916 0 1.699-.663 1.85-1.567l.091-.549a.798.798 0 0 1 .517-.608 7.52 7.52 0 0 0 .478-.198.798.798 0 0 1 .796.064l.453.324a1.875 1.875 0 0 0 2.416-.2l.243-.243c.648-.648.733-1.67.2-2.416l-.324-.453a.798.798 0 0 1-.064-.796c.071-.157.137-.316.198-.478.1-.267.327-.47.608-.517l.55-.091a1.875 1.875 0 0 0 1.566-1.85v-.344c0-.916-.663-1.699-1.567-1.85l-.549-.091a.798.798 0 0 1-.608-.517 7.507 7.507 0 0 0-.198-.478.798.798 0 0 1 .064-.796l.324-.453a1.875 1.875 0 0 0-.2-2.416l-.243-.243a1.875 1.875 0 0 0-2.416-.2l-.453.324a.798.798 0 0 1-.796.064 7.462 7.462 0 0 0-.478-.198.798.798 0 0 1-.517-.608l-.091-.55a1.875 1.875 0 0 0-1.85-1.566h-.344ZM12 15.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Z" clip-rule="evenodd" />
                    </svg>
                </button>
                <button
                    type="button"
                    wire:click="removeFieldFromGroup({{ $groupRowIndex }}, {{ $groupRowInnerIndex }}, {{ $groupFieldIndex }})"
                    class="shrink-0 text-gray-300 hover:text-red-500 transition-colors text-lg leading-none"
                    @click="window.beforeRemoveGroupField({{ $groupRowIndex }}, {{ $groupRowInnerIndex }}, {{ $groupFieldIndex }})"
                    @mousedown.stop
                >&times;</button>
            </div>
        </div>
        @if(($groupField->handle ?? null) === 'repeater')
            {!! $groupField->render(true, true, ['groupRowIndex' => $groupRowIndex, 'rowIndex' => $groupRowInnerIndex, 'fieldIndex' => $groupFieldIndex]) !!}
        @else
            <div @change="$wire.updateFieldDefaultValue({{ $groupRowIndex }}, {{ $groupRowInnerIndex }}, {{ $groupFieldIndex }}, $event.target.multiple ? Array.from($event.target.options).filter(o => o.selected).map(o => o.value) : ($event.target.type === 'checkbox' ? $event.target.checked : $event.target.value))">
                {!! $groupField->render() !!}
            </div>
        @endif
    </div>
@else
    @php
        $rowHasSpace = count($rowFields) < 3;
        $rowHasSpaceJs = $rowHasSpace ? 'true' : 'false';
        $zoneActiveJs = "\$store.formDrag.isDragging && !(\$store.formDrag.isCanvasDrag && \$store.formDrag.sourceRowIndex === {$fieldRowIndex} && \$store.formDrag.sourceFieldIndex === {$fieldIndex}) && (\$store.formDrag.isCanvasDrag ? (\$store.formDrag.sourceRowIndex === {$fieldRowIndex} || {$rowHasSpaceJs}) : {$rowHasSpaceJs})";
    @endphp
    <div
        x-data="{ dragging: false }"
        :class="dragging ? 'ring-2 ring-blue-400 border-blue-400 opacity-60' : ''"
        class="relative flex-1 min-w-0 bg-white border border-gray-200 rounded-md shadow-sm"
        data-field-location="{{ $fieldRowIndex }}-{{ $fieldIndex }}"
        wire:key="{{ $field->getId() }}-v{{ $fieldVersions[$field->getId()] ?? 0 }}"
    >
        <div
            class="absolute inset-y-0 left-0 w-2/5 z-10 flex items-center rounded-l-md"
            :style="{{ $zoneActiveJs }} ? 'pointer-events:auto' : 'pointer-events:none'"
            @dragover.prevent="$store.formDrag.showInsertMarker($el)"
            @dragleave="$store.formDrag.hideInsertMarker($el)"
            @drop.prevent="$store.formDrag.handleTopFieldInsertDrop($el, $wire, {{ $fieldRowIndex }}, {{ $fieldIndex }})"
        >
            <div class="ml-2 w-0.5 h-3/4 bg-blue-400 rounded-md" style="opacity:0;transition:opacity 0.15s, height 0.15s, box-shadow 0.15s"></div>
        </div>

        <div
            class="absolute inset-y-0 right-0 w-2/5 z-10 flex items-center justify-end rounded-r-md"
            :style="{{ $zoneActiveJs }} ? 'pointer-events:auto' : 'pointer-events:none'"
            @dragover.prevent="$store.formDrag.showInsertMarker($el)"
            @dragleave="$store.formDrag.hideInsertMarker($el)"
            @drop.prevent="$store.formDrag.handleTopFieldInsertDrop($el, $wire, {{ $fieldRowIndex }}, {{ $fieldIndex + 1 }})"
        >
            <div class="mr-2 w-0.5 h-3/4 bg-blue-400" style="opacity:0;transition:opacity 0.15s, height 0.15s, box-shadow 0.15s"></div>
        </div>

        <div class="p-4">
            <div
                class="relative z-20 mb-3 flex items-center justify-between gap-3 rounded-md border border-dashed border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-500 cursor-move active:cursor-grabbing select-none transition-colors hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600"
                draggable="true"
                title="Drag to move field"
                @dragstart.stop="dragging = true; $store.formDrag.startCanvasDrag({{ $fieldRowIndex }}, {{ $fieldIndex }}); $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', 'field-{{ $field->getId() }}')"
                @dragend.stop="dragging = false; $store.formDrag.endDrag()"
            >
                <div class="flex items-center gap-2 min-w-0">
                    <span class="text-sm leading-none">⠿</span>
                    <span class="truncate font-medium">Move field: {{ $field->getLabel() }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="editField({{ $fieldRowIndex }}, {{ $fieldIndex }})"
                        class="shrink-0 text-gray-300 hover:text-blue-500 transition-colors text-xl leading-none"
                        title="Field settings"
                        @mousedown.stop
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6 cursor-pointer">
                            <path fill-rule="evenodd" d="M11.828 2.25c-.916 0-1.699.663-1.85 1.567l-.091.549a.798.798 0 0 1-.517.608 7.45 7.45 0 0 0-.478.198.798.798 0 0 1-.796-.064l-.453-.324a1.875 1.875 0 0 0-2.416.2l-.243.243a1.875 1.875 0 0 0-.2 2.416l.324.453a.798.798 0 0 1 .064.796 7.448 7.448 0 0 0-.198.478.798.798 0 0 1-.608.517l-.55.092a1.875 1.875 0 0 0-1.566 1.849v.344c0 .916.663 1.699 1.567 1.85l.549.091c.281.047.508.25.608.517.06.162.127.321.198.478a.798.798 0 0 1-.064.796l-.324.453a1.875 1.875 0 0 0 .2 2.416l.243.243c.648.648 1.67.733 2.416.2l.453-.324a.798.798 0 0 1 .796-.064c.157.071.316.137.478.198.267.1.47.327.517.608l.092.55c.15.903.932 1.566 1.849 1.566h.344c.916 0 1.699-.663 1.85-1.567l.091-.549a.798.798 0 0 1 .517-.608 7.52 7.52 0 0 0 .478-.198.798.798 0 0 1 .796.064l.453.324a1.875 1.875 0 0 0 2.416-.2l.243-.243c.648-.648.733-1.67.2-2.416l-.324-.453a.798.798 0 0 1-.064-.796c.071-.157.137-.316.198-.478.1-.267.327-.47.608-.517l.55-.091a1.875 1.875 0 0 0 1.566-1.85v-.344c0-.916-.663-1.699-1.567-1.85l-.549-.091a.798.798 0 0 1-.608-.517 7.507 7.507 0 0 0-.198-.478.798.798 0 0 1 .064-.796l.324-.453a1.875 1.875 0 0 0-.2-2.416l-.243-.243a1.875 1.875 0 0 0-2.416-.2l-.453.324a.798.798 0 0 1-.796.064 7.462 7.462 0 0 0-.478-.198.798.798 0 0 1-.517-.608l-.091-.55a1.875 1.875 0 0 0-1.85-1.566h-.344ZM12 15.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        wire:click="removeField({{ $fieldRowIndex }}, {{ $fieldIndex }})"
                        class="shrink-0 text-gray-300 hover:text-red-500 transition-colors text-xl leading-none"
                        title="Remove field"
                        @click="window.beforeRemoveField({{ $fieldRowIndex }}, {{ $fieldIndex }})"
                        @mousedown.stop
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6 cursor-pointer">
                            <path fill-rule="evenodd" d="M5.47 5.47a.75.75 0 0 1 1.06 0L12 10.94l5.47-5.47a.75.75 0 1 1 1.06 1.06L13.06 12l5.47 5.47a.75.75 0 1 1-1.06 1.06L12 13.06l-5.47 5.47a.75.75 0 0 1-1.06-1.06L10.94 12 5.47 6.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </div>
                @if(($field->handle ?? null) === 'repeater')
                {!! $field->render(true, true, ['groupRowIndex' => null, 'rowIndex' => $fieldRowIndex, 'fieldIndex' => $fieldIndex]) !!}
            @else
                <div @change="$wire.updateFieldDefaultValue(null, {{ $fieldRowIndex }}, {{ $fieldIndex }}, $event.target.multiple ? Array.from($event.target.options).filter(o => o.selected).map(o => o.value) : ($event.target.type === 'checkbox' ? $event.target.checked : $event.target.value))">
                    {!! $field->render() !!}
                </div>
            @endif
        </div>
    </div>
@endif
