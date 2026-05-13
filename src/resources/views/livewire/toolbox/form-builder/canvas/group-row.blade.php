@php
    $groupPayload = $canvasRow['group'];
    $groupRowIndex = $canvasRow['rowIndex'];
@endphp

<div class="mb-4 border border-gray-300 rounded-lg bg-gray-50" wire:key="group-{{ $groupPayload['id'] }}">
    <div
        class="flex items-center justify-between px-4 py-3 border-b border-gray-200 cursor-move"
        draggable="true"
        @dragstart="$event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('application/x-meros-group-row', '{{ $groupRowIndex }}')"
    >
        <div>
            <div class="font-semibold text-gray-800">⠿ {{ $groupPayload['title'] }}</div>
            @if(!empty($groupPayload['description']))
                <div class="text-xs text-gray-500 mt-0.5">{{ $groupPayload['description'] }}</div>
            @endif
        </div>
        <button
            type="button"
            wire:click="removeGroup({{ $groupRowIndex }})"
            class="text-sm text-red-500 hover:text-red-700"
        >
            Remove section
        </button>
    </div>

    <div class="p-3">
        @if(empty($groupPayload['rows']))
            <div
                class="h-16 border-2 border-dashed border-gray-300 rounded-lg flex items-center justify-center text-xs text-gray-500"
                @dragover.prevent="$store.formDrag.itemKind === 'field' ? $el.classList.add('border-blue-400', 'bg-blue-50', 'text-blue-500') : null"
                @dragleave="$el.classList.remove('border-blue-400', 'bg-blue-50', 'text-blue-500')"
                @drop.prevent="$el.classList.remove('border-blue-400', 'bg-blue-50', 'text-blue-500'); $store.formDrag.itemKind !== 'field' ? null : ($store.formDrag.sourceGroupRowIndex === {{ $groupRowIndex }} ? $wire.moveFieldToGroupNewRow({{ $groupRowIndex }}, $store.formDrag.sourceGroupInnerRowIndex, $store.formDrag.sourceFieldIndex, -1) : ($store.formDrag.sourceGroupRowIndex !== null ? $wire.moveFieldBetweenGroupsToNewRow($store.formDrag.sourceGroupRowIndex, $store.formDrag.sourceGroupInnerRowIndex, $store.formDrag.sourceFieldIndex, {{ $groupRowIndex }}, -1) : ($store.formDrag.isCanvasDrag ? $wire.moveFieldToGroupNewRowFromTopLevel($store.formDrag.sourceRowIndex, $store.formDrag.sourceFieldIndex, {{ $groupRowIndex }}, -1) : $wire.addFieldToGroupNewRow({{ $groupRowIndex }}, -1, $store.formDrag.itemHandle))))"
            >
                Drag fields here to start this section
            </div>
        @else
            <div
                class="h-2 rounded-sm mb-1 transition-all duration-150"
                @dragover.prevent="$store.formDrag.itemKind === 'field' ? $store.formDrag.showRowGap($el) : null"
                @dragleave="$store.formDrag.hideRowGap($el)"
                @drop.prevent="$store.formDrag.hideRowGap($el); $store.formDrag.itemKind !== 'field' ? null : ($store.formDrag.sourceGroupRowIndex === {{ $groupRowIndex }} ? $wire.moveFieldToGroupNewRow({{ $groupRowIndex }}, $store.formDrag.sourceGroupInnerRowIndex, $store.formDrag.sourceFieldIndex, -1) : ($store.formDrag.sourceGroupRowIndex !== null ? $wire.moveFieldBetweenGroupsToNewRow($store.formDrag.sourceGroupRowIndex, $store.formDrag.sourceGroupInnerRowIndex, $store.formDrag.sourceFieldIndex, {{ $groupRowIndex }}, -1) : ($store.formDrag.isCanvasDrag ? $wire.moveFieldToGroupNewRowFromTopLevel($store.formDrag.sourceRowIndex, $store.formDrag.sourceFieldIndex, {{ $groupRowIndex }}, -1) : $wire.addFieldToGroupNewRow({{ $groupRowIndex }}, -1, $store.formDrag.itemHandle))))"
            ></div>

            @foreach($groupPayload['rows'] as $groupRowInnerIndex => $groupRowFields)
                <div class="flex gap-3 mb-1">
                    @foreach($groupRowFields as $groupFieldIndex => $groupField)
                        @include('meros::livewire.toolbox.form-builder.canvas.field', [
                            'scope' => 'group',
                            'groupRowFields' => $groupRowFields,
                            'groupRowIndex' => $groupRowIndex,
                            'groupRowInnerIndex' => $groupRowInnerIndex,
                            'groupFieldIndex' => $groupFieldIndex,
                            'groupField' => $groupField,
                        ])
                    @endforeach
                </div>

                <div
                    class="h-2 rounded-sm mb-1 transition-all duration-150"
                    @dragover.prevent="$store.formDrag.itemKind === 'field' ? $store.formDrag.showRowGap($el) : null"
                    @dragleave="$store.formDrag.hideRowGap($el)"
                    @drop.prevent="$store.formDrag.hideRowGap($el); $store.formDrag.itemKind !== 'field' ? null : ($store.formDrag.sourceGroupRowIndex === {{ $groupRowIndex }} ? $wire.moveFieldToGroupNewRow({{ $groupRowIndex }}, $store.formDrag.sourceGroupInnerRowIndex, $store.formDrag.sourceFieldIndex, {{ $groupRowInnerIndex }}) : ($store.formDrag.sourceGroupRowIndex !== null ? $wire.moveFieldBetweenGroupsToNewRow($store.formDrag.sourceGroupRowIndex, $store.formDrag.sourceGroupInnerRowIndex, $store.formDrag.sourceFieldIndex, {{ $groupRowIndex }}, {{ $groupRowInnerIndex }}) : ($store.formDrag.isCanvasDrag ? $wire.moveFieldToGroupNewRowFromTopLevel($store.formDrag.sourceRowIndex, $store.formDrag.sourceFieldIndex, {{ $groupRowIndex }}, {{ $groupRowInnerIndex }}) : $wire.addFieldToGroupNewRow({{ $groupRowIndex }}, {{ $groupRowInnerIndex }}, $store.formDrag.itemHandle))))"
                ></div>
            @endforeach
        @endif
    </div>
</div>
