@php
    $attributesString = trim((string) $attributes);
    $isRequired = (bool) preg_match('/(?:^|\s)required(?:\s|=|$)/', $attributesString);
    $isDisabled = (bool) preg_match('/(?:^|\s)disabled(?:\s|=|$)/', $attributesString);
    $isFieldSet = in_array($field->handle, ['radio', 'checkboxes', 'repeater', 'rich_text']);
    $showMaxHint = $showMaxHint ?? true;
    $showMinHint = $showMinHint ?? false;

    if ($isRequired && $isDisabled) {
        $isRequired = false;
    }
@endphp

<div x-data="{ isEditor: false }">
    <div
        x-data="merosFieldWrapper"
        class="meros-field nice-form-group" {{ $label === false ? 'style=margin-top:0;' : '' }}
    >
        @if ($label !== false)
            @if (!$isFieldSet)
                <label 
                    @if(in_array($field->handle, ['rich_text']))
                        id="{{ $id }}-label"
                    @else
                        id="{{ $id }}-label"
                        for="{{ $id }}"
                    @endif
                    class="form-label"
                >
                    {{ $label }}
                    @if($isRequired)
                        <span class="required-indicator">*</span>
                    @endif
                </label>
            @endif
        @endif

        @if(!$isFieldSet && $helpText !== false && !empty($helpText))
            <small class="description">{{ $helpText }}</small>
        @endif

        @include($view)

        @if($hasRules)
            @if($field->hasRule('min-chars') || $field->hasRule('max-chars'))
                <div class="meros-field-hints">
                    @if($showMinHint && $field->hasRule('min-chars') && isset($rules['min-chars']['value']) && $rules['min-chars']['value'] > 0)
                        <small class="meros-field-hint">
                            Minimum characters: {{ $rules['min-chars']['value'] }}
                        </small>
                    @endif

                    @if($showMaxHint && $field->hasRule('max-chars') && isset($rules['max-chars']['value']) && $rules['max-chars']['value'] > 0)
                        <small class="meros-field-hint char-count-hint">
                            <span x-text="getControlCharCount()"></span>/{{ $rules['max-chars']['value'] }} characters
                        </small>
                    @endif
                </div>

            @elseif($field->hasRule('min-words') || $field->hasRule('max-words'))
                <div class="meros-field-hints">
                    @if($showMinHint && $field->hasRule('min-words') && isset($rules['min-words']['value']) && $rules['min-words']['value'] > 0)
                        <small class="meros-field-hint">
                            Minimum words: {{ $rules['min-words']['value'] }}
                        </small>
                    @endif

                    @if($showMaxHint && $field->hasRule('max-words') && isset($rules['max-words']['value']) && $rules['max-words']['value'] > 0)
                        <small class="meros-field-hint word-count-hint">
                            <span x-text="getControlWordCount()"></span>/{{ $rules['max-words']['value'] }} words
                        </small>
                    @endif
                </div>

            @elseif($field->hasRule('min-items') || $field->hasRule('max-items'))
                <div class="meros-field-hints">
                    @if($showMinHint && $field->hasRule('min-items') && isset($rules['min-items']['value']) && $rules['min-items']['value'] > 0)
                        <small class="meros-field-hint">
                            Minimum items: {{ $rules['min-items']['value'] }}
                        </small>
                    @endif

                    @if($showMaxHint && $field->hasRule('max-items') && isset($rules['max-items']['value']) && $rules['max-items']['value'] > 0)
                        <small class="meros-field-hint item-count-hint">
                            <span x-text="getControlItemCount()"></span>/{{ $rules['max-items']['value'] }} items
                        </small>
                    @endif
                </div>
            @endif

            <small 
                id="{{ $id }}-validation-messages" 
                class="meros-field-validation-messages" 
                @if(!$field->hasRule('min-chars') && !$field->hasRule('min-words')) style="margin-top: 0.5rem;" @endif
            >
                {{-- Validation messages will be dynamically inserted here --}}
            </small>
        @endif
    </div>
</div>