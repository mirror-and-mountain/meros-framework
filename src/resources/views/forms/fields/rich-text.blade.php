<fieldset id="{{ $id }}-wrapper" class="nice-form-group" data-field-type="{{ $fieldType }}">
    @if(!$isSubField && $label !== false)
        <legend 
            id="{{ $id . '-label' }}"
            class="form-label"
            style="margin-bottom: 0.5rem;"
        >
            {{ $label }}
            @if($isRequired)
                <span class="required-indicator">*</span>
            @endif
        </legend>
    @endif

    @if($helpText !== false && !empty($helpText))
        <small class="description">{{ $helpText }}</small>
    @endif

    <div
        x-data="merosRichTextField('{{ $id }}', '{{ $serialisedRules }}')"
        id="{{ $id }}"
        name="{{ $name }}"
        {!! $attributes !!}
        aria-labelledby="{{ $id . '-label' }}"
    >
        {!! $value !!}
    </div>
</fieldset>
