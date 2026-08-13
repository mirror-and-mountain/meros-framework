@php
    $classList = ' ' . $field->classList() . ' ';
    $isAdminRepeater = str_contains($classList, ' meros-admin-field ');
    $isSettingsRepeater = str_contains($classList, ' meros-settings-field ');
    $emptyMarkerName = \MM\Meros\Support\FormFieldName::emptyMarkerName($name);

    $onAddRow = $field->getAddRowCallback();
    $onRemoveRow = $field->getRemoveRowCallback();
    $onConfigureRow = $field->getConfigureRowCallback();

    if ($onConfigureRow === '') {
        $onConfigureRow = 'null';
    } else if ($onConfigureRow === 'ajax') {
        $onConfigureRow = "'ajax'";
    } else {
        $onConfigureRow = e($onConfigureRow);
    }

    if ($onAddRow === '') {
        $onAddRow = 'null';
    } else {
        $onAddRow = e($onAddRow);
    }

    if ($onRemoveRow === '') {
        $onRemoveRow = 'null';
    } else {
        $onRemoveRow = e($onRemoveRow);
    }

@endphp

<fieldset 
    x-data="merosRepeaterField('{{ $id }}', '{{ $placeholder }}', {{ $fieldCount }}, '{{ $ajaxUrl }}', {{ $serialisedRules }})"
    id="{{ $id }}" 
    class="meros-repeater meros-repeater-field{{ $isSettingsRepeater ? ' meros-repeater--settings' : '' }}" 
    data-field-type="repeater" 
    data-field-name="{{ $name }}"
    data-rule-max-items="{{ $maxRows ?? '-1' }}"
    data-rule-min-items="{{ $minRows ?? '-1' }}"
>
    @if($isAdminRepeater)
        <input type="hidden" name="{{ $emptyMarkerName }}" value="1" data-repeater-empty-value="true">
    @endif

    {{-- Legend --}}
    @if($label !== false)
        <legend 
            id="{{ $id }}-label"
            class="form-label"
            style="color:inherit;padding:0"
        >{{ $label }}
            @if($isRequired)
                <span class="required-indicator">*</span>
            @endif
        </legend>
    @endif

    {{-- Help Text --}}
    @if($helpText !== false && !empty($helpText))
        <small class="description">{{ $helpText }}</small>
    @endif

    {{-- Repeater row configuration dialog --}}
    <template x-ref="rowDialog" x-if="rowDialogActive">
        <dialog class="meros-repeater-config-dialog" :open="rowDialogActive">
            <div class="meros-repeater-config-dialog__shell">
                <span aria-label="Close dialog" role="button" @click="closeRowDialog()" style="float:right;cursor:pointer" title="Close dialog">x</span>
                <form id="meros-repeater-row-dialog-{{ $id }}" class="meros-repeater-row-dialog-form" @submit.prevent="submitRowDialog($event)">
                    <div class="meros-repeater-row-dialog-content"></div>
                    <div class="meros-repeater-config-dialog__footer">
                        <button type="submit" class="button button-primary" style="width:100px;" title="Save">Save</button>
                        <button type="button" @click="closeRowDialog()" class="button button-secondary" style="width:100px;" title="Cancel">Cancel</button>
                    </div>
                </form>
            </div>
        </dialog>
    </template>


    {{-- Repeater table --}}
    <div class="meros-repeater-scroll">
        <table class="meros-repeater-table meros-repeater-table--interactive">
            {{-- Repeater header --}}
            <thead class="meros-repeater-head">
                <tr>
                    @if($allowsReorder)
                        <th class="meros-repeater-head-cell meros-repeater-head-cell--move">Move</th>
                    @endif
                    @foreach($fieldNames as $fieldIndex => $fieldName)
                        @php
                            $templateSubField = $templateRow[$fieldName] ?? null;
                            $isHiddenInTable = $templateSubField && $templateSubField->isHiddenInRepeaterTable();
                        @endphp

                        <th
                            class="meros-repeater-data-header meros-repeater-head-cell meros-repeater-field-header {{ $isHiddenInTable ? 'meros-repeater-data-header--hidden' : '' }}"
                            @if($isHiddenInTable) aria-hidden="true" @endif
                        >
                            {{ $fieldLabels[$fieldIndex] ?? $fieldName }}
                        </th>
                    @endforeach
                    @if($showsActionsColumn)
                        <th class="meros-repeater-head-cell meros-repeater-head-cell--actions">Actions</th>
                    @endif
                </tr>
            </thead>
            {{-- Repeater body --}}
            <tbody class="meros-repeater-body" x-sort="handleReorder">
                {{-- Repeater rows --}}
                @foreach($rows as $rowIndex => $row)
                    <tr
                        x-sort:item="{{ $rowIndex }}"
                        class="meros-repeater-row"
                        data-repeater-row-index="{{ $rowIndex }}"
                    >
                        @if($allowsReorder)
                            <td class="meros-repeater-move-cell" x-sort:handle>
                                <button
                                    type="button"
                                    draggable="true"
                                    class="meros-repeater-move-button"
                                    title="Drag to reorder row"
    
                                    @if(!empty($onMoveCallback))
                                        data-move-row-callback="{{ $onMoveCallback }}"
                                    @endif
                                >
                                    ☰
                                </button>
                            </td>
                        @endif
                        {{-- Row cells --}}
                        @foreach($fieldNames as $fieldName)
                            @php
                                $templateSubField = $templateRow[$fieldName] ?? null;
                                $isHiddenInTable = $templateSubField && $templateSubField->isHiddenInRepeaterTable();
                            @endphp

                            <td
                                class="meros-repeater-data-cell {{ $isHiddenInTable ? 'meros-repeater-data-cell--hidden' : '' }}"
                                data-field-name="{{ $fieldName }}"
                                @if($isHiddenInTable) aria-hidden="true" @endif
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
                                    {!! $subField->render(true, ['label' => false, 'helpText' => false]) !!}
                                @endif
                            </td>
                        @endforeach
                        {{-- Row actions --}}
                        @if($showsActionsColumn)
                            {{-- Row configure button --}}
                            <td class="meros-repeater-actions-cell">
                                @if($allowsConfigure)
                                    <button
                                        type="button"
                                        @if(!empty($configureRequiredFields))
                                            data-configure-required-fields="{{ json_encode($configureRequiredFields) }}"
                                        @endif
                                        class="meros-repeater-button meros-repeater-button--configure meros-repeater-button--neutral"
                                        title="{{ $configureRowText }}"
                                        :disabled="(typeof isOpeningRowDialog !== 'undefined' && isOpeningRowDialog) || (typeof isUpdatingRowDialog !== 'undefined' && isUpdatingRowDialog)"
                                        @click.stop="openRowDialog($event, {!! $onConfigureRow !!})"
                                    >
                                        <span class="configure-row-text" x-text="'{{ $configureRowText }}'"></span>
                                    </button>
                                @endif
                                {{-- Row remove button --}}
                                @if($allowsRemove)
                                    <button
                                        type="button"
                                        @click.stop="removeRow($event, {{ $rowIndex }}, {!! $onRemoveRow !!})"
                                        class="meros-repeater-button meros-repeater-button--remove meros-repeater-button--danger"
                                        title="{{ $removeRowText }}"
                                    >
                                        {{ $removeRowText }}
                                    </button>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach

                {{-- Placeholder row when there are no entries --}}
                @if($allowsAdd)
                    <tr>
                        <td x-show="showPlaceholder" colspan="{{ $columnCount }}" class="meros-repeater-empty-state">
                            {{ $placeholder }}
                        </td>
                    </tr>
                @else
                    <tr>
                        <td x-show="showPlaceholder" colspan="{{ $columnCount }}" class="meros-repeater-empty-state">
                            Nothing to display.
                        </td>
                    </tr>
                @endif

                {{-- Template row for new entries --}}
                <tr id="meros-repeater-template-row-{{ $id }}" class="meros-repeater-row meros-repeater-template-row" style="display: none;">
                    @if($allowsReorder)
                        <td class="meros-repeater-move-cell">
                            <button
                                type="button"
                                draggable="true"
                                class="meros-repeater-move-button"
                                title="Drag to reorder row"
                                @if(!empty($onMoveCallback))
                                    data-move-row-callback="{{ $onMoveCallback }}"
                                @endif
                            >
                                ☰
                            </button>
                        </td>
                    @endif
                    @foreach($fieldNames as $fieldName)
                        @php
                            $subField = $templateRow[$fieldName] ?? null;
                            $isHiddenInTable = $subField && $subField->isHiddenInRepeaterTable();
                        @endphp
                        <td
                            class="meros-repeater-data-cell {{ $isHiddenInTable ? 'meros-repeater-data-cell--hidden' : '' }}"
                            data-field-name="{{ $fieldName }}"
                            @if($isHiddenInTable) aria-hidden="true" @endif
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

                                @endphp
                                {!! $subField->render(true, ['label' => false, 'helpText' => false]) !!}
                            @endif
                        </td>
                    @endforeach
                    @if($showsActionsColumn)
                        <td class="meros-repeater-actions-cell">
                            @if($allowsConfigure)
                                <button
                                    type="button"
                                    @if(!empty($configureRequiredFields))
                                        data-configure-required-fields="{{ json_encode($configureRequiredFields) }}"
                                    @endif
                                    class="meros-repeater-button meros-repeater-button--configure meros-repeater-button--neutral"
                                    title="{{ $configureRowText }}"
                                    :disabled="(typeof isOpeningRowDialog !== 'undefined' && isOpeningRowDialog) || (typeof isUpdatingRowDialog !== 'undefined' && isUpdatingRowDialog)"
                                    @click.stop="openRowDialog($event, {!! $onConfigureRow !!})"
                                >
                                    <span class="configure-row-text" x-text="'{{ $configureRowText }}'"></span>
                                </button>
                            @endif
                            @if($allowsRemove)
                                <button
                                    type="button"
                                    class="meros-repeater-button meros-repeater-button--remove meros-repeater-button--danger"
                                    title="{{ $removeRowText }}"
                                >
                                    {{ $removeRowText }}
                                </button>
                            @endif
                        </td>
                    @endif
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Repeater footer --}}
    <div class="meros-repeater-footer">
        {{-- Add row button --}}
        @if($allowsAdd)
            <button
                type="button"
                @click.stop="addRow(((typeof isEditor !== 'undefined') ? isEditor : false) || !!$el.closest('.meros-repeater-config-dialog__body'), {!! $onAddRow !!}, {!! $onRemoveRow !!})"
                class="meros-repeater-button meros-repeater-button--neutral meros-repeater-button--add"
                title="{{ $addRowText }}"
                :disabled="!canAddRows && !$el.closest('.meros-repeater-config-dialog__body')"
                :aria-disabled="!canAddRows && !$el.closest('.meros-repeater-config-dialog__body')"
            >
                {{ $addRowText }}
            </button>
        @endif
    </div>
</fieldset>