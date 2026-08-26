@php
    $isGroupRow = $childGroup !== null && is_array($childGroup) && is_array($childGroup['rows'] ?? null);
@endphp
<div class="mforms-row">
    @if($isGroupRow)
        @include('meros::forms.field-group', $childGroup)
    @else
        @foreach($fields as $field)
           @include($field['wrapper'], $field)
        @endforeach
    @endif
</div>