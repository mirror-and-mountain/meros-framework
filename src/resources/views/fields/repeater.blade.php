<div class="meros-repeater-field {!! $field->classList() !!}">
    <div class="meros-repeater-rows">
        <table class="widefat striped meros-repeater-table">
            <thead>
                <tr>
                    <th class="meros-repeater-column meros-col-handle"></th>
                    @foreach ($field->getFieldLabels() as $label)
                        <th scope="col" class="meros-repeater-column meros-col-{{ strtolower($label) }}">{{ $label }}</th>
                    @endforeach
                    <th class="meros-repeater-column meros-col-actions"></th>
                </tr>
            </thead>
            
            <tbody>
                @foreach ($rows as $row)
                    <tr draggable="false" class="meros-draggable-row">
                        <td class="meros-repeater-column meros-col-handle meros-drag-handle">☰</td>
                        @foreach ($field->getFieldNames() as $name)
                            <td class="meros-repeater-column meros-col-{{ $name }}">
                                @php
                                    $subField = $row[$name] ?? null;           
                                @endphp
                                @if($subField)
                                    {{ $subField->render(false, false) }}
                                @endif
                            </td>
                        @endforeach
                        <td class="meros-repeater-column meros-repeater-actions">
                            <button type="button" class="button meros-remove-row">
                                Remove
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="meros-repeater-footer">
        <button type="button" class="button button-secondary meros-add-row" style="margin-top: 10px;">
            Add Row
        </button>
    </div>
</div>