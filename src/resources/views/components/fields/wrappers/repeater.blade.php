<div class="meros-repeater" data-field="{{ $field->getFieldName() }}">

    @if ($field->label)
        <label class="meros-label">
            {{ $field->label }}
        </label>
    @endif

    @if ($field->description)
        <p class="description">
            {{ $field->description }}
        </p>
    @endif

    <div class="meros-repeater-rows">
        @if ($field->isTableLayout())
            <x-fields.repeater.table 
                :rows="$rows" 
                :field="$field"
            />
        @else
            @foreach ($rows as $row)
                {!! $field->renderRow($row) !!}
            @endforeach
        @endif
    </div>

    <div class="meros-repeater-footer">
        <button type="button" class="button button-secondary meros-add-row">
            Add Row
        </button>
    </div>

</div>