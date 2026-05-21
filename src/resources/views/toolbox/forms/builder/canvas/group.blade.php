<div class="mb-4 border border-gray-300 rounded-lg bg-gray-50" wire:key="group-{{ $groupPayload['id'] }}">
    <div
        class="flex items-center justify-between px-4 py-3 border-b border-gray-200 cursor-move"
        draggable="true"
        @dragstart="$store.formBuilder.endDrag(); $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('application/x-meros-group-row', '{{ $groupRowIndex }}')"
        @dragend="$store.formBuilder.endDrag()"
    >
        <div>
            <div class="font-semibold text-gray-800">⠿ {{ $groupPayload['title'] }}</div>
            @if(!empty($groupPayload['description']))
                <div class="text-xs text-gray-500 mt-0.5">{{ $groupPayload['description'] }}</div>
            @endif
        </div>
        <button
            type="button"
            @click.stop="$store.formBuilder.removeGroup({{ $groupRowIndex }})"
            class="text-sm text-red-500 hover:text-red-700 cursor-pointer"
            title="Remove section"
        >
            Remove section
        </button>
    </div>

    <div class="p-3">
        @if(empty($groupPayload['rows']))
            {{-- Default drop zone for new group elements --}}
            <div
                class="h-16 border-2 border-dashed border-gray-300 rounded-lg flex items-center justify-center text-xs text-gray-500"
                @dragover.prevent="$store.formBuilder.showFieldDropHighlight($el)"
                @dragleave="$store.formBuilder.hideFieldDropHighlight($el)"
                @drop.prevent="$store.formBuilder.handleGroupEmptyDrop($el, {{ $groupRowIndex }}, -1)"
            >
                Drag fields here to start this section
            </div>
        @else
            {{-- Group row gap before first row --}}
            @include('meros::toolbox.forms.builder.canvas.row-drop-zone', [
                'isGroupRow'         => true,
                'groupRowIndex'      => $groupRowIndex,
                'groupRowInnerIndex' => -1,
            ])

            @foreach($groupPayload['rows'] as $groupRowInnerIndex => $groupRow)
                @php
                    $groupRowFields = $groupRow['fields'] ?? [];
                @endphp

                <div class="flex gap-3 mb-1">
                    @foreach($groupRowFields as $groupFieldIndex => $groupField)
                        @include('meros::toolbox.forms.builder.canvas.field', [
                            'scope'              => 'group',
                            'groupRowFields'     => $groupRowFields,
                            'groupRowIndex'      => $groupRowIndex,
                            'groupRowInnerIndex' => $groupRowInnerIndex,
                            'groupFieldIndex'    => $groupFieldIndex,
                            'groupField'         => $groupField,
                        ])
                    @endforeach
                </div>

                {{-- Group row gap after current row --}}
                @include('meros::toolbox.forms.builder.canvas.row-drop-zone', [
                    'isGroupRow'         => true,
                    'groupRowIndex'      => $groupRowIndex,
                    'groupRowInnerIndex' => $groupRowInnerIndex,
                ])
            @endforeach
        @endif
    </div>
</div>