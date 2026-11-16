@php
    $inRepeater = $repeaterId !== null;
@endphp

@if ($wrapper === 'meros::forms.fields.richtext')
    <div x-data="mformsRichText" class="mforms-richtext-field meros-field-wrapper mforms-field-wrapper nice-form-group">
        @if(!$inRepeater && $type !== 'hidden')
            <label for="{{ $id }}" class="meros-field-label">{{ $label }}</label>
        @endif
        @if (!$inRepeater && $description !== '')
            <div style="margin-bottom: 0.5rem;">
                <small class="meros-field-description">{!! $description !!}</small>
            </div>
        @endif
        <input
            x-ref="input"
            type="hidden"
            id="{{ $id }}"
            name="{{ $name }}"
            value="{{ wp_kses_post($defaultValue ?? '') }}"
            {!! $attributeString !!}
        >
        <div x-ref="editor" data-label="{{ $label ?? 'Rich text' }}"></div>
    </div>
@else
    <div class="mforms-richtext-field">
        <input
            x-ref="input"
            type="hidden"
            id="{{ $id }}"
            name="{{ $name }}"
            value="{{ wp_kses_post($defaultValue ?? '') }}"
            {!! $attributeString !!}
        >
        <div x-ref="editor" data-label="{{ $label ?? 'Rich text' }}"></div>
    </div>
@endif
