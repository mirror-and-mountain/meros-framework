<div 
    x-data="merosRepeaterField('{{ $id }}')"
    id="{{ $id }}" 
    class="{{ $classList }} meros-repeater" 
    data-field-type="repeater" 
    data-repeater-name="{{ str_replace(['-'], '_', $id) }}"
    aria-labelledby="{{ $id }}-label"
    data-rule-maxitems="{{ $maxRows ?? '-1' }}"
    data-rule-minitems="{{ $minRows ?? '-1' }}"
>
    {{-- Repeater row configuration dialog --}}
    <template x-teleport="body">
        <dialog
            class="meros-repeater-config-dialog"
            x-ref="rowConfigDialog"
            x-cloak
            x-effect="if (rowDialogOpen && activeDialogRowIndex !== null) { if (!$el.open) $el.showModal(); } else if ($el.open) { $el.close(); }"
            @close="if (rowDialogOpen) updateRowDialog()"
            @click.self="updateRowDialog()"
            @keydown.escape.window="updateRowDialog()"
        >
            <div
                class="meros-repeater-config-dialog__shell transition duration-200 ease-out"
                :class="isUpdatingRowDialog ? 'opacity-95 scale-[0.995]' : 'opacity-100 scale-100'"
                @click.stop
            >
                <header class="meros-repeater-config-dialog__header">
                    <h2 class="meros-repeater-config-dialog__title">{{ $configureRowText }}</h2>
                </header>

                <template x-if="!isUsingCustomConfigurationDialog">
                    <div class="meros-repeater-config-dialog__body" x-ref="rowConfigDialogBody">
                        @foreach($fieldNames as $fieldIndex => $fieldName)
                            @php
                                $templateSubField = $templateRow[$fieldName] ?? null;
                                $isHiddenInTable = $templateSubField && $templateSubField->isHiddenInRepeaterTable();
                            @endphp

                            <section class="meros-repeater-config-dialog__field" data-field-name="{{ $fieldName }}">
                                <h3 class="meros-repeater-config-dialog__field-label">
                                    {{ $fieldLabels[$fieldIndex] ?? $fieldName }}@if($isHiddenInTable) (This field is hidden in the table)@endif
                                </h3>
                                <div class="meros-repeater-config-dialog__field-input" data-field-name="{{ $fieldName }}"></div>
                            </section>
                        @endforeach
                    </div>
                </template>

                <template x-if="isUsingCustomConfigurationDialog && customConfigurationDialogHtml !== '' && customConfigurationDialogHtml !== null">
                    <div
                        x-data
                        id="meros-repeater-config-dialog-custom-body"
                        class="meros-repeater-config-dialog__body" 
                        x-ref="customRowConfigDialogBody"
                        x-html="customConfigurationDialogHtml"
                    >
                    </div>
                </template>

                <footer class="meros-repeater-config-dialog__footer">
                    <button
                        type="button"
                        class="meros-repeater-button meros-repeater-button--neutral"
                        :disabled="isUpdatingRowDialog"
                        @click="updateRowDialog()"
                    >
                        <span x-show="isUpdatingRowDialog" x-cloak>
                            @include('meros::toolbox.svgs.spinner')
                        </span>
                        <span>Update</span>
                    </button>
                </footer>
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
                            class="meros-repeater-data-header meros-repeater-head-cell {{ $isHiddenInTable ? 'meros-repeater-data-header--hidden' : '' }}"
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
                                        :disabled="isOpeningRowDialog || isUpdatingRowDialog"
                                        @click.stop="openRowDialog($event, @js($customConfigurationDialogs))"
                                    >
                                        <span class="configure-row-text" x-text="'{{ $configureRowText }}'"></span>
                                    </button>
                                @endif
                                {{-- Row remove button --}}
                                @if($allowsRemove)
                                    <button
                                        type="button"
                                        @click.stop="removeRow($event)"
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
                                    :disabled="isOpeningRowDialog || isUpdatingRowDialog"
                                    @click.stop="openRowDialog($event, @js($customConfigurationDialogs))"
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
                @click.stop="addRow"
                class="meros-repeater-button meros-repeater-button--neutral"
                title="{{ $addRowText }}"
            >
                {{ $addRowText }}
            </button>
        @endif
    </div>
</div>