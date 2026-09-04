@php
    $hasActions = $allowsRemove || $editForm;
@endphp

<fieldset 
    x-data="mformsRepeater" 
    id="{{ $id }}" 
    class="meros-field-wrapper meros-repeater-field nice-form-group" 
    {!! $attributeString !!} 
    data-name="{{ $name }}"
    data-ajax-url="{{ $ajaxUrl }}"
    data-ajax-nonce="{{ $ajaxNonce }}"
    @if($onInit !== '')
        data-on-init="{{ $onInit }}"
    @endif
    @if($onRemove !== '')
        data-on-remove="{{ $onRemove }}"
    @endif
>
    @if($renderContext !== 'settings')
        <legend class="meros-repeater-field-legend" style="margin-bottom: 0.5rem;">{{ $label }}</legend>
        @if($description !== '')
            <div style="margin-bottom: 0.5rem;">
                <small class="meros-field-description">{!! $description !!}</small>
            </div>
        @endif
    @endif
    <div class="meros-repeater-scroll">
        <table class="meros-repeater-table">
            {{-- Table Header Row --}}
            <thead class="meros-repeater-table-header">
                <tr>
                    @if($allowsReorder)
                        <th class="meros-repeater-table-header-cell meros-repeater-table-header-cell--move">Move</th>
                    @endif
                    @foreach($fields as $index => $field)
                        @php
                            $isHeaderHidden = $field['type'] === 'hidden';
                            $headerClass = 'meros-repeater-table-header-cell meros-repeater-table-header-cell--field';

                            if ($isHeaderHidden) {
                                $headerClass .= ' meros-repeater-table-header-cell--hidden';
                            }
                        @endphp
                        <th class="{{ $headerClass }}">
                            {{ $field['label']}}
                            @if($field['description'] !== '')
                                <span 
                                    class="meros-repeater-field-description" 
                                    data-description="{{ $field['description'] }}"
                                    @mouseover.prevent="showFieldTooltip($event, '{{ $field['description'] }}')"
                                >
                                    &#9432;
                                </span>
                            @endif
                        </th>
                    @endforeach
                    @if($hasActions)
                        <th class="meros-repeater-table-header-cell meros-repeater-table-header-cell--actions">Actions</th>
                    @endif
                </tr>
            </thead>
            {{-- Table Body Rows --}}
            <tbody class="meros-repeater-table-body" @if($allowsReorder)x-sort="handleReorderRows"@endif>
                @if (count($tableRows) > 0)
                    @foreach($tableRows as $rowIndex => $rowData)
                        @php
                            $isTemplateRow = $rowIndex === -1;
                            $rowClass = $isTemplateRow ? 'meros-repeater-table-row meros-repeater-table-row--template' : 'meros-repeater-table-row';
                        @endphp
                        <tr 
                            class="{{ $rowClass }}" 
                            data-row-index="{{ $rowIndex }}"
                            x-sort:item="{{ $rowIndex }}"
                        >
                            @if($allowsReorder)
                                <td class="meros-repeater-table-cell meros-repeater-table-cell--move" x-sort:handle>
                                    <span 
                                        class="meros-repeater-table-move-handle"
                                        draggable="true"
                                        title="Drag to reorder row"
                                    >
                                        ☰
                                    </span>
                                </td>
                            @endif
                            @foreach($rowData as $fieldIndex => $fieldData)
                                @php
                                    $isFieldHidden = $fieldData['type'] === 'hidden';
                                    $fieldClass = 'meros-repeater-table-cell meros-repeater-table-cell--field';

                                    if ($isFieldHidden) {
                                        $fieldClass .= ' meros-repeater-table-cell--hidden';
                                    }
                                @endphp
                                <td class="{{ $fieldClass }}">
                                    @include($fieldData['wrapper'], $fieldData)
                                </td>
                            @endforeach
                            @if($hasActions)
                                <td class="meros-repeater-table-cell meros-repeater-table-cell--actions">
                                    <div class="meros-repeater-table-actions">
                                        @if($editForm)
                                            <button 
                                                type="button" 
                                                class="meros-repeater-table-button meros-repeater-table-button--edit" 
                                                @click.prevent="handleEditRow($event)"
                                            >
                                                Edit
                                            </button>
                                        @endif
                                        @if($allowsRemove)
                                            <button 
                                                type="button" 
                                                class="meros-repeater-table-button meros-repeater-table-button--remove" 
                                                @click.prevent="handleRemoveRow($event)"
                                            >
                                                {{ $removeText }}
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                @endif
                <tr
                    class="meros-repeater-table-row meros-repeater-table-row--empty"
                    x-show="numRows === 0"
                >
                    <td colspan="{{ count($fields) + ($allowsReorder ? 1 : 0) + ($hasActions ? 1 : 0) }}" class="meros-repeater-table-cell meros-repeater-table-cell--empty">
                        {{ $emptyText }}
                    </td>
                </tr>
            </tbody>
            <tfoot class="meros-repeater-table-footer">
                <tr>
                    <td colspan="{{ count($fields) + ($allowsReorder ? 1 : 0) + ($hasActions ? 1 : 0) }}" class="meros-repeater-table-cell meros-repeater-table-cell--add">
                        @if($allowsAdd)
                            <button 
                                type="button" 
                                class="meros-repeater-table-button meros-repeater-table-button--add"
                                @click.prevent="handleAddRow($event)"
                            >
                                {{ $addText }}
                            </button>
                        @endif
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</fieldset>