@php
    $isGroupRow = $childGroup !== null && is_array($childGroup) && is_array($childGroup['rows'] ?? null);
@endphp
<div class="mforms-row">
    @if($isGroupRow)
        @include('meros::forms.field-group', $childGroup)
    @else
        @foreach($fields as $field)
            @php
                $properties = $field['properties'] ?? [];
                $wrapper = null;
                if (isset($properties['wrapper'])) {
                    $wrapper = $properties['wrapper'];
                }
            @endphp

            @if($properties !== [] && $wrapper)
                @include($wrapper, $field ?? [])
            @endif
        @endforeach
    @endif
</div>