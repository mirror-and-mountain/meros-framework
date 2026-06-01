@php 
    $id         = $field->getId();
    $fieldType  = $field->getType();
    $classList  = $field->classList();
    $isSubField = $field->isSubField();
    $fieldValue = $field->getValue();
    $layout     = $field->getLayout();
    $helpText   = $showHelp ? $field->getHelpText() : '';
    $helpTextPosition = $showHelp ? $field->getHelpTextPosition() : null;
    $fieldSetClass    = $isSubField ? 'nice-form-group !-mt-2.5' : 'nice-form-group';
@endphp

<fieldset id="{{ $id }}" class="{{ $fieldSetClass }}" data-field-type="{{ $fieldType }}" x-data="{ layout: '{{ $layout }}' }">
    @if(!$isSubField)
        <legend>{{ $field->getLabel() }}</legend>
    
        @if($showHelp)
            <small class="description">{{ $helpText }}</small>
        @endif
    @endif

    @if($layout === 'horizontal')
        <div class="flex gap-3 items-start -mt-6">
    @endif

    @foreach($field->getOptions() as $value => $label)
        <div class="nice-form-group">
            @php
                $checked = $field->allowsMultiple()
                    ? (is_array($fieldValue) && in_array($value, $fieldValue, true))
                    : ($fieldValue !== null && (string) $fieldValue === (string) $value);
            @endphp
            <input 
                id="{{ $id }}_{{ $value }}" 
                {!! $field->attributes(['id', 'name', 'data-field-type']) !!} 
                name="{{ $field->getName(!$isSubField) . ($field->allowsMultiple() ? '[]' : '') }}"
                data-option-value="{{ $value }}"
                @checked($checked) 
            />
            <label for="{{ $id }}_{{ $value }}" :class="layout === 'horizontal' ? '!whitespace-pre' : ''">{{ $label }}</label>
        </div>
    @endforeach

    @if($layout === 'horizontal')
        </div>
    @endif
</fieldset>