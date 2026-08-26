<fieldset 
    id="{{ $id }}"
    class="mforms-field-group mforms-section nice-form-group"
    data-name="{{ $name }}"
>
    @if($metaBox === false && $title !== '')
        <legend class="mforms-section-title">{{ $title }}</legend>
    @endif
    @if($description !== '')
        @php
            $class = 'mforms-section-description';
            if ($metaBox) {
                $class .= ' mforms-section-description-metabox';
            }
        @endphp
        <small class="{{ $class }}">{{ $description }}</small>
    @endif
    @foreach($rows as $row)
        @include('meros::forms.field-row', $row)
    @endforeach
    @if($metaBox === false)
        <hr class="mforms-section-divider" />
    @endif
</fieldset>