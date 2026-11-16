@if ($wrapper === 'meros::forms.fields.checkbox' && $horizontal === true)
    @php
        $inRepeater = $repeaterId !== null;
    @endphp
    <div
        class="meros-field-wrapper mforms-field-wrapper nice-form-group"
        data-field-conditions="{{ $conditionsString }}"
        data-field-origin-name="{{ $originalName }}"
    >
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            title="{{ $label }}"
            @if ($toggle)
                class="switch"
            @endif
            {!! $attributeString !!}
            {{ $defaultValue ? 'checked' : '' }}
        />
        @if(!$inRepeater)
            <label for="{{ $id }}">{{ $label }}
                @if($description !== '')
                    <div style="margin-top: 0.5rem;">
                        <small class="meros-field-description">{!! $description !!}</small>
                    </div>
                @endif
            </label>
        @endif
    </div>
@else
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        title="{{ $label }}"
        {!! $attributeString !!}
        @if($toggle)
            class="switch"
        @endif
        {{ $defaultValue ? 'checked' : '' }}
    />
@endif
