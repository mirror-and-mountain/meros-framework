@php
    $hasActions = $allowsRemove || $editForm;
    $visibleFieldCount = count(array_filter($fields, fn ($field) =>
        $field['type'] !== 'hidden' && !($field['attributes']['data-meros-hidden-field'] ?? false)
    ));
    $columnCount = max(1, $visibleFieldCount + ($allowsReorder ? 1 : 0) + ($hasActions ? 1 : 0));
    $actionsWidth = $hasActions ? ($allowsRemove && $editForm ? '14rem' : '9rem') : '0rem';
@endphp

<fieldset 
    x-data="mformsRepeater" 
    id="{{ $id }}" 
    class="meros-field-wrapper mforms-field-wrapper mforms-repeater-field meros-repeater-field nice-form-group" 
    {!! $attributeString !!} 
    data-name="{{ $name }}"
    data-origin-name="{{ $originalName }}"
    data-field-origin-name="{{ $originalName }}"
    data-field-conditions="{{ $conditionsString }}"
    data-form-title="{{ $formTitle }}"
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
        @if ($hideLabel !== true)
            <legend class="meros-repeater-field-legend">{{ $label }}</legend>
        @endif
        @if($description !== '')
            <div style="margin-bottom: 0.5rem;">
                <small class="meros-field-description">{!! $description !!}</small>
            </div>
        @endif
    @endif
    <div class="meros-repeater-scroll" role="region" aria-label="{{ $label ?: 'Repeater fields' }}" tabindex="0">
        <table class="meros-repeater-table" style="--meros-repeater-fields-width: {{ $visibleFieldCount * 12 }}rem; --meros-repeater-move-width: {{ $allowsReorder ? '5rem' : '0rem' }}; --meros-repeater-actions-width: {{ $actionsWidth }};">
            {{-- Table Header Row --}}
            <thead class="meros-repeater-table-header">
                <tr>
                    @if($allowsReorder)
                        <th scope="col" class="meros-repeater-table-header-cell meros-repeater-table-header-cell--move">Move</th>
                    @endif
                    @foreach($fields as $index => $field)
                        @php
                            $isHeaderHidden = $field['type'] === 'hidden' || array_key_exists('attributes', $field) && ($field['attributes']['data-meros-hidden-field'] ?? false);
                            $headerClass = 'meros-repeater-table-header-cell meros-repeater-table-header-cell--field';

                            if ($isHeaderHidden) {
                                $headerClass .= ' meros-repeater-table-header-cell--hidden';
                            }
                        @endphp
                        <th scope="col" class="{{ $headerClass }}">
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
                        <th scope="col" class="meros-repeater-table-header-cell meros-repeater-table-header-cell--actions">Actions</th>
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
                                <td class="meros-repeater-table-cell meros-repeater-table-cell--move">
                                    <div class="meros-repeater-row-controls">
                                        <div class="meros-repeater-row-directions">
                                            <button type="button" class="meros-repeater-row-move" aria-label="Move row up" @click.stop.prevent="moveRow($event, -1)">↑</button>
                                            <button type="button" class="meros-repeater-row-move" aria-label="Move row down" @click.stop.prevent="moveRow($event, 1)">↓</button>
                                        </div>
                                        <span
                                            class="meros-repeater-table-move-handle"
                                            x-sort:handle
                                            draggable="true"
                                            aria-hidden="true"
                                            title="Drag to reorder row"
                                        >
                                            ☰
                                        </span>
                                    </div>
                                </td>
                            @endif
                            @foreach($rowData as $fieldIndex => $fieldData)
                                @php
                                    $isFieldHidden = $fieldData['type'] === 'hidden' || array_key_exists('attributes', $fieldData) && ($fieldData['attributes']['data-meros-hidden-field'] ?? false);
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
                                                {{ $editText }}
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
                    <td colspan="{{ $columnCount }}" class="meros-repeater-table-cell meros-repeater-table-cell--empty">
                        {{ $emptyText }}
                    </td>
                </tr>
            </tbody>
            <tfoot class="meros-repeater-table-footer">
                <tr>
                    <td colspan="{{ $columnCount }}" class="meros-repeater-table-cell meros-repeater-table-cell--add">
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
    <div class="meros-repeater-status" role="status" aria-atomic="true"></div>
</fieldset>