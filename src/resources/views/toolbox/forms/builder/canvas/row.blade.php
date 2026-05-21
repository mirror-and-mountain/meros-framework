@php
    $rowFields = $canvasRow['fields'] ?? [];
    $fieldRowIndex = $rowIndex;
@endphp

<div class="flex gap-3 mb-1" wire:key="row-{{ $rowIndex }}">
    @foreach($rowFields as $fieldIndex => $field)
        @include('meros::toolbox.forms.builder.canvas.field', [
            'scope'         => 'top',
            'rowFields'     => $rowFields,
            'fieldRowIndex' => $fieldRowIndex,
            'fieldIndex'    => $fieldIndex,
            'field'         => $field,
        ])
    @endforeach
</div>
