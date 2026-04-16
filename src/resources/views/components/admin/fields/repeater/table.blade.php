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
                        @php $fieldInstance = $row->makeField($name); @endphp
                        @if ($fieldInstance)
                            <x-admin.fields.field-plain 
                                :component="$fieldInstance->getFieldComponent()" 
                                :field="$fieldInstance" 
                            />
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