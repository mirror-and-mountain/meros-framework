<div
    x-data="canvasfield('{{ $field->getId() }}')"
    class="relative flex-1 min-w-0 bg-white border border-gray-300 rounded-md shadow-sm"
    wire:key="{{ $field->getId() }}"
>
    <div class="p-4">
        <div
            class="relative z-20 mb-3 flex items-center justify-between gap-3 rounded-md border border-dashed border-gray-300 bg-gray-100 px-3 py-2 text-xs text-gray-700 cursor-move active:cursor-grabbing select-none transition-colors duration-150 motion-reduce:transition-none hover:border-blue-500 hover:bg-blue-50 hover:text-blue-800"
            draggable="true"
            data-field-id="{{ $field->getId() }}"
            data-row-index="{{ $fieldRowIndex }}"
            data-field-position="{{ $fieldPosition }}"
            title="Drag to move field"
            aria-label="Move field"
            aria-description="Drag to move this field to a different position in the form"
            :aria-grabbed="moving ? 'true' : 'false'"
            @dragstart="handleDragStart($event)"
            @dragend="moving = false"
        >
            <div class="flex items-center gap-2 min-w-0">
                <span class="text-sm leading-none">⠿</span>
                <span class="truncate font-medium">Move field: {{ $field->getLabel() }}</span>
            </div>
            <div class="flex items-center gap-2">
                <div>
                    <button
                        type="button"
                        class="shrink-0 text-gray-500 hover:text-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-1 rounded-sm transition-colors text-xl leading-none cursor-pointer"
                        title="Field settings"
                        aria-label="Field settings"
                        aria-description="Opens the settings panel for this field"
                        aria-controls="field-settings-panel"
                        @click="$dispatch('mforms:open-field-settings', { fieldId: '{{ $field->getId() }}', rowIndex: '{{ $fieldRowIndex }}', groupId: '{{ $field->getGroupId() }}' })"
                    >
                        @include('meros::toolbox.svgs.settings')
                    </button>
                </div>
                @if($field->getType() === 'repeater')
                    <div>
                        <button
                            type="button"
                            class="shrink-0 text-gray-500 hover:text-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-1 rounded-sm transition-colors text-xl leading-none cursor-pointer"
                            title="Edit repeater fields"
                            aria-label="Edit repeater fields"
                            aria-description="Opens the repeater field editor for this repeater field"
                            wire:click="openRepeaterEditor('{{ $field->getId() }}', '{{ $fieldRowIndex }}', '{{ $field->getGroupId() }}')"
                        >
                            @include('meros::toolbox.svgs.wrench')
                        </button>
                    </div>
                @endif
                <div>
                    <button
                        type="button"
                        class="shrink-0 text-gray-500 hover:text-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-1 rounded-sm transition-colors text-xl leading-none cursor-pointer"
                        title="Field conditions"
                        aria-label="Field conditions"
                        aria-description="Opens the field conditions panel for this field"
                    >
                        @include('meros::toolbox.svgs.conditions-eye')
                    </button>
                </div>
                <div @confirm="confirm('Are you sure you want to delete this field? This action cannot be undone.') ? $wire.removeField('{{ $field->getId() }}', '{{ (int) $rowIndex }}', '{{ $field->getGroupId() }}') : null">
                    <button
                        type="button"
                        class="shrink-0 text-gray-500 hover:text-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-1 rounded-sm transition-colors text-xl leading-none cursor-pointer"
                        title="Remove field"
                        aria-label="Remove field"
                        aria-description="Removes this field from the form"
                        @click="$dispatch('confirm')"
                    >
                        @include('meros::toolbox.svgs.remove')
                    </button>
                </div>
            </div>
        </div>
        {!! $field->render() !!}
    </div>
</div>

