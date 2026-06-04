@if($field->showsNumberInput())
    <div style="display: flex; gap: 1rem; align-items: center; margin-top: -2rem;">
        <div class="nice-form-group" style="flex: 75%;">
            <input
                type="range"
                {!! $field->attributes() !!}
                @if ($field->getValue() !== null && $field->getValue() !== '')
                    value="{{ $field->getValue() }}"
                @endif
                data-field-id="{{ $id }}-range"
                oninput="document.querySelector('[data-field-id=\'{{ $id }}-number\']').value = this.value"
            />
        </div>
        <div class="nice-form-group" style="flex: 25%;">
            <input
                type="number"
                {!! $field->attributes(['id', 'name']) !!}
                name="{{ $field->getName() }}_number"
                id="{{ $id }}-number"
                aria-labelledby="{{ $id }}-label"
                @if ($field->getPlaceholder() && $field->getPlaceholder() !== '')
                    placeholder="{{ $field->getPlaceholder() }}"
                @endif
                @if ($field->getValue() !== null && $field->getValue() !== '')
                    value="{{ $field->getValue() }}"
                @endif
                data-field-id="{{ $id }}-number"
                oninput="document.querySelector('[data-field-id=\'{{ $id }}-range\']').value = this.value"
            />
        </div>
    </div>
@else
    <input
        type="range"
        id="{{ $id }}"
        {!! $field->attributes() !!}
        @if ($field->getValue() !== null && $field->getValue() !== '')
            value="{{ $field->getValue() }}"
        @endif
    />
@endif