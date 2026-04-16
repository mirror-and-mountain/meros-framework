<div class="meros-repeater" data-field="{{ $field->getFieldName() }}">
    <div class="meros-repeater-rows">
         <x-fields.repeater.table 
            :rows="$rows" 
            :field="$field"
        />
    </div>

    <div class="meros-repeater-footer">
        <button type="button" class="button button-secondary meros-add-row" style="margin-top: 10px;">
            Add Row
        </button>
    </div>

</div>