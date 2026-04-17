<div class="meros-repeater" data-field="{{ $field->name }}">

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
        <x-fields.repeater.table 
            :rows="$rows" 
            :field="$field"
            :niceFields="true"
        />
    </div>

    <div class="meros-repeater-footer">
        <button type="button" class="button button-secondary meros-add-row" style="margin-top: 10px;">
            Add Row
        </button>
    </div>

</div>