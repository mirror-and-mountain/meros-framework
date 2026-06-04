@php 
    $placeholder = $field->getPlaceholder();
    $showsIcon   = $field->showsIcon();
    $classList   = $field->classList();
    $value       = $field->getValue();
    $onChange    = $field->getOnChange();

    if ($showsIcon) {
        $classList .= ' icon-' . $field->getIconPosition();
    }
@endphp

<input 
    {!! $field->attributes(['class']) !!}
    @if (!empty($classList))
        class="{{ ltrim($classList) }}"
    @endif
    @if ($placeholder && $placeholder !== '')
        placeholder="{{ $placeholder }}"
    @endif
    @if ($value !== null && $value !== '')
        value="{{ $value }}"
    @endif
    @if ($onChange && $onChange !== '')
        x-on:change="{{ $onChange }}"
    @endif
/>