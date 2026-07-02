<fieldset id="{{ $id }}-wrapper" class="nice-form-group" data-field-type="{{ $field->handle }}">
    @if(!$isSubField && $label !== false)
        <legend 
            id="{{ $id . '-label' }}"
            class="form-label"
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
        style="background-color: var(--nf-input-background-color); border: 1px solid var(--nf-input-border-color); color: var(--nf-input-color); font-size: var(--nf-input-font-size);"
    >
        {!! $value !!}
    </div>
</fieldset>
