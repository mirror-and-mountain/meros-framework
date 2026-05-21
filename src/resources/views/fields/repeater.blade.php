<div class="{{ $field->classList() }} meros-repeater">
    @if($location)
        @php
            $rawRepeaterValue = is_array($field->getValue()) ? $field->getValue() : [];
            $repeaterLocationKey = is_int($location['groupRowIndex'] ?? null)
                ? sprintf('group-%d-%d-%d', $location['groupRowIndex'], $location['rowIndex'] ?? -1, $location['fieldIndex'] ?? -1)
                : sprintf('%d-%d', $location['rowIndex'] ?? -1, $location['fieldIndex'] ?? -1);
            $livewireSyncEnabled = isset($this) && $this instanceof \MM\Meros\App\Toolbox\FormBuilder;
        @endphp
        <div
            class="meros-repeater-scroll"
            wire:key="canvas-repeater-{{ $location['groupRowIndex'] ?? 'top' }}-{{ $location['rowIndex'] ?? 'x' }}-{{ $location['fieldIndex'] ?? 'x' }}"
            x-data="{ isDraggingRow: false, draggingRowIndex: null }"
        >
            <table class="meros-repeater-table meros-repeater-table--interactive" data-repeater-location-key="{{ $repeaterLocationKey }}" data-livewire-sync-enabled="{{ $livewireSyncEnabled ? 'true' : 'false' }}">
                <thead class="meros-repeater-head">
                    <tr>
                        <th class="meros-repeater-head-cell meros-repeater-head-cell--move">Move</th>
                        @foreach($field->getFieldLabels() as $label)
                            <th class="meros-repeater-data-header meros-repeater-head-cell">{{ $label }}</th>
                        @endforeach
                        <th class="meros-repeater-head-cell meros-repeater-head-cell--actions">Actions</th>
                    </tr>
                </thead>
                <tbody class="meros-repeater-body">
                    @if(!empty($rows))
                        <tr>
                            <td colspan="{{ count($field->getFieldNames()) + 2 }}" class="meros-repeater-gap-cell">
                                <div
                                    class="meros-repeater-row-gap"
                                    :class="isDraggingRow ? 'is-active' : ''"
                                    @dragover.prevent="$store.repeaterField.handleRowGapDragOver($el)"
                                    @dragleave="$store.repeaterField.hideRowGap($el)"
                                    @drop.prevent="$store.repeaterField.handleRowGapDrop($event, $el, {{ $location['rowIndex'] ?? 'null' }}, {{ $location['fieldIndex'] ?? 'null' }}, {{ $location['groupRowIndex'] ?? 'null' }}, 0)"
                                ></div>
                            </td>
                        </tr>
                    @endif

                    @forelse($rows as $rowIndex => $row)
                        @php
                            $rowKey = $rawRepeaterValue[$rowIndex]['__rowKey'] ?? null;
                            $fallbackRowKey = is_array($rawRepeaterValue[$rowIndex] ?? null)
                                ? ('hash-' . md5(json_encode($rawRepeaterValue[$rowIndex])))
                                : ('idx-' . $rowIndex);
                            $renderRowKey = is_string($rowKey) && $rowKey !== '' ? $rowKey : $fallbackRowKey;
                        @endphp
                        <tr
                            class="meros-repeater-row"
                            data-repeater-row-index="{{ $rowIndex }}"
                            data-repeater-row-key="{{ $renderRowKey }}"
                            wire:key="repeater-row-{{ $renderRowKey }}"
                            :class="draggingRowIndex === {{ $rowIndex }} ? 'is-dragging' : ''"
                            @dragover.prevent
                        >
                            <td class="meros-repeater-move-cell">
                                <button
                                    type="button"
                                    draggable="true"
                                    class="meros-repeater-move-button"
                                    title="Drag to reorder row"
                                    @dragstart="const rowIndex = Number($el.closest('tr')?.dataset.repeaterRowIndex ?? {{ $rowIndex }}); isDraggingRow = true; draggingRowIndex = rowIndex; $store.repeaterField.startRowDrag($event, rowIndex)"
                                    @dragend="isDraggingRow = false; draggingRowIndex = null"
                                >
                                    ☰
                                </button>
                            </td>
                            @foreach($field->getFieldNames() as $fieldName)
                                <td
                                    class="meros-repeater-data-cell"
                                    data-location-row-index="{{ $location['rowIndex'] ?? 'null' }}"
                                    data-location-field-index="{{ $location['fieldIndex'] ?? 'null' }}"
                                    data-location-group-row-index="{{ $location['groupRowIndex'] ?? 'null' }}"
                                    data-field-name="{{ $fieldName }}"
                                    @change="$store.repeaterField.updateRowValue({{ $location['rowIndex'] ?? 'null' }}, {{ $location['fieldIndex'] ?? 'null' }}, {{ $location['groupRowIndex'] ?? 'null' }}, {{ $rowIndex }}, $el.dataset.fieldName, $el, $event)"
                                >
                                    @php
                                        $subField = $row[$fieldName] ?? null;
                                    @endphp
                                    @if($subField)
                                        {!! $subField->render(false, false) !!}
                                    @endif
                                </td>
                            @endforeach
                            <td class="meros-repeater-actions-cell">
                                <button
                                    type="button"
                                    @click.stop="$store.repeaterField.removeRow({{ $location['rowIndex'] ?? 'null' }}, {{ $location['fieldIndex'] ?? 'null' }}, {{ $location['groupRowIndex'] ?? 'null' }}, {{ $rowIndex }})"
                                    class="meros-repeater-button meros-repeater-button--danger"
                                    title="Remove row"
                                >
                                    Remove
                                </button>
                            </td>
                        </tr>

                        <tr>
                            <td colspan="{{ count($field->getFieldNames()) + 2 }}" class="meros-repeater-gap-cell">
                                <div
                                    class="meros-repeater-row-gap"
                                    :class="isDraggingRow ? 'is-active' : ''"
                                    @dragover.prevent="$store.repeaterField.handleRowGapDragOver($el)"
                                    @dragleave="$store.repeaterField.hideRowGap($el)"
                                    @drop.prevent="$store.repeaterField.handleRowGapDrop($event, $el, {{ $location['rowIndex'] ?? 'null' }}, {{ $location['fieldIndex'] ?? 'null' }}, {{ $location['groupRowIndex'] ?? 'null' }}, {{ $rowIndex + 1 }})"
                                ></div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($field->getFieldNames()) + 2 }}" class="meros-repeater-empty-state">
                                No rows yet. Use "Add Row" to create repeater data.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="meros-repeater-footer">
            <button
                type="button"
                @click.stop="$store.repeaterField.addRow({{ $location['rowIndex'] ?? 'null' }}, {{ $location['fieldIndex'] ?? 'null' }}, {{ $location['groupRowIndex'] ?? 'null' }})"
                class="meros-repeater-button meros-repeater-button--neutral"
                title="Add new row"
            >
                Add Row
            </button>
        </div>
    @else
        <div class="meros-repeater-scroll">
            <table class="meros-repeater-table meros-repeater-table--readonly">
                <thead class="meros-repeater-head">
                    <tr>
                        <th class="meros-repeater-head-cell meros-repeater-head-cell--move"></th>
                        @foreach ($field->getFieldLabels() as $label)
                            <th scope="col" class="meros-repeater-data-header meros-repeater-head-cell">{{ $label }}</th>
                        @endforeach
                        <th class="meros-repeater-head-cell meros-repeater-head-cell--actions">Actions</th>
                    </tr>
                </thead>

                <tbody class="meros-repeater-body">
                    @foreach ($rows as $row)
                        <tr draggable="false" class="meros-repeater-row">
                            <td class="meros-repeater-move-cell">☰</td>
                            @foreach ($field->getFieldNames() as $name)
                                <td class="meros-repeater-data-cell">
                                    @php
                                        $subField = $row[$name] ?? null;
                                    @endphp
                                    @if($subField)
                                        {!! $subField->render(false, false) !!}
                                    @endif
                                </td>
                            @endforeach
                            <td class="meros-repeater-actions-cell">
                                <button
                                    type="button"
                                    class="meros-repeater-button meros-repeater-button--danger"
                                >
                                    Remove
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="meros-repeater-footer">
            <button
                type="button"
                class="meros-repeater-button meros-repeater-button--neutral"
            >
                Add Row
            </button>
        </div>
    @endif
</div>