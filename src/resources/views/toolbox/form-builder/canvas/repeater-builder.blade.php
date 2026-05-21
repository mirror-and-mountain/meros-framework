@if($activeRepeaterField)
    @php
        $repeaterField = $activeRepeaterField['field'];
        $repeaterColumns = $activeRepeaterField['columns'] ?? [];
        $previewRows = $activeRepeaterField['previewRows'] ?? [];
        $repeaterLocation = $activeRepeaterField['location'] ?? [];
        $repeaterPanelKey = sprintf(
            'repeater-panel-%s-%s-%s-%s',
            (string) ($repeaterField->getId() ?? 'repeater'),
            (string) ($repeaterLocation['groupRowIndex'] ?? 'root'),
            (string) ($repeaterLocation['rowIndex'] ?? 'x'),
            md5(json_encode($previewRows))
        );
    @endphp
    @component('meros::toolbox.form-builder.canvas.settings-panel', [
        'title' => 'Configure Repeater',
        'subtitle' => $repeaterField->getLabel() ?: 'Repeater field',
        'closeAction' => 'closeRepeaterBuilder',
    ])
        <div wire:key="{{ $repeaterPanelKey }}" class="mb-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-2">Columns</h3>
            <p class="text-xs text-gray-500 mb-3">Drag fields from the sidebar here to define the repeater row schema.</p>

            <div
                class="mb-3 min-h-16 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 px-3 py-4 text-sm text-gray-500 transition-colors"
                @dragover.prevent="$store.formDrag.showRepeaterColumnDropHighlight($el)"
                @dragleave="$store.formDrag.hideRepeaterColumnDropHighlight($el)"
                @drop.prevent="$store.formDrag.handleRepeaterColumnDrop($el, $wire, {{ count($repeaterColumns) }})"
            >
                Drag a field here to add a column
            </div>

            <div class="space-y-2">
                <div
                    wire:key="column-gap-0"
                    class="h-2 rounded-sm transition-all duration-150"
                    @dragover.prevent="$store.formDrag.handleRepeaterColumnGapDragOver($event, $el)"
                    @dragleave="$store.formDrag.hideRowGap($el)"
                    @drop.prevent="$store.formDrag.handleRepeaterColumnGapDrop($event, $el, $wire, 0)"
                ></div>

                @foreach($repeaterColumns as $columnIndex => $column)
                    <div
                        wire:key="column-{{ $column->getId() }}"
                        class="relative flex-1 min-w-0 bg-white border border-gray-200 rounded-md shadow-sm p-3"
                        draggable="true"
                        @dragstart="$event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('application/x-meros-repeater-column', '{{ $columnIndex }}')"
                    >
                        <div
                            class="relative z-20 mb-2 flex items-center justify-between gap-3 rounded-md border border-dashed border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-500 cursor-move active:cursor-grabbing select-none transition-colors hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600"
                            title="Drag to reorder column"
                        >
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="text-sm leading-none">⠿</span>
                                <span class="truncate font-medium">{{ $column->getLabel() }}</span>
                            </div>
                            <button
                                type="button"
                                wire:click="removeRepeaterColumn({{ $columnIndex }})"
                                class="shrink-0 cursor-pointer text-gray-300 hover:text-red-500 transition-colors text-lg leading-none"
                                title="Remove column"
                                @mousedown.stop
                            >&times;</button>
                        </div>
                        <div class="px-0.5 py-0.5 text-xs text-gray-500">{{ $column->handle }}</div>
                    </div>

                    <div
                        wire:key="column-gap-{{ $columnIndex + 1 }}"
                        class="h-2 rounded-sm transition-all duration-150"
                        @dragover.prevent="$store.formDrag.handleRepeaterColumnGapDragOver($event, $el)"
                        @dragleave="$store.formDrag.hideRowGap($el)"
                        @drop.prevent="$store.formDrag.handleRepeaterColumnGapDrop($event, $el, $wire, {{ $columnIndex + 1 }})"
                    ></div>
                @endforeach
            </div>
        </div>

        <div>
            <div class="mb-2">
                <h3 class="text-sm font-semibold text-gray-800">Preview Rows</h3>
            </div>
            <p class="text-xs text-gray-500 mb-3">Rows here become the repeater's default value. You can drag to reorder or click Edit to configure.</p>

            @if(empty($repeaterColumns))
                <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-3 py-4 text-sm text-gray-500">
                    Add at least one column to preview repeater rows.
                </div>
            @else
                <div class="space-y-2">
                    <div
                        wire:key="preview-row-gap-0"
                        class="h-2 rounded-sm transition-all duration-150"
                        @dragover.prevent="$store.formDrag.handleRepeaterRowGapDragOver($event, $el)"
                        @dragleave="$store.formDrag.hideRowGap($el)"
                        @drop.prevent="$store.formDrag.handleRepeaterRowGapDrop($event, $el, $wire, 0)"
                    ></div>

                    @forelse($previewRows as $rowIndex => $row)
                        <div
                            class="rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden"
                            wire:key="preview-row-{{ $rowIndex }}-{{ md5(json_encode($row)) }}"
                            draggable="true"
                            @dragstart="$event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('application/x-meros-repeater-row', '{{ $rowIndex }}')"
                        >
                            <div class="overflow-x-auto">
                                <table class="meros-repeater-table min-w-full text-sm">
                                    <thead class="bg-gray-50 border-b border-gray-200">
                                        <tr>
                                            <th class="w-10 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 cursor-move" title="Drag to reorder">☰</th>
                                            @foreach($repeaterColumns as $column)
                                                <th class="meros-repeater-data-header px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                    {{ $column->getLabel() }}
                                                </th>
                                            @endforeach
                                            <th class="w-32 px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr wire:key="preview-row-fields-{{ $rowIndex }}" class="align-top">
                                            <td class="px-3 py-3 text-gray-300 select-none"></td>
                                            @foreach($repeaterColumns as $column)
                                                @php
                                                    $columnName = $column->getName(false);
                                                    $subField = $row[$columnName] ?? null;
                                                @endphp
                                                <td
                                                    class="meros-repeater-data-cell px-3 py-3 align-top"
                                                    data-field-name="{{ $columnName }}"
                                                    @change="$wire.updateRepeaterDefaultRowValue({{ $rowIndex }}, $el.dataset.fieldName, $event.target.type === 'checkbox' ? (Array.from($el.querySelectorAll('input[type=checkbox]')).length > 1 ? Array.from($el.querySelectorAll('input[type=checkbox]:checked')).map(i => i.value) : $event.target.checked) : ($event.target.type === 'radio' ? ($el.querySelector('input[type=radio]:checked') ? $el.querySelector('input[type=radio]:checked').value : null) : ($event.target.multiple ? Array.from($event.target.options).filter(o => o.selected).map(o => o.value) : $event.target.value)))"
                                                >
                                                    @if($subField)
                                                        {!! $subField->render(false, false) !!}
                                                    @endif
                                                </td>
                                            @endforeach
                                            <td class="px-3 py-3 text-right align-top">
                                                <div class="flex items-center justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        wire:click="selectRepeaterRow({{ $rowIndex }})"
                                                        class="inline-flex items-center cursor-pointer rounded-md border border-blue-200 px-3 py-1.5 text-sm font-medium text-blue-600 transition-colors hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700"
                                                    >
                                                        Edit
                                                    </button>
                                                    <button
                                                        type="button"
                                                        wire:click="removeRepeaterDefaultRow({{ $rowIndex }})"
                                                        class="inline-flex items-center cursor-pointer rounded-md border border-red-200 px-3 py-1.5 text-sm font-medium text-red-600 transition-colors hover:border-red-300 hover:bg-red-50 hover:text-red-700"
                                                    >
                                                        Remove
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div
                            wire:key="preview-row-gap-{{ $rowIndex + 1 }}"
                            class="h-2 rounded-sm transition-all duration-150"
                            @dragover.prevent="$store.formDrag.handleRepeaterRowGapDragOver($event, $el)"
                            @dragleave="$store.formDrag.hideRowGap($el)"
                            @drop.prevent="$store.formDrag.handleRepeaterRowGapDrop($event, $el, $wire, {{ $rowIndex + 1 }})"
                        ></div>
                    @empty
                        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-3 py-4 text-sm text-gray-500">
                            No rows yet. Use "Add Row" to create the repeater default value.
                        </div>
                    @endforelse
                </div>

                <div class="border-t border-gray-200 bg-gray-50 rounded-b-lg px-6 py-3">
                    <button
                        type="button"
                        wire:click="addRepeaterDefaultRow"
                        class="inline-flex items-center cursor-pointer rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:border-gray-400 hover:bg-gray-100 hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-50"
                        @disabled(empty($repeaterColumns))
                    >
                        Add Row
                    </button>
                </div>
            @endif
        </div>
    @endcomponent
@endif

@if($activeRepeaterRow !== null && $activeRepeater)
    @php
        $repeaterData = $this->getActiveRepeaterViewModel();
        $repeaterField = $repeaterData['field'] ?? null;
        $repeaterColumns = $repeaterData['columns'] ?? [];
        $columnPayloads = $repeaterData['columnPayloads'] ?? [];
        $previewRows = $repeaterData['previewRows'] ?? [];
        $rowData = $previewRows[$activeRepeaterRow] ?? null;
        $repeaterRowEditorKey = sprintf(
            'repeater-row-editor-%s-%s-%s-%s',
            (string) ($repeaterField?->getId() ?? 'repeater'),
            (string) ($activeRepeater['groupRowIndex'] ?? 'root'),
            (string) ($activeRepeater['rowIndex'] ?? 'x'),
            (string) $activeRepeaterRow
        );
    @endphp

    @if($rowData)
        @component('meros::toolbox.form-builder.canvas.settings-panel', [
            'title' => 'Edit Row',
            'subtitle' => 'Row ' . ($activeRepeaterRow + 1),
            'closeAction' => 'closeRepeaterRow',
        ])
            <div wire:key="{{ $repeaterRowEditorKey }}" class="space-y-6">
                @foreach($repeaterColumns as $columnIndex => $column)
                    @php
                        $columnPayload = $columnPayloads[$columnIndex] ?? [];
                        $columnHandle = (string) ($columnPayload['handle'] ?? $column->handle ?? '');
                        $columnName = (string) ($columnPayload['name'] ?? $column->getName(false));
                        $subField = $rowData[$columnName] ?? null;
                        $options = is_array($columnPayload['options'] ?? null) ? $columnPayload['options'] : [];
                        $optionsText = collect($options)
                            ->map(fn ($label, $value) => is_string($value) ? ($value . '|' . (string) $label) : (string) $label)
                            ->implode("\n");
                        $supportsOptions = in_array($columnHandle, ['select', 'multi_select', 'radio', 'checkboxes'], true);
                        $supportsPlaceholder = in_array($columnHandle, ['text', 'email', 'url', 'number', 'password', 'date', 'time'], true);
                        $supportsRows = $columnHandle === 'textarea';
                        $columnEditorKey = sprintf(
                            'repeater-column-editor-%s-%s-%s-%s-%s',
                            (string) ($repeaterField?->getId() ?? 'repeater'),
                            (string) $activeRepeaterRow,
                            (string) $columnIndex,
                            (string) ($columnPayload['id'] ?? $columnName),
                            (string) ($columnPayload['_fieldVersion'] ?? 0)
                        );
                        $columnOptionsKey = sprintf(
                            'repeater-column-options-%s-%s-%s-%s',
                            (string) ($repeaterField?->getId() ?? 'repeater'),
                            (string) $activeRepeaterRow,
                            (string) $columnIndex,
                            (string) ($columnPayload['id'] ?? $columnName)
                        );
                    @endphp

                    <div wire:key="{{ $columnEditorKey }}" class="rounded-lg border border-gray-200 bg-white p-4">
                        <h4 class="text-sm font-semibold text-gray-800 mb-3">{{ $column->getLabel() }}</h4>

                        <div class="mb-4">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Row Value</label>
                            <div
                                data-field-name="{{ $columnName }}"
                                @change="$wire.updateRepeaterDefaultRowValue({{ $activeRepeaterRow }}, $el.dataset.fieldName, $event.target.type === 'checkbox' ? (Array.from($el.querySelectorAll('input[type=checkbox]')).length > 1 ? Array.from($el.querySelectorAll('input[type=checkbox]:checked')).map(i => i.value) : $event.target.checked) : ($event.target.type === 'radio' ? ($el.querySelector('input[type=radio]:checked') ? $el.querySelector('input[type=radio]:checked').value : null) : ($event.target.multiple ? Array.from($event.target.options).filter(o => o.selected).map(o => o.value) : $event.target.value)))"
                            >
                                @if($subField)
                                    {{ $subField->render(false, false) }}
                                @endif
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 border-t border-gray-100 pt-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Label</label>
                                <input
                                    type="text"
                                    value="{{ $columnPayload['label'] ?? '' }}"
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                    @input.debounce.300ms="$wire.updateRepeaterColumnSetting({{ $columnIndex }}, 'label', $event.target.value)"
                                >
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Name</label>
                                <input
                                    type="text"
                                    value="{{ $columnPayload['name'] ?? ($columnPayload['handle'] ?? '') }}"
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                    @input.debounce.300ms="$wire.updateRepeaterColumnSetting({{ $columnIndex }}, 'name', $event.target.value)"
                                >
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">ID</label>
                                <input
                                    type="text"
                                    value="{{ $columnPayload['id'] ?? '' }}"
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                    @input.debounce.300ms="$wire.updateRepeaterColumnSetting({{ $columnIndex }}, 'id', $event.target.value)"
                                >
                            </div>

                            @if($supportsPlaceholder)
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Placeholder</label>
                                    <input
                                        type="text"
                                        value="{{ $columnPayload['placeholder'] ?? '' }}"
                                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                        @input.debounce.300ms="$wire.updateRepeaterColumnSetting({{ $columnIndex }}, 'placeholder', $event.target.value)"
                                    >
                                </div>
                            @endif

                            @if($supportsRows)
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Rows</label>
                                    <input
                                        type="number"
                                        min="1"
                                        value="{{ (int) ($columnPayload['rows'] ?? 3) }}"
                                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                        @input.debounce.300ms="$wire.updateRepeaterColumnSetting({{ $columnIndex }}, 'rows', $event.target.value)"
                                    >
                                </div>
                            @endif

                            @if($supportsOptions)
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Options</label>
                                    <p class="mb-1 text-[11px] text-gray-500">One per line. Use value|Label or just Label.</p>
                                    <textarea
                                        wire:key="{{ $columnOptionsKey }}"
                                        rows="5"
                                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                        @input.debounce.300ms="$wire.updateRepeaterColumnSetting({{ $columnIndex }}, 'optionsText', $event.target.value)"
                                    >{{ $optionsText }}</textarea>
                                </div>
                            @endif

                            <div class="grid grid-cols-2 gap-2">
                                <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-xs text-gray-700">
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4"
                                        @checked((bool) ($columnPayload['required'] ?? false))
                                        @change="$wire.updateRepeaterColumnSetting({{ $columnIndex }}, 'required', $event.target.checked)"
                                    >
                                    Required
                                </label>

                                <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-xs text-gray-700">
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4"
                                        @checked((bool) ($columnPayload['disabled'] ?? false))
                                        @change="$wire.updateRepeaterColumnSetting({{ $columnIndex }}, 'disabled', $event.target.checked)"
                                    >
                                    Disabled
                                </label>
                            </div>

                            @php
                                $isMultiSelect = $columnHandle === 'multi_select';
                                $isSelect = $columnHandle === 'select';
                                $isChoiceField = in_array($columnHandle, ['select', 'multi_select', 'radio', 'checkboxes'], true);
                                $supportsAdvanced = $isSelect || $isMultiSelect;
                                $isAdvancedEnabled = (bool) ($columnPayload['advanced'] ?? ($isMultiSelect ? true : false));
                            @endphp

                            @if($supportsAdvanced)
                                @if($isSelect)
                                    <div>
                                        <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-xs text-gray-700 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                class="h-4 w-4"
                                                @checked($isAdvancedEnabled)
                                                @change="$wire.updateRepeaterColumnSetting({{ $columnIndex }}, 'advanced', $event.target.checked)"
                                            >
                                            <span>Use Advanced Select</span>
                                        </label>
                                    </div>
                                @endif

                                @if($isAdvancedEnabled)
                                    <div>
                                        <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-xs text-gray-700 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                class="h-4 w-4"
                                                @checked((bool) ($columnPayload['allowAdd'] ?? false))
                                                @change="$wire.updateRepeaterColumnSetting({{ $columnIndex }}, 'allowAdd', $event.target.checked)"
                                            >
                                            <span>Allow users to add new options</span>
                                        </label>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endcomponent
    @endif
@endif
