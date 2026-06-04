<div class="mb-4 border border-gray-300 rounded-lg bg-gray-50" wire:key="group-{{ $groupPayload['id'] }}">
    <div
        class="flex items-center justify-between px-4 py-3 border-b border-gray-200 cursor-move"
        draggable="true"
        @dragstart="$store.formBuilder.endDrag(); $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('application/x-meros-group-row', '{{ $groupRowIndex }}')"
        @dragend="$store.formBuilder.endDrag()"
    >
        <div class="meros-form-group-header">
            <h3 class="font-semibold text-gray-800">⠿ {{ $groupPayload['title'] }}</h3>
            @if(!empty($groupPayload['description']))
                <p>{!! $this->renderQuillContent($groupPayload['description']) !!}</p>
            @endif
        </div>
        <div class="flex items-center gap-2">
            <button
                type="button"
                class="shrink-0 text-gray-300 hover:text-blue-500 transition-colors text-xl leading-none cursor-pointer"
                @click.stop="$store.formBuilder.editGroup({{ $groupRowIndex }})"
                title="Edit section"
            >
                @include('meros::toolbox.svgs.settings')
            </button>
            <button
                type="button"
                class="shrink-0 text-gray-300 hover:text-red-500 transition-colors text-xl leading-none cursor-pointer"
                @click.stop="$store.formBuilder.removeGroup({{ $groupRowIndex }})"
                title="Remove section"
            >
                @include('meros::toolbox.svgs.remove')
            </button>
        </div>
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
                'isRepeaterField'    => false,
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
                    'isRepeaterField'    => false,
                    'groupRowIndex'      => $groupRowIndex,
                    'groupRowInnerIndex' => $groupRowInnerIndex,
                ])
            @endforeach
        @endif
    </div>
</div>