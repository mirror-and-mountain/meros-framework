@php
    $id = $field->getID();
    $value = $field->getValue();
    $rawRepeaterValue = is_array($value) ? $value : [];
    $templateRow = $field->buildTemplateRow();
    $showsMoveColumn = $field->allowsReorder();
    $showsActionsColumn = $field->allowsConfigure() || $field->allowsRemove();
    $columnCount = count($field->getFieldNames())
        + ($showsMoveColumn ? 1 : 0)
        + ($showsActionsColumn ? 1 : 0);
    $configurationCallback = $field->getConfigurationCallback();
@endphp
<div id="{{ $id }}" class="{{ $field->classList() }} meros-repeater">
    <div
        class="meros-repeater-scroll"
        x-data="{ isDraggingRow: false, draggingRowIndex: null }"
    >
        <table class="meros-repeater-table meros-repeater-table--interactive">
            <thead class="meros-repeater-head">
                <tr>
                    @if($showsMoveColumn)
                        <th class="meros-repeater-head-cell meros-repeater-head-cell--move">Move</th>
                    @endif
                    @foreach($field->getFieldNames() as $fieldIndex => $fieldName)
                        <th
                            class="meros-repeater-data-header meros-repeater-head-cell"
                            wire:key="repeater-head-{{ $field->getID() }}-{{ $fieldName }}"
                        >
                            {{ $field->getFieldLabels()[$fieldIndex] ?? $fieldName }}
                        </th>
                    @endforeach
                    @if($showsActionsColumn)
                        <th class="meros-repeater-head-cell meros-repeater-head-cell--actions">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody class="meros-repeater-body">
                <tr class="meros-repeater-gap-row">
                    <td colspan="{{ $columnCount }}" class="meros-repeater-gap-cell">
                        <div
                            class="meros-repeater-row-gap"
                            :class="isDraggingRow ? 'is-active' : ''"
                            @dragover.prevent="$store.repeaterField.handleRowGapDragOver($el)"
                            @dragleave="$store.repeaterField.hideRowGap($el)"
                            @drop.prevent="$store.repeaterField.handleRowGapDrop($event, $el)"
                        ></div>
                    </td>
                </tr>

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
                        @if($field->allowsReorder())
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
                        @endif
                        @foreach($field->getFieldNames() as $fieldName)
                            <td
                                class="meros-repeater-data-cell"
                                data-field-name="{{ $fieldName }}"
                                wire:key="repeater-cell-{{ $renderRowKey }}-{{ $fieldName }}"
                            >
                                @php
                                    $subField = $row[$fieldName] ?? null;
                                @endphp
                                @if($subField)
                                    @php
                                        $default = $subField->getDefault();
                                        if ($default !== null) {
                                            if (is_array($default) || is_object($default)) {
                                                $default = json_encode($default);
                                            }
                                            $subField->attribute('data-default-value', $default);
                                        }
                                    @endphp
                                    {!! $subField->render(false, false) !!}
                                @endif
                            </td>
                        @endforeach
                        @if($showsActionsColumn)
                            <td class="meros-repeater-actions-cell">
                                @if($field->allowsConfigure())
                                    <button
                                        type="button"
                                        @click.stop="$store.repeaterField.configureRow($el, '{{ $configurationCallback }}')"
                                        data-configure-callback="{{ $configurationCallback }}"
                                        class="meros-repeater-button meros-repeater-button--neutral meros-repeater-button--configure"
                                        title="Configure row"
                                    >
                                        Configure
                                    </button>
                                @endif
                                @if($field->allowsRemove())
                                    <button
                                        type="button"
                                        @click.stop="$store.repeaterField.removeRow($el)"
                                        class="meros-repeater-button meros-repeater-button--danger"
                                        title="Remove row"
                                    >
                                        Remove
                                    </button>
                                @endif
                            </td>
                        @endif
                    </tr>

                    <tr class="meros-repeater-gap-row">
                        <td colspan="{{ $columnCount }}" class="meros-repeater-gap-cell">
                            <div
                                class="meros-repeater-row-gap"
                                :class="isDraggingRow ? 'is-active' : ''"
                                @dragover.prevent="$store.repeaterField.handleRowGapDragOver($el)"
                                @dragleave="$store.repeaterField.hideRowGap($el)"
                                @drop.prevent="$store.repeaterField.handleRowGapDrop($event, $el)"
                            ></div>
                        </td>
                    </tr>
                @empty
                @if($field->allowsAdd())
                    <tr>
                        <td colspan="{{ $columnCount }}" class="meros-repeater-empty-state">
                            No rows yet. Use "Add Row" to create repeater data.
                        </td>
                    </tr>
                @else
                    <tr>
                        <td colspan="{{ $columnCount }}" class="meros-repeater-empty-state">
                            No data.
                        </td>
                    </tr>
                @endif
                @endforelse
                <tr id="meros-repeater-template-row-{{ $field->getID() }}" class="meros-repeater-row meros-repeater-template-row" style="display: none;">
                    @if($field->allowsReorder())
                        <td class="meros-repeater-move-cell">
                            <button
                                type="button"
                                draggable="true"
                                class="meros-repeater-move-button"
                                title="Drag to reorder row"
                            >
                                ☰
                            </button>
                        </td>
                    @endif
                    @foreach($field->getFieldNames() as $fieldName)
                        @php
                            $subField = $templateRow[$fieldName] ?? null;
                        @endphp
                        <td
                            class="meros-repeater-data-cell"
                            data-field-name="{{ $fieldName }}"
                            wire:key="repeater-template-cell-{{ $field->getID() }}-{{ $fieldName }}"
                        >
                            @if($subField)
                                @php
                                    $default = $subField->getDefault();
                                    if ($default !== null) {
                                        if (is_array($default) || is_object($default)) {
                                            $default = json_encode($default);
                                        }
                                        $subField->attribute('data-default-value', $default);
                                    }
                                    $subField->attribute('disabled', true);
                                @endphp
                                {!! $subField->render(false, false) !!}
                            @endif
                        </td>
                    @endforeach
                    @if($showsActionsColumn)
                        <td class="meros-repeater-actions-cell">
                            @if($field->allowsConfigure())
                                <button
                                    type="button"
                                    @click.stop="$store.repeaterField.configureRow($el, '{{ $configurationCallback }}')"
                                    data-configure-callback="{{ $configurationCallback }}"
                                    class="meros-repeater-button meros-repeater-button--neutral meros-repeater-button--configure"
                                    title="Configure row"
                                >
                                    Configure
                                </button>
                            @endif
                            @if($field->allowsRemove())
                                <button
                                    type="button"
                                    class="meros-repeater-button meros-repeater-button--danger"
                                    title="Remove row"
                                >
                                    Remove
                                </button>
                            @endif
                        </td>
                    @endif
                </tr>
            </tbody>
        </table>
    </div>

    <div class="meros-repeater-footer">
        @if($field->allowsAdd())
            <button
                type="button"
                @click.stop="$store.repeaterField.addRow($el)"
                class="meros-repeater-button meros-repeater-button--neutral"
                title="Add new row"
            >
                Add Row
            </button>
        @endif
    </div>
</div>