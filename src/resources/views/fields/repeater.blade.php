<div class="{{ $field->classList() }} rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">
    @if($location)
        @php
            $rawRepeaterValue = is_array($field->getValue()) ? $field->getValue() : [];
            $repeaterStateSignature = md5(json_encode([
                'location' => $location,
                'value' => $rawRepeaterValue,
                'columns' => $field->getFieldNames(),
            ]));
        @endphp
        <div
            class="overflow-x-auto"
            wire:key="canvas-repeater-{{ $location['groupRowIndex'] ?? 'top' }}-{{ $location['rowIndex'] ?? 'x' }}-{{ $location['fieldIndex'] ?? 'x' }}-{{ $repeaterStateSignature }}"
            x-data="{ isDraggingRow: false, draggingRowIndex: null }"
        >
            <table class="meros-repeater-table min-w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="w-10 px-3 py-2 text-left text-xs font-semibold text-gray-500">Move</th>
                        @foreach($field->getFieldLabels() as $label)
                            <th class="meros-repeater-data-header px-3 py-2 text-left text-xs font-semibold text-gray-500">{{ $label }}</th>
                        @endforeach
                        <th class="w-24 px-3 py-2 text-right text-xs font-semibold text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @if(!empty($rows))
                        <tr>
                            <td colspan="{{ count($field->getFieldNames()) + 2 }}" class="p-0">
                                <div
                                    class="h-0 rounded-sm transition-all duration-150"
                                    :class="isDraggingRow ? 'h-2' : 'h-0'"
                                    @dragover.prevent="$store.formDrag.handleFieldRepeaterRowGapDragOver($el)"
                                    @dragleave="$store.formDrag.hideRowGap($el)"
                                    @drop.prevent="$store.formDrag.handleFieldRepeaterRowGapDrop($event, $el, $wire, {{ $location['rowIndex'] ?? 'null' }}, {{ $location['fieldIndex'] ?? 'null' }}, {{ $location['groupRowIndex'] ?? 'null' }}, 0)"
                                ></div>
                            </td>
                        </tr>
                    @endif

                    @forelse($rows as $rowIndex => $row)
                        <tr
                            class="align-top"
                            wire:key="repeater-row-{{ $rowIndex }}-{{ md5(json_encode($rawRepeaterValue[$rowIndex] ?? [])) }}"
                            :class="draggingRowIndex === {{ $rowIndex }} ? 'opacity-40 bg-gray-50' : ''"
                            @dragover.prevent
                        >
                            <td class="px-3 py-3 text-gray-300 select-none text-center">
                                <button
                                    type="button"
                                    draggable="true"
                                    class="cursor-move text-gray-300 hover:text-gray-500 transition-colors"
                                    title="Drag to reorder row"
                                    @dragstart="isDraggingRow = true; draggingRowIndex = {{ $rowIndex }}; $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('application/x-meros-field-repeater-row', '{{ $rowIndex }}'); $event.dataTransfer.setData('text/plain', '{{ $rowIndex }}')"
                                    @dragend="isDraggingRow = false; draggingRowIndex = null"
                                >
                                    ☰
                                </button>
                            </td>
                            @foreach($field->getFieldNames() as $fieldName)
                                <td
                                    class="meros-repeater-data-cell px-3 py-3 align-top"
                                    @change="$wire.updateFieldRepeaterRowValue({{ $location['rowIndex'] ?? 'null' }}, {{ $location['fieldIndex'] ?? 'null' }}, {{ $location['groupRowIndex'] ?? 'null' }}, {{ $rowIndex }}, '{{ $fieldName }}', $event.target.type === 'checkbox' ? $event.target.checked : ($event.target.multiple ? Array.from($event.target.options).filter(o => o.selected).map(o => o.value) : $event.target.value))"
                                >
                                    @php
                                        $subField = $row[$fieldName] ?? null;
                                    @endphp
                                    @if($subField)
                                        {{ $subField->render(false, false) }}
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-3 py-3 text-right align-top">
                                <button
                                    type="button"
                                    wire:click="removeFieldRepeaterRow({{ $location['rowIndex'] ?? 'null' }}, {{ $location['fieldIndex'] ?? 'null' }}, {{ $location['groupRowIndex'] ?? 'null' }}, {{ $rowIndex }})"
                                    class="inline-flex items-center cursor-pointer rounded-md border border-red-200 px-3 py-1.5 text-sm font-medium text-red-600 transition-colors hover:border-red-300 hover:bg-red-50 hover:text-red-700"
                                >
                                    Remove
                                </button>
                            </td>
                        </tr>

                        <tr>
                            <td colspan="{{ count($field->getFieldNames()) + 2 }}" class="p-0">
                                <div
                                    class="h-0 rounded-sm transition-all duration-150"
                                    :class="isDraggingRow ? 'h-2' : 'h-0'"
                                    @dragover.prevent="$store.formDrag.handleFieldRepeaterRowGapDragOver($el)"
                                    @dragleave="$store.formDrag.hideRowGap($el)"
                                    @drop.prevent="$store.formDrag.handleFieldRepeaterRowGapDrop($event, $el, $wire, {{ $location['rowIndex'] ?? 'null' }}, {{ $location['fieldIndex'] ?? 'null' }}, {{ $location['groupRowIndex'] ?? 'null' }}, {{ $rowIndex + 1 }})"
                                ></div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($field->getFieldNames()) + 2 }}" class="px-3 py-4 text-sm text-gray-500 text-center">
                                No rows yet. Use "Add Row" to create repeater data.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-200 bg-gray-50 px-4 py-3">
            <button
                type="button"
                wire:click="addFieldRepeaterRow({{ $location['rowIndex'] ?? 'null' }}, {{ $location['fieldIndex'] ?? 'null' }}, {{ $location['groupRowIndex'] ?? 'null' }})"
                class="inline-flex items-center cursor-pointer rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:border-gray-400 hover:bg-gray-100 hover:text-gray-900"
            >
                Add Row
            </button>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="meros-repeater-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="w-10 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"></th>
                        @foreach ($field->getFieldLabels() as $label)
                            <th scope="col" class="meros-repeater-data-header px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</th>
                        @endforeach
                        <th class="w-24 px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($rows as $row)
                        <tr draggable="false" class="align-top">
                            <td class="px-3 py-3 text-gray-300 select-none">☰</td>
                            @foreach ($field->getFieldNames() as $name)
                                <td class="meros-repeater-data-cell px-3 py-3 align-top">
                                    @php
                                        $subField = $row[$name] ?? null;
                                    @endphp
                                    @if($subField)
                                        {{ $subField->render(false, false) }}
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-3 py-3 text-right align-top">
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-md border border-red-200 px-3 py-1.5 text-sm font-medium text-red-600 transition-colors hover:border-red-300 hover:bg-red-50 hover:text-red-700"
                                >
                                    Remove
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-200 bg-gray-50 px-4 py-3">
            <button
                type="button"
                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:border-gray-400 hover:bg-gray-100 hover:text-gray-900"
            >
                Add Row
            </button>
        </div>
    @endif
</div>